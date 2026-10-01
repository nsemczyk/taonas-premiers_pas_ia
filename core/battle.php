<?php
// Prompt Battle : par manche, deux équipes s'affrontent (un volontaire
// chacune) sur une tâche tirée au sort. 5 minutes chrono, copies projetées
// anonymement, vote du groupe (sauf les deux volontaires), 20 points à
// l'équipe gagnante (ex-aequo : à chacune).
//
// Une manche passe par quatre états : preparation → jeu → vote → revele.
// Le passage jeu → vote se fait de lui-même à la fin du chrono, à la première
// requête qui le constate (battle_courante).
//
// Les tâches sont déclarées dans formations/<slug>/formation.php, clé
// 'battle_taches' ; celles déjà jouées dans la séance ne sont plus tirées.
//
// Aucune sortie : ce fichier ne définit que des helpers.
require_once __DIR__ . '/points.php';

const BATTLE_DUREE      = 300;    // secondes de jeu
const BATTLE_GRACE      = 3;      // tolérance réseau pour le dernier enregistrement
const BATTLE_POINTS     = 20;
const BATTLE_MAX_PROMPT = 2000;
const BATTLE_MAX_RESULT = 10000;

function battle_taches(): array
{
    return array_values(formation()['battle_taches'] ?? []);
}

function battle_table_ok(): bool
{
    try {
        db()->query('SELECT 1 FROM battle_manches LIMIT 1');
        return true;
    } catch (PDOException $e) {
        return false;
    }
}

function battle_seance(): array
{
    return [formation_slug(), aujourdhui_local()];
}

// La manche en cours (la dernière de la séance), ou null. Fait passer au vote
// une manche dont le chrono est écoulé.
function battle_courante(): ?array
{
    $st = db()->prepare('SELECT * FROM battle_manches WHERE formation = ? AND seance = ? ORDER BY numero DESC LIMIT 1');
    $st->execute(battle_seance());
    $m = $st->fetch();
    if (!$m) {
        return null;
    }
    if ($m['etat'] === 'jeu' && time() > (int)$m['fin_jeu'] + BATTLE_GRACE) {
        battle_ouvrir_vote((int)$m['id']);
        return battle_courante();
    }
    return $m;
}

// Fin du chrono : les copies non vides reçoivent une lettre au hasard, le vote s'ouvre.
// La mise à jour conditionnelle garantit qu'un seul passage attribue les lettres.
function battle_ouvrir_vote(int $id): void
{
    $st = db()->prepare("UPDATE battle_manches SET etat = 'vote' WHERE id = ? AND etat = 'jeu'");
    $st->execute([$id]);
    if ($st->rowCount() !== 1) {
        return;
    }
    $copies = array_filter(battle_copies($id), fn($c) => trim($c['resultat']) !== '');
    $ids = array_column($copies, 'id');
    shuffle($ids);
    $maj = db()->prepare('UPDATE battle_copies SET lettre = ? WHERE id = ?');
    foreach ($ids as $i => $cid) {
        $maj->execute([chr(65 + $i), $cid]);
    }
}

function battle_copies(int $manche_id): array
{
    $st = db()->prepare('SELECT * FROM battle_copies WHERE manche_id = ? ORDER BY lettre IS NULL, lettre, equipe');
    $st->execute([$manche_id]);
    return $st->fetchAll();
}

// Copies en lice (avec une lettre), dans l'ordre A, B…
function battle_copies_en_lice(int $manche_id): array
{
    return array_values(array_filter(battle_copies($manche_id), fn($c) => $c['lettre'] !== null));
}

// Tâches déjà jouées dans la séance (manches lancées)
function battle_taches_jouees(): array
{
    $st = db()->prepare("SELECT tache FROM battle_manches WHERE formation = ? AND seance = ? AND etat <> 'preparation' AND tache IS NOT NULL");
    $st->execute(battle_seance());
    return $st->fetchAll(PDO::FETCH_COLUMN);
}

function battle_taches_restantes(): array
{
    return array_values(array_diff(battle_taches(), battle_taches_jouees()));
}

function battle_nouvelle(): void
{
    $m = battle_courante();
    if ($m && $m['etat'] !== 'revele') {
        return;   // une manche à la fois
    }
    $st = db()->prepare('INSERT INTO battle_manches (formation, seance, numero) VALUES (?, ?, ?)');
    $st->execute([...battle_seance(), $m ? (int)$m['numero'] + 1 : 1]);
}

