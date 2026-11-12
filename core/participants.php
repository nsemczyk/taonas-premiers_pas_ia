<?php
// Participants de la séance et équipes.
//
// Séance = formation courante + journée locale. Un téléphone est relié à son
// participant par un cookie (jeton aléatoire) : pas de compte, pas de mot de
// passe, et le lien tombe de lui-même le lendemain.
//
// Les équipes sont déclarées dans formations/<slug>/formation.php :
//   'equipes'    => ['cle' => ['nom' => 'Équipe …', 'couleur' => '#…'], …],
//   'equipe_max' => 5,
// Une formation sans 'equipes' n'a pas d'équipes.
//
// Aucune sortie : ce fichier ne définit que des helpers.
require_once __DIR__ . '/horodatage.php';

const COOKIE_PARTICIPANT = 'participant';

function equipes(): array
{
    return formation()['equipes'] ?? [];
}

function equipe_max(): int
{
    return (int)(formation()['equipe_max'] ?? 5);
}

function equipe_existe(?string $cle): bool
{
    return $cle !== null && isset(equipes()[$cle]);
}

// Tous les participants de la séance, par ordre d'arrivée.
// [] si la table n'existe pas encore (exercices/equipes/migration.sql pas jouée).
function participants_seance(): array
{
    try {
        $st = db()->prepare('SELECT id, prenom, equipe, created_at FROM participants
                             WHERE formation = ? AND seance = ? ORDER BY id');
        $st->execute([formation_slug(), aujourdhui_local()]);
        return $st->fetchAll();
    } catch (PDOException $e) {
        return [];
    }
}

function participants_table_ok(): bool
{
    try {
        db()->query('SELECT 1 FROM participants LIMIT 1');
        return true;
    } catch (PDOException $e) {
        return false;
    }
}

// Le participant de ce téléphone pour la séance en cours, ou null
function participant_courant(): ?array
{
    static $p = false;
    if ($p === false) {
        $p = null;
        $jeton = $_COOKIE[COOKIE_PARTICIPANT] ?? '';
        if (is_string($jeton) && preg_match('/^[a-f0-9]{32}$/', $jeton)) {
            try {
                $st = db()->prepare('SELECT id, prenom, equipe FROM participants
                                     WHERE jeton = ? AND formation = ? AND seance = ?');
                $st->execute([$jeton, formation_slug(), aujourdhui_local()]);
                $p = $st->fetch() ?: null;
            } catch (PDOException $e) {
                $p = null;
            }
        }
    }
    return $p;
}

// Cookie de séance : il suffit qu'il tienne la journée
function cookie_participant(string $jeton, int $duree): void
{
    setcookie(COOKIE_PARTICIPANT, $jeton, [
        'expires'  => time() + $duree,
        'path'     => '/',
        'secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

// Étiquette d'équipe colorée, pour l'accueil comme pour l'animateur
function badge_equipe(string $cle): string
{
    $eq = equipes()[$cle] ?? null;
    if (!$eq) {
        return '';
    }
    return '<span class="equipe-badge" style="--equipe:' . e($eq['couleur'] ?? '#1B3E90') . '">'
         . e($eq['nom']) . '</span>';
}
