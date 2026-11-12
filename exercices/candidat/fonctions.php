<?php
// Le candidat qui répond à côté : un prompt bâclé (« Fais-moi un mail pour un
// client. ») et la réponse creuse qu'il a produite. Chaque équipe, par la main
// de son secrétaire, liste ce qui manquait, réécrit le prompt, le teste et
// colle la réponse de son IA. Puis le groupe vote, copies anonymes, jamais
// pour son équipe ; CANDIDAT_POINTS à l'équipe gagnante (ex-aequo : à chacune).
//
// Le cas (prompt et réponse) : formations/<slug>/formation.php, clé 'candidat'.
//
// Aucune sortie : ce fichier ne définit que des helpers.
require_once __DIR__ . '/../../core/etapes.php';
require_once __DIR__ . '/../../core/participants.php';
require_once __DIR__ . '/../../core/points.php';

const CANDIDAT_POINTS     = 20;
const CANDIDAT_SOURCE     = 'candidat';
const CANDIDAT_MAX_TEXTE  = 4000;
const CANDIDAT_MAX_RESULT = 10000;
const CANDIDAT_CHAMPS     = ['manques' => CANDIDAT_MAX_TEXTE, 'prompt' => CANDIDAT_MAX_TEXTE, 'resultat' => CANDIDAT_MAX_RESULT];

function candidat_config(): ?array
{
    return formation()['candidat'] ?? null;
}

function candidat_table_ok(): bool
{
    try {
        db()->query('SELECT 1 FROM candidat_votes LIMIT 1');
        return true;
    } catch (PDOException $e) {
        return false;
    }
}

// La partie de la séance : etat redaction | vote | revele
function candidat_partie(): array
{
    $st = db()->prepare('INSERT IGNORE INTO candidat_parties (formation, seance) VALUES (?, ?)');
    $st->execute([formation_slug(), aujourdhui_local()]);
    $st = db()->prepare('SELECT * FROM candidat_parties WHERE formation = ? AND seance = ?');
    $st->execute([formation_slug(), aujourdhui_local()]);
    return $st->fetch();
}

function candidat_etat(string $etat): void
{
    candidat_partie();
    $st = db()->prepare('UPDATE candidat_parties SET etat = ? WHERE formation = ? AND seance = ?');
    $st->execute([$etat, formation_slug(), aujourdhui_local()]);
}

