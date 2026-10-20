<?php
// Jeu de tri : qui est quoi ? Chaque stagiaire range 12 cartes dans 5
// catégories (prompt, assistant, workflow, automatisation, agent). Le score
// de l'équipe est la moyenne de ses membres : nombre moyen de cartes bien
// placées, arrondi, × TRI_POINTS_CARTE. Les cartes ambiguës acceptent deux
// réponses. Correction carte par carte au vidéoprojecteur.
//
// Catégories et cartes : formations/<slug>/formation.php, clé 'tri'.
//
// Aucune sortie : ce fichier ne définit que des helpers.
require_once __DIR__ . '/../../core/etapes.php';
require_once __DIR__ . '/../../core/participants.php';
require_once __DIR__ . '/../../core/points.php';

const TRI_POINTS_CARTE = 5;
const TRI_SOURCE = 'tri';

function tri_config(): ?array
{
    $c = formation()['tri'] ?? null;
    return $c && !empty($c['categories']) && !empty($c['cartes']) ? $c : null;
}

function tri_table_ok(): bool
{
    try {
        db()->query('SELECT 1 FROM tri_copies LIMIT 1');
        return true;
    } catch (PDOException $e) {
        return false;
    }
}

// La partie de la séance (créée à la première demande).
//   clos   : 1 quand le formateur a fermé le tri
//   carte  : correction — 0 pas commencée, 1 à N carte projetée, N+1 résultats
//   revele : 1 quand la réponse de la carte projetée est dévoilée
function tri_partie(): array
{
    $st = db()->prepare('INSERT IGNORE INTO tri_parties (formation, seance) VALUES (?, ?)');
    $st->execute([formation_slug(), aujourdhui_local()]);
    $st = db()->prepare('SELECT * FROM tri_parties WHERE formation = ? AND seance = ?');
    $st->execute([formation_slug(), aujourdhui_local()]);
    $p = $st->fetch();
    $p['carte'] = (int)$p['carte'];
    return $p;
}

function tri_maj(array $champs): void
{
    tri_partie();
    $sets = implode(', ', array_map(fn($c) => "$c = ?", array_keys($champs)));
    $st = db()->prepare("UPDATE tri_parties SET $sets WHERE formation = ? AND seance = ?");
    $st->execute(array_merge(array_values($champs), [formation_slug(), aujourdhui_local()]));
}

// Réponses d'un joueur : n° de carte (0…) => clé de catégorie
function tri_reponses(int $participant_id): array
{
    $st = db()->prepare('SELECT reponses FROM tri_copies WHERE formation = ? AND seance = ? AND participant_id = ?');
    $st->execute([formation_slug(), aujourdhui_local(), $participant_id]);
    return tri_nettoyer(json_decode((string)$st->fetchColumn(), true) ?: []);
}

function tri_nettoyer(array $reponses): array
{
    $c = tri_config();
    $ok = [];
    foreach ($reponses as $n => $cat) {
        if (isset($c['cartes'][(int)$n]) && isset($c['categories'][$cat])) {
            $ok[(int)$n] = (string)$cat;
        }
    }
    ksort($ok);
    return $ok;
}