// Tire une tâche parmi celles pas encore jouées (une autre que l'actuelle si possible)
function battle_tirer(int $id): void
{
    $m = battle_courante();
    if (!$m || (int)$m['id'] !== $id || $m['etat'] !== 'preparation') {
        return;
    }
    $choix = battle_taches_restantes() ?: battle_taches();
    if (count($choix) > 1) {
        $choix = array_values(array_diff($choix, [$m['tache']]));
    }
    if (!$choix) {
        return;
    }
    $st = db()->prepare('UPDATE battle_manches SET tache = ?, tirage_at = ? WHERE id = ?');
    $st->execute([$choix[array_rand($choix)], time(), $id]);
}

// Équipes en duel et leurs volontaires (chaque volontaire doit être membre de son équipe)
function battle_regler(int $id, ?string $eqA, ?string $eqB, int $jA, int $jB): void
{
    $m = battle_courante();
    if (!$m || (int)$m['id'] !== $id || $m['etat'] !== 'preparation') {
        return;
    }
    $eqA = equipe_existe($eqA) ? $eqA : null;
    $eqB = equipe_existe($eqB) && $eqB !== $eqA ? $eqB : null;
    $membre = function (?string $eq, int $pid): ?int {
        foreach (participants_seance() as $p) {
            if ((int)$p['id'] === $pid && $eq !== null && $p['equipe'] === $eq) {
                return $pid;
            }
        }
        return null;
    };
    $st = db()->prepare('UPDATE battle_manches SET equipe_a = ?, equipe_b = ?, joueur_a = ?, joueur_b = ? WHERE id = ?');
    $st->execute([$eqA, $eqB, $membre($eqA, $jA), $membre($eqB, $jB), $id]);
}

function battle_prete(array $m): bool
{
    return $m['tache'] && $m['equipe_a'] && $m['equipe_b'] && $m['joueur_a'] && $m['joueur_b'];
}

function battle_demarrer(int $id): void
{
    $m = battle_courante();
    if ($m && (int)$m['id'] === $id && $m['etat'] === 'preparation' && battle_prete($m)) {
        $st = db()->prepare("UPDATE battle_manches SET etat = 'jeu', fin_jeu = ? WHERE id = ?");
        $st->execute([time() + BATTLE_DUREE, $id]);
    }
}

// Rallonge (secondes > 0) ou arrête (null) le chrono d'une manche en jeu
function battle_chrono(int $id, ?int $secondes): void
{
    $m = battle_courante();
    if (!$m || (int)$m['id'] !== $id || $m['etat'] !== 'jeu') {
        return;
    }
    $fin = $secondes === null ? time() - BATTLE_GRACE - 1 : max(time(), (int)$m['fin_jeu']) + $secondes;
    $st = db()->prepare('UPDATE battle_manches SET fin_jeu = ? WHERE id = ?');
    $st->execute([$fin, $id]);
    battle_courante();   // déclenche le passage au vote si le chrono est arrêté
}

// Rôle d'un participant dans la manche : 'a', 'b' (volontaires) ou null
function battle_role(array $m, ?array $p): ?string
{
    if (!$p) {
        return null;
    }
    if ((int)$m['joueur_a'] === (int)$p['id']) {
        return 'a';
    }
    if ((int)$m['joueur_b'] === (int)$p['id']) {
        return 'b';
    }
    return null;
}