// Copie d'une équipe (null si elle n'a pas commencé), avec le prénom de son secrétaire
function candidat_copie(string $equipe): ?array
{
    $st = db()->prepare('SELECT c.*, p.prenom AS secretaire FROM candidat_copies c
                         LEFT JOIN participants p ON p.id = c.secretaire_id
                         WHERE c.formation = ? AND c.seance = ? AND c.equipe = ?');
    $st->execute([formation_slug(), aujourdhui_local(), $equipe]);
    return $st->fetch() ?: null;
}

// Toutes les copies de la séance, rangées par équipe (ordre des équipes déclarées)
function candidat_copies(): array
{
    $out = [];
    foreach (array_keys(equipes()) as $eq) {
        if ($c = candidat_copie($eq)) {
            $out[$eq] = $c;
        }
    }
    return $out;
}

// Copies en lice au vote : celles qui ont un prompt, avec leur lettre, dans l'ordre des lettres
function candidat_en_lice(): array
{
    $c = array_filter(candidat_copies(), fn($x) => $x['lettre'] !== null);
    uasort($c, fn($a, $b) => strcmp($a['lettre'], $b['lettre']));
    return $c;
}

// Devenir le secrétaire de son équipe (le précédent repasse en lecture)
function candidat_prendre_la_main(array $participant): bool
{
    if (!equipe_existe($participant['equipe']) || candidat_partie()['etat'] !== 'redaction') {
        return false;
    }
    $st = db()->prepare('INSERT INTO candidat_copies (formation, seance, equipe, secretaire_id, manques, prompt, resultat)
                         VALUES (?, ?, ?, ?, \'\', \'\', \'\')
                         ON DUPLICATE KEY UPDATE secretaire_id = VALUES(secretaire_id)');
    $st->execute([formation_slug(), aujourdhui_local(), $participant['equipe'], (int)$participant['id']]);
    return true;
}

// Enregistrement par le secrétaire, pendant la rédaction seulement
function candidat_sauver(array $participant, array $saisie): bool
{
    $copie = equipe_existe($participant['equipe']) ? candidat_copie($participant['equipe']) : null;
    if (!$copie || (int)$copie['secretaire_id'] !== (int)$participant['id'] || candidat_partie()['etat'] !== 'redaction') {
        return false;
    }
    $valeurs = [];
    foreach (CANDIDAT_CHAMPS as $champ => $max) {
        $valeurs[] = mb_substr(trim(str_replace("\r\n", "\n", (string)($saisie[$champ] ?? ''))), 0, $max);
    }
    $st = db()->prepare('UPDATE candidat_copies SET manques = ?, prompt = ?, resultat = ? WHERE id = ?');
    $st->execute(array_merge($valeurs, [$copie['id']]));
    return true;
}

// Ouvre le vote : les copies qui ont un prompt reçoivent une lettre au hasard
function candidat_ouvrir_vote(): void
{
    $copies = array_filter(candidat_copies(), fn($c) => trim($c['prompt']) !== '');
    $ids = array_column($copies, 'id');
    shuffle($ids);
    $st = db()->prepare('UPDATE candidat_copies SET lettre = NULL WHERE formation = ? AND seance = ?');
    $st->execute([formation_slug(), aujourdhui_local()]);
    $st = db()->prepare('UPDATE candidat_copies SET lettre = ? WHERE id = ?');
    foreach ($ids as $i => $id) {
        $st->execute([chr(65 + $i), $id]);
    }
    candidat_etat('vote');
}

// Vote d'un participant (il peut changer d'avis) ; jamais pour la copie de son équipe
function candidat_voter(array $participant, int $copie_id): bool
{
    if (candidat_partie()['etat'] !== 'vote') {
        return false;
    }
    $copie = array_values(array_filter(candidat_en_lice(), fn($c) => (int)$c['id'] === $copie_id))[0] ?? null;
    if (!$copie || $copie['equipe'] === $participant['equipe']) {
        return false;
    }
    $st = db()->prepare('REPLACE INTO candidat_votes (formation, seance, participant_id, copie_id) VALUES (?, ?, ?, ?)');
    $st->execute([formation_slug(), aujourdhui_local(), (int)$participant['id'], $copie_id]);
    return true;
}

function candidat_mon_vote(int $participant_id): ?int
{
    $st = db()->prepare('SELECT copie_id FROM candidat_votes WHERE formation = ? AND seance = ? AND participant_id = ?');
    $st->execute([formation_slug(), aujourdhui_local(), $participant_id]);
    $v = $st->fetchColumn();
    return $v === false ? null : (int)$v;
}

// Voix par copie : id de copie => nombre
function candidat_decompte(): array
{
    $st = db()->prepare('SELECT copie_id, COUNT(*) n FROM candidat_votes WHERE formation = ? AND seance = ? GROUP BY copie_id');
    $st->execute([formation_slug(), aujourdhui_local()]);
    return array_map('intval', array_column($st->fetchAll(), 'n', 'copie_id'));
}

function candidat_nb_votants(): int
{
    $st = db()->prepare('SELECT COUNT(*) FROM candidat_votes WHERE formation = ? AND seance = ?');
    $st->execute([formation_slug(), aujourdhui_local()]);
    return (int)$st->fetchColumn();
}

// Équipes gagnantes (ex-aequo compris) ; aucune sans vote
function candidat_gagnants(): array
{
    $voix = candidat_decompte();
    if (!$voix) {
        return [];
    }
    $max = max($voix);
    return array_values(array_map(fn($c) => $c['equipe'],
        array_filter(candidat_en_lice(), fn($c) => ($voix[(int)$c['id']] ?? 0) === $max)));
}

// Révélation : les points vont aux gagnants
function candidat_reveler(): void
{
    candidat_etat('revele');
    if (points_actifs() && points_table_ok()) {
        foreach (candidat_gagnants() as $eq) {
            if (!points_source_donnee(CANDIDAT_SOURCE, $eq)) {
                points_ajouter($eq, null, CANDIDAT_POINTS, 'Le candidat qui répond à côté', CANDIDAT_SOURCE);
            }
        }
    }
}

// Recommencer : copies, votes et points de l'exercice effacés
function candidat_recommencer(): void
{
    if (points_table_ok()) {
        $st = db()->prepare('DELETE FROM points WHERE formation = ? AND seance = ? AND source = ?');
        $st->execute([formation_slug(), aujourdhui_local(), CANDIDAT_SOURCE]);
    }
    foreach (['candidat_votes', 'candidat_copies', 'candidat_parties'] as $table) {
        $st = db()->prepare("DELETE FROM $table WHERE formation = ? AND seance = ?");
        $st->execute([formation_slug(), aujourdhui_local()]);
    }
}

// Le cas à corriger : le prompt du recruteur et la réponse du candidat
function candidat_cas_html(string $classe = ''): string
{
    $c = candidat_config();
    return '<div class="cd-cas ' . e($classe) . '">'
         . '<div class="cd-prompt"><p class="cd-titre">Le prompt du recruteur</p><p class="cd-texte">« ' . e($c['prompt']) . ' »</p></div>'
         . '<div class="cd-reponse"><p class="cd-titre">La réponse du candidat</p><div class="cd-texte">' . nl2br(e($c['reponse']), false) . '</div></div>'
         . '</div>';
}

// Le candidat qui répond à côté : le bouton vers candidat.php, derrière l'étape $cle
function bloc_candidat(string $cle = 'candidat'): string
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
    return $h . '    <p class="lead">Ce candidat IA a mal compris la mission : qu\'est-ce que le recruteur a oublié de lui dire ? Défi en équipe, puis vote.</p>' . "\n"
              . '    <a class="btn btn-primary" href="' . e(avec_f('candidat.php')) . '">Relever le défi</a>' . "\n"
              . '  </section>';
}