// Range une carte (ou la retire avec $categorie = ''), tant que le tri est ouvert
function tri_ranger(int $participant_id, int $carte, string $categorie): bool
{
    $c = tri_config();
    if (tri_partie()['clos'] || !isset($c['cartes'][$carte]) || ($categorie !== '' && !isset($c['categories'][$categorie]))) {
        return false;
    }
    $reponses = tri_reponses($participant_id);
    if ($categorie === '') {
        unset($reponses[$carte]);
    } else {
        $reponses[$carte] = $categorie;
    }
    $reponses = tri_nettoyer($reponses);
    $st = db()->prepare('INSERT INTO tri_copies (formation, seance, participant_id, reponses) VALUES (?, ?, ?, ?)
                         ON DUPLICATE KEY UPDATE reponses = VALUES(reponses)');
    $st->execute([formation_slug(), aujourdhui_local(), $participant_id, json_encode((object)$reponses)]);
    return true;
}

function tri_juste(int $carte, ?string $categorie): bool
{
    return $categorie !== null && in_array($categorie, tri_config()['cartes'][$carte]['reponses'], true);
}

// Les joueurs de la séance placés dans une équipe, avec leurs réponses et leur score
function tri_joueurs(): array
{
    $st = db()->prepare('SELECT participant_id, reponses FROM tri_copies WHERE formation = ? AND seance = ?');
    $st->execute([formation_slug(), aujourdhui_local()]);
    $copies = array_column($st->fetchAll(), 'reponses', 'participant_id');
    $joueurs = [];
    foreach (participants_seance() as $p) {
        if (!equipe_existe($p['equipe'])) {
            continue;
        }
        $r = tri_nettoyer(json_decode((string)($copies[$p['id']] ?? ''), true) ?: []);
        $justes = count(array_filter(array_keys($r), fn($n) => tri_juste($n, $r[$n])));
        $joueurs[] = $p + ['reponses' => $r, 'justes' => $justes];
    }
    return $joueurs;
}

// Score de chaque équipe qui joue : membres, moyenne de cartes justes, points
function tri_scores(): array
{
    $scores = [];
    foreach (tri_joueurs() as $j) {
        $scores[$j['equipe']][] = $j['justes'];
    }
    foreach ($scores as $eq => $justes) {
        $moyenne = array_sum($justes) / count($justes);
        $scores[$eq] = ['membres' => count($justes), 'moyenne' => $moyenne,
                        'points' => (int)round($moyenne) * TRI_POINTS_CARTE];
    }
    uasort($scores, fn($a, $b) => $b['moyenne'] <=> $a['moyenne']);
    return $scores;
}

// Pour une carte : équipe => catégorie => nombre de joueurs qui l'y ont rangée
function tri_repartition(int $carte): array
{
    $rep = [];
    foreach (tri_joueurs() as $j) {
        if (isset($j['reponses'][$carte])) {
            $rep[$j['equipe']][$j['reponses'][$carte]] = ($rep[$j['equipe']][$j['reponses'][$carte]] ?? 0) + 1;
        }
    }
    return $rep;
}

function tri_points_donnes(): bool
{
    foreach (array_keys(tri_scores()) as $eq) {
        if (points_source_donnee(TRI_SOURCE, $eq)) {
            return true;
        }
    }
    return false;
}

// Donne (ou reprend, s'ils sont déjà donnés) les points du tri à toutes les équipes
function tri_basculer_points(): void
{
    $donnes = tri_points_donnes();
    foreach (tri_scores() as $eq => $s) {
        if ($donnes === points_source_donnee(TRI_SOURCE, $eq) && ($donnes || $s['points'] > 0)) {
            points_source_basculer(TRI_SOURCE, $eq, $s['points'], 'Jeu de tri');
        }
    }
}

// Recommencer : copies, correction et points du tri de la séance effacés
function tri_recommencer(): void
{
    if (points_table_ok()) {
        $st = db()->prepare('DELETE FROM points WHERE formation = ? AND seance = ? AND source = ?');
        $st->execute([formation_slug(), aujourdhui_local(), TRI_SOURCE]);
    }
    foreach (['tri_copies', 'tri_parties'] as $table) {
        $st = db()->prepare("DELETE FROM $table WHERE formation = ? AND seance = ?");
        $st->execute([formation_slug(), aujourdhui_local()]);
    }
}

function tri_libelles(array $cles): string
{
    return implode(' ou ', array_map(fn($k) => tri_config()['categories'][$k], $cles));
}

// Jeu de tri : le bouton vers tri.php, derrière l'étape $cle
function bloc_tri(string $cle = 'tri'): string
{
    if (!etape_ouverte($cle)) {
        return carte_verrouillee($cle);
    }
    $p = participant_courant();
    $h = '<section class="card">' . "\n"
       . '    <h2>' . e(etape_titre($cle)) . '</h2>' . "\n";
    if (!$p) {
        return $h . '    <p class="lead">Indiquez d\'abord votre prénom ci-dessus.</p>' . "\n" . '  </section>';
    }
    if (!equipe_existe($p['equipe'])) {
        return $h . '    <p class="lead">Un jeu en équipe : attendez d\'être placé dans la vôtre.</p>' . "\n" . '  </section>';
    }
    return $h . '    <p class="lead">Prompt, assistant, workflow, automatisation ou agent ? Rangez les 12 cartes : votre score compte pour votre équipe.</p>' . "\n"
              . '    <a class="btn btn-primary" href="' . e(avec_f('tri.php')) . '">Trier les cartes</a>' . "\n"
              . '  </section>';
}
