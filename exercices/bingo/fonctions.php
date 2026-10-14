<?php
// Bingo de l'hallucination : un texte produit par une IA, truffé d'erreurs
// plantées, est projeté et lu à voix haute ; chaque stagiaire coche sur son
// téléphone les cases repérées. Les 9 cases sont les mêmes pour tous, à des
// places tirées au hasard pour chacun. Première ligne complète (ligne, colonne
// ou diagonale) : « Bingo ! ». Le formateur fait citer les passages et valide ;
// le premier bingo validé gagne BINGO_POINTS points individuels.
//
// Cases, texte et corrigé : formations/<slug>/formation.php, clé 'bingo'.
//
// Aucune sortie : ce fichier ne définit que des helpers.
require_once __DIR__ . '/../../core/etapes.php';
require_once __DIR__ . '/../../core/participants.php';
require_once __DIR__ . '/../../core/points.php';

const BINGO_POINTS = 10;
const BINGO_SOURCE = 'bingo';
// Les 8 lignes gagnantes de la grille 3×3, en positions 0 à 8 (lecture ligne par ligne)
const BINGO_LIGNES = [[0, 1, 2], [3, 4, 5], [6, 7, 8], [0, 3, 6], [1, 4, 7], [2, 5, 8], [0, 4, 8], [2, 4, 6]];

function bingo_config(): ?array
{
    $c = formation()['bingo'] ?? null;
    return $c && count($c['cases'] ?? []) === 9 ? $c : null;
}

function bingo_table_ok(): bool
{
    try {
        db()->query('SELECT 1 FROM bingo_grilles LIMIT 1');
        return true;
    } catch (PDOException $e) {
        return false;
    }
}

// La partie de la séance (créée à la première demande)
function bingo_partie(): array
{
    $st = db()->prepare('INSERT IGNORE INTO bingo_parties (formation, seance) VALUES (?, ?)');
    $st->execute([formation_slug(), aujourdhui_local()]);
    $st = db()->prepare('SELECT * FROM bingo_parties WHERE formation = ? AND seance = ?');
    $st->execute([formation_slug(), aujourdhui_local()]);
    return $st->fetch();
}

function bingo_basculer(string $colonne): void
{
    if (in_array($colonne, ['texte_visible', 'corrige_visible'], true)) {
        bingo_partie();
        $st = db()->prepare("UPDATE bingo_parties SET $colonne = 1 - $colonne WHERE formation = ? AND seance = ?");
        $st->execute([formation_slug(), aujourdhui_local()]);
    }
}

// Grille d'un joueur, créée (cases mélangées) à sa première visite.
//   ordre  : position => numéro de case de la config
//   coches : positions cochées
function bingo_grille(int $participant_id): array
{
    $ordre = range(0, 8);
    shuffle($ordre);
    $st = db()->prepare('INSERT IGNORE INTO bingo_grilles (formation, seance, participant_id, ordre, coches) VALUES (?, ?, ?, ?, ?)');
    $st->execute([formation_slug(), aujourdhui_local(), $participant_id, json_encode($ordre), '[]']);
    $st = db()->prepare('SELECT * FROM bingo_grilles WHERE formation = ? AND seance = ? AND participant_id = ?');
    $st->execute([formation_slug(), aujourdhui_local(), $participant_id]);
    return bingo_decoder($st->fetch());
}

function bingo_decoder(array $g): array
{
    $g['ordre']  = array_map('intval', json_decode($g['ordre'], true) ?: range(0, 8));
    $g['coches'] = array_values(array_filter(array_map('intval', json_decode($g['coches'], true) ?: []), fn($p) => $p >= 0 && $p < 9));
    return $g;
}

// Première ligne complète des positions cochées, ou null
function bingo_ligne(array $coches): ?array
{
    foreach (BINGO_LIGNES as $ligne) {
        if (!array_diff($ligne, $coches)) {
            return $ligne;
        }
    }
    return null;
}

