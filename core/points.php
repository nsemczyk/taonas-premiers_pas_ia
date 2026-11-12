<?php
// Points de la séance : attribués à une équipe ou à un joueur, indépendamment.
// Une formation les active dans formations/<slug>/formation.php ('points' => true).
//
// Aucune sortie : ce fichier ne définit que des helpers.
require_once __DIR__ . '/participants.php';

const POINTS_BOULE = 10;   // prompt boule de neige : points par équipe

function points_actifs(): bool
{
    return !empty(formation()['points']);
}

function points_table_ok(): bool
{
    try {
        db()->query('SELECT 1 FROM points LIMIT 1');
        return true;
    } catch (PDOException $e) {
        return false;
    }
}

// Ajoute (ou retire, valeur négative) des points à une équipe ou à un joueur de la séance
function points_ajouter(?string $equipe, ?int $participant_id, int $valeur, string $motif = '', ?string $source = null): void
{
    if ($valeur === 0 || ($equipe === null) === ($participant_id === null)) {
        return;   // exactement une cible
    }
    if ($equipe !== null && !equipe_existe($equipe)) {
        return;
    }
    if ($participant_id !== null && !in_array($participant_id, array_map('intval', array_column(participants_seance(), 'id')), true)) {
        return;
    }
    $st = db()->prepare('INSERT INTO points (formation, seance, equipe, participant_id, valeur, motif, source)
                         VALUES (?, ?, ?, ?, ?, ?, ?)');
    $st->execute([formation_slug(), aujourdhui_local(), $equipe, $participant_id,
                  max(-1000, min(1000, $valeur)), $motif !== '' ? mb_substr($motif, 0, 120) : null, $source]);
}

// Les points d'un exercice ont-ils déjà été donnés à cette équipe ?
function points_source_donnee(string $source, string $equipe): bool
{
    $st = db()->prepare('SELECT 1 FROM points WHERE formation = ? AND seance = ? AND source = ? AND equipe = ? LIMIT 1');
    $st->execute([formation_slug(), aujourdhui_local(), $source, $equipe]);
    return (bool)$st->fetchColumn();
}

// Donne ou reprend les points d'un exercice à une équipe (bascule)
function points_source_basculer(string $source, string $equipe, int $valeur, string $motif): void
{
    if (points_source_donnee($source, $equipe)) {
        $st = db()->prepare('DELETE FROM points WHERE formation = ? AND seance = ? AND source = ? AND equipe = ?');
        $st->execute([formation_slug(), aujourdhui_local(), $source, $equipe]);
    } else {
        points_ajouter($equipe, null, $valeur, $motif, $source);
    }
}

// Points d'un exercice déjà attribués à un joueur (null : rien d'attribué)
function points_joueur_source(int $participant_id, string $source): ?int
{
    $st = db()->prepare('SELECT SUM(valeur) FROM points WHERE formation = ? AND seance = ? AND source = ? AND participant_id = ?');
    $st->execute([formation_slug(), aujourdhui_local(), $source, $participant_id]);
    $v = $st->fetchColumn();
    return $v === null ? null : (int)$v;
}

// Fixe les points d'un exercice pour un joueur : remplace ce qui était attribué
// (0 compte comme « noté, sans point » ; null efface la note)
function points_joueur_fixer(int $participant_id, string $source, ?int $valeur, string $motif): void
{
    $st = db()->prepare('DELETE FROM points WHERE formation = ? AND seance = ? AND source = ? AND participant_id = ?');
    $st->execute([formation_slug(), aujourdhui_local(), $source, $participant_id]);
    if ($valeur === null || !in_array($participant_id, array_map('intval', array_column(participants_seance(), 'id')), true)) {
        return;
    }
    $st = db()->prepare('INSERT INTO points (formation, seance, equipe, participant_id, valeur, motif, source)
                         VALUES (?, ?, NULL, ?, ?, ?, ?)');
    $st->execute([formation_slug(), aujourdhui_local(), $participant_id, $valeur, mb_substr($motif, 0, 120), $source]);
}

// Total de chaque équipe déclarée (0 si rien), du meilleur au moins bon
function points_equipes(): array
{
    $totaux = array_fill_keys(array_keys(equipes()), 0);
    $st = db()->prepare('SELECT equipe, SUM(valeur) total FROM points
                         WHERE formation = ? AND seance = ? AND equipe IS NOT NULL GROUP BY equipe');
    $st->execute([formation_slug(), aujourdhui_local()]);
    foreach ($st as $r) {
        if (isset($totaux[$r['equipe']])) {
            $totaux[$r['equipe']] = (int)$r['total'];
        }
    }
    arsort($totaux);
    return $totaux;
}

// Tous les participants de la séance avec leur total, du meilleur au moins bon
function points_joueurs(): array
{
    $st = db()->prepare('SELECT participant_id, SUM(valeur) total FROM points
                         WHERE formation = ? AND seance = ? AND participant_id IS NOT NULL GROUP BY participant_id');
    $st->execute([formation_slug(), aujourdhui_local()]);
    $totaux = [];
    foreach ($st as $r) {
        $totaux[(int)$r['participant_id']] = (int)$r['total'];
    }
    $joueurs = array_map(fn($p) => $p + ['total' => $totaux[(int)$p['id']] ?? 0], participants_seance());
    usort($joueurs, fn($a, $b) => [$b['total'], $a['prenom']] <=> [$a['total'], $b['prenom']]);
    return $joueurs;
}

// Score d'un joueur et de son équipe, pour son accueil
function points_de(array $participant): array
{
    $st = db()->prepare('SELECT COALESCE(SUM(valeur), 0) FROM points WHERE participant_id = ?');
    $st->execute([$participant['id']]);
    $joueur = (int)$st->fetchColumn();
    $equipe = null;
    if (equipe_existe($participant['equipe'])) {
        $equipe = points_equipes()[$participant['equipe']] ?? 0;
    }
    return ['joueur' => $joueur, 'equipe' => $equipe];
}

// Rang avec ex-aequo : 1, 2, 2, 4…
function points_rangs(array $totaux): array
{
    $rangs = [];
    $precedent = null;
    $rang = 0;
    foreach (array_values($totaux) as $i => $t) {
        if ($t !== $precedent) {
            $rang = $i + 1;
            $precedent = $t;
        }
        $rangs[] = $rang;
    }
    return $rangs;
}