// Enregistre la copie d'un volontaire, tant que le chrono tourne
function battle_sauver(array $m, array $p, string $prompt, string $resultat): bool
{
    $role = battle_role($m, $p);
    if (!$role || $m['etat'] !== 'jeu' || time() > (int)$m['fin_jeu'] + BATTLE_GRACE) {
        return false;
    }
    $st = db()->prepare('INSERT INTO battle_copies (manche_id, equipe, participant_id, prenom, prompt, resultat)
                         VALUES (?, ?, ?, ?, ?, ?)
                         ON DUPLICATE KEY UPDATE participant_id = VALUES(participant_id), prenom = VALUES(prenom),
                                                 prompt = VALUES(prompt), resultat = VALUES(resultat)');
    $st->execute([$m['id'], $m['equipe_' . $role], $p['id'], $p['prenom'],
                  mb_substr($prompt, 0, BATTLE_MAX_PROMPT), mb_substr($resultat, 0, BATTLE_MAX_RESULT)]);
    return true;
}

// Ma copie dans la manche (volontaire), ou null
function battle_ma_copie(array $m, array $p): ?array
{
    $role = battle_role($m, $p);
    if (!$role) {
        return null;
    }
    foreach (battle_copies((int)$m['id']) as $c) {
        if ($c['equipe'] === $m['equipe_' . $role]) {
            return $c;
        }
    }
    return null;
}

// Vote (ou changement de vote) d'un participant ; jamais les volontaires de la manche
function battle_voter(array $m, array $p, int $copie_id): bool
{
    if ($m['etat'] !== 'vote' || battle_role($m, $p)) {
        return false;
    }
    if (!in_array($copie_id, array_map('intval', array_column(battle_copies_en_lice((int)$m['id']), 'id')), true)) {
        return false;
    }
    $st = db()->prepare('REPLACE INTO battle_votes (manche_id, participant_id, copie_id) VALUES (?, ?, ?)');
    $st->execute([$m['id'], $p['id'], $copie_id]);
    return true;
}

function battle_mon_vote(array $m, array $p): ?int
{
    $st = db()->prepare('SELECT copie_id FROM battle_votes WHERE manche_id = ? AND participant_id = ?');
    $st->execute([$m['id'], $p['id']]);
    $v = $st->fetchColumn();
    return $v === false ? null : (int)$v;
}

// Voix par copie (id => nombre), copies en lice comprises à 0
function battle_decompte(int $manche_id): array
{
    $voix = array_fill_keys(array_map('intval', array_column(battle_copies_en_lice($manche_id), 'id')), 0);
    $st = db()->prepare('SELECT copie_id, COUNT(*) n FROM battle_votes WHERE manche_id = ? GROUP BY copie_id');
    $st->execute([$manche_id]);
    foreach ($st as $r) {
        if (isset($voix[(int)$r['copie_id']])) {
            $voix[(int)$r['copie_id']] = (int)$r['n'];
        }
    }
    return $voix;
}

// Équipes gagnantes : le plus de voix, ex-aequo compris ; aucune s'il n'y a eu aucun vote
function battle_gagnants(int $manche_id): array
{
    $voix = battle_decompte($manche_id);
    if (!$voix || max($voix) === 0) {
        return [];
    }
    $equipes = array_column(battle_copies($manche_id), 'equipe', 'id');
    return array_values(array_map(fn($cid) => $equipes[$cid], array_keys($voix, max($voix))));
}

function battle_source(int $id): string
{
    return 'battle-' . $id;
}

// Clôture le vote : révélation, et 20 points à chaque équipe gagnante
function battle_reveler(int $id): void
{
    $st = db()->prepare("UPDATE battle_manches SET etat = 'revele' WHERE id = ? AND etat = 'vote'");
    $st->execute([$id]);
    if ($st->rowCount() !== 1 || !points_actifs() || !points_table_ok()) {
        return;
    }
    $m = battle_courante();
    foreach (battle_gagnants($id) as $eq) {
        if (!points_source_donnee(battle_source($id), $eq)) {
            points_ajouter($eq, null, BATTLE_POINTS, 'Prompt Battle — manche ' . $m['numero'], battle_source($id));
        }
    }
}

// Abandon d'une manche : elle disparaît, ses éventuels points aussi
function battle_abandonner(int $id): void
{
    $m = battle_courante();
    if (!$m || (int)$m['id'] !== $id) {
        return;
    }
    if (points_table_ok()) {
        $st = db()->prepare('DELETE FROM points WHERE formation = ? AND seance = ? AND source = ?');
        $st->execute([...battle_seance(), battle_source($id)]);
    }
    $st = db()->prepare('DELETE FROM battle_manches WHERE id = ?');
    $st->execute([$id]);
}

// Secondes restantes au chrono (0 si écoulé)
function battle_reste(array $m): int
{
    return $m['etat'] === 'jeu' ? max(0, (int)$m['fin_jeu'] - time()) : 0;
}

function battle_participant(?int $id): ?array
{
    foreach (participants_seance() as $p) {
        if ((int)$p['id'] === (int)$id) {
            return $p;
        }
    }
    return null;
}

// Nettoie une saisie (retours à la ligne conservés)
function battle_texte_clean(?string $texte, int $max): string
{
    return mb_substr(str_replace("\r\n", "\n", trim((string)$texte)), 0, $max);
}