function bingo_gagnant(): ?array
{
    $st = db()->prepare("SELECT g.*, p.prenom, p.equipe FROM bingo_grilles g JOIN participants p ON p.id = g.participant_id
                         WHERE g.formation = ? AND g.seance = ? AND g.statut = 'valide' LIMIT 1");
    $st->execute([formation_slug(), aujourdhui_local()]);
    $g = $st->fetch();
    return $g ? bingo_decoder($g) : null;
}

// Coches d'un joueur : seulement texte affiché, sans bingo en attente, partie non gagnée
function bingo_cocher(int $participant_id, array $coches): bool
{
    $g = bingo_grille($participant_id);
    if (!bingo_partie()['texte_visible'] || $g['statut'] === 'attente' || $g['statut'] === 'valide' || bingo_gagnant()) {
        return false;
    }
    $coches = array_values(array_unique(array_filter(array_map('intval', $coches), fn($p) => $p >= 0 && $p < 9)));
    $st = db()->prepare('UPDATE bingo_grilles SET coches = ? WHERE id = ?');
    $st->execute([json_encode($coches), $g['id']]);
    return true;
}

// « Bingo ! » : accepté avec une ligne complète, mis dans la file du formateur
function bingo_annoncer(int $participant_id): bool
{
    $g = bingo_grille($participant_id);
    if (!bingo_partie()['texte_visible'] || !bingo_ligne($g['coches']) || in_array($g['statut'], ['attente', 'valide'], true) || bingo_gagnant()) {
        return false;
    }
    $st = db()->prepare("UPDATE bingo_grilles SET statut = 'attente', bingo_at = NOW(6) WHERE id = ?");
    $st->execute([$g['id']]);
    return true;
}

// Les bingos annoncés, dans l'ordre d'arrivée (validés, en attente et refusés)
function bingo_annonces(): array
{
    $st = db()->prepare("SELECT g.*, p.prenom, p.equipe FROM bingo_grilles g JOIN participants p ON p.id = g.participant_id
                         WHERE g.formation = ? AND g.seance = ? AND g.bingo_at IS NOT NULL ORDER BY g.bingo_at, g.id");
    $st->execute([formation_slug(), aujourdhui_local()]);
    return array_map('bingo_decoder', $st->fetchAll());
}

function bingo_nb_joueurs(): int
{
    $st = db()->prepare('SELECT COUNT(*) FROM bingo_grilles WHERE formation = ? AND seance = ?');
    $st->execute([formation_slug(), aujourdhui_local()]);
    return (int)$st->fetchColumn();
}

// Décision du formateur sur un bingo : valider (un seul gagnant, points),
// refuser (le joueur reprend), ou annuler une validation
function bingo_decider(int $grille_id, string $decision): void
{
    $st = db()->prepare('SELECT * FROM bingo_grilles WHERE id = ? AND formation = ? AND seance = ?');
    $st->execute([$grille_id, formation_slug(), aujourdhui_local()]);
    $g = $st->fetch();
    if (!$g) {
        return;
    }
    $points = points_actifs() && points_table_ok();
    if ($decision === 'valider' && $g['statut'] === 'attente' && !bingo_gagnant()) {
        db()->prepare("UPDATE bingo_grilles SET statut = 'valide' WHERE id = ?")->execute([$grille_id]);
        if ($points) {
            points_joueur_fixer((int)$g['participant_id'], BINGO_SOURCE, BINGO_POINTS, 'Bingo de l\'hallucination');
        }
    } elseif ($decision === 'refuser' && $g['statut'] === 'attente') {
        db()->prepare("UPDATE bingo_grilles SET statut = 'refuse' WHERE id = ?")->execute([$grille_id]);
    } elseif ($decision === 'annuler' && $g['statut'] === 'valide') {
        db()->prepare("UPDATE bingo_grilles SET statut = 'attente' WHERE id = ?")->execute([$grille_id]);
        if ($points) {
            points_joueur_fixer((int)$g['participant_id'], BINGO_SOURCE, null, '');
        }
    }
}

// Recommencer : grilles et points de la séance effacés, texte de nouveau caché
function bingo_recommencer(): void
{
    if (points_table_ok()) {
        $st = db()->prepare('DELETE FROM points WHERE formation = ? AND seance = ? AND source = ?');
        $st->execute([formation_slug(), aujourdhui_local(), BINGO_SOURCE]);
    }
    foreach (['bingo_grilles', 'bingo_parties'] as $table) {
        $st = db()->prepare("DELETE FROM $table WHERE formation = ? AND seance = ?");
        $st->execute([formation_slug(), aujourdhui_local()]);
    }
}

// Le texte en HTML ; avec le corrigé, les passages fautifs surlignés et numérotés
function bingo_texte_html(bool $corrige): string
{
    $c = bingo_config();
    $h = e($c['texte']);
    if ($corrige) {
        foreach ($c['corrige'] as $i => [$passages]) {
            foreach ((array)$passages as $passage) {
                $h = str_replace(e($passage), '<mark data-n="' . ($i + 1) . '">' . e($passage) . '</mark>', $h);
            }
        }
    }
    return nl2br($h, false);
}

// Bingo de l'hallucination : le bouton vers bingo.php, derrière l'étape $cle
function bloc_bingo(string $cle = 'bingo'): string
{
    if (!etape_ouverte($cle)) {
        return carte_verrouillee($cle);
    }
    $h = '<section class="card">' . "\n"
       . '    <h2>' . e(etape_titre($cle)) . '</h2>' . "\n";
    if (!participant_courant()) {
        return $h . '    <p class="lead">Indiquez d\'abord votre prénom ci-dessus.</p>' . "\n" . '  </section>';
    }
    return $h . '    <p class="lead">Un texte écrit par une IA va s\'afficher : repérez ses hallucinations. Première ligne complète gagne.</p>' . "\n"
              . '    <a class="btn btn-primary" href="' . e(avec_f('bingo.php')) . '">Ma grille de bingo</a>' . "\n"
              . '  </section>';
}
