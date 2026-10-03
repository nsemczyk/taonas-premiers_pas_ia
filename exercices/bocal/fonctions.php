<?php
// Le bocal à secrets : un faux mail où chaque stagiaire surligne ce qui ne doit
// pas partir chez une IA, puis réécrit la demande sous une forme anonymisée.
//
// Le texte et son corrigé sont dans formations/<slug>/formation.php, clé
// 'bocal' : chaque donnée à surligner y est balisée [[texte|catégorie]]. Le
// texte est découpé en mots, numérotés dans l'ordre ; une copie retient les
// numéros des mots surlignés.
//
// Aucune sortie : ce fichier ne définit que des helpers.
require_once __DIR__ . '/../../core/etapes.php';
require_once __DIR__ . '/../../core/participants.php';
require_once __DIR__ . '/../../core/points.php';

const BOCAL_POINTS_TOUT   = 20;     // toutes les données surlignées
const BOCAL_POINTS_MOITIE = 10;     // plus de la moitié
const BOCAL_POINTS_PROMPT = 10;     // prompt anonymisé qui garde le sens (jugé par le formateur)
const BOCAL_MAX_PROMPT    = 4000;

function bocal_config(): ?array
{
    return formation()['bocal'] ?? null;
}

function bocal_table_ok(): bool
{
    try {
        db()->query('SELECT 1 FROM bocal_copies LIMIT 1');
        return true;
    } catch (PDOException $e) {
        return false;
    }
}

// Le texte découpé : jetons (mots surlignables ou séparateurs) et données attendues.
//   jetons   : [['t' => texte, 'i' => n° de mot ou null, 's' => n° de donnée ou null], …]
//   donnees  : [['cat' => clé, 'texte' => …, 'mots' => [n°…]], …]
function bocal_decoupe(): array
{
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }
    $jetons = [];
    $donnees = [];
    $n = 0;
    $morceaux = preg_split('/\[\[(.+?)\|(\w+)\]\]/us', (string)(bocal_config()['texte'] ?? ''), -1, PREG_SPLIT_DELIM_CAPTURE);
    // Morceaux : texte libre, puis par balise (texte, catégorie), texte libre…
    for ($k = 0; $k < count($morceaux); $k++) {
        $s = null;
        if ($k % 3 === 1) {
            $s = count($donnees);
            $donnees[] = ['cat' => $morceaux[$k + 1], 'texte' => $morceaux[$k], 'mots' => []];
        } elseif ($k % 3 === 2) {
            continue;
        }
        foreach (preg_split('/([\s()<>,;:«»"]+)/u', $morceaux[$k], -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY) as $p) {
            $point = '';
            if (preg_match('/^[^\s()<>,;:«»"]/u', $p) && mb_strlen($p) > 1 && str_ends_with($p, '.')) {
                [$p, $point] = [mb_substr($p, 0, -1), '.'];   // point final : pas dans le mot
            }
            $mot = preg_match('/^[^\s()<>,;:«»"]/u', $p) && !preg_match('/^[\/–—-]+$/u', $p);
            $jetons[] = ['t' => $p, 'i' => $mot ? $n : null, 's' => $mot ? $s : null];
            if ($mot) {
                if ($s !== null) {
                    $donnees[$s]['mots'][] = $n;
                }
                $n++;
            }
            if ($point !== '') {
                $jetons[] = ['t' => $point, 'i' => null, 's' => null];
            }
        }
    }
    return $cache = ['jetons' => $jetons, 'donnees' => $donnees, 'nb_mots' => $n];
}

// Numéros de mots valides, sans doublon, dans l'ordre
function bocal_nettoyer(array $surlignes): array
{
    $nb = bocal_decoupe()['nb_mots'];
    $ok = array_unique(array_filter(array_map('intval', $surlignes), fn($i) => $i >= 0 && $i < $nb));
    sort($ok);
    return $ok;
}

// Correction automatique d'une copie. Une donnée est trouvée quand au moins la
// moitié de ses mots est surlignée ; un mot surligné hors données est un faux positif.
function bocal_analyse(array $surlignes): array
{
    $d = bocal_decoupe();
    $sur = array_flip($surlignes);
    $oublies = [];
    $trouves = 0;
    foreach ($d['donnees'] as $donnee) {
        $nb = count(array_filter($donnee['mots'], fn($i) => isset($sur[$i])));
        if ($donnee['mots'] && 2 * $nb >= count($donnee['mots'])) {
            $trouves++;
        } else {
            $oublies[] = $donnee;
        }
    }
    $faux = [];
    foreach ($d['jetons'] as $j) {
        if ($j['i'] !== null && $j['s'] === null && isset($sur[$j['i']])) {
            $faux[] = $j['t'];
        }
    }
    $total = count($d['donnees']);
    $suggestion = $total && $trouves === $total ? BOCAL_POINTS_TOUT
                : (2 * $trouves > $total ? BOCAL_POINTS_MOITIE : 0);
    return ['trouves' => $trouves, 'total' => $total, 'oublies' => $oublies, 'faux' => $faux, 'suggestion' => $suggestion];
}

// Le mail en HTML, mots cliquables. Avec $correction : trouvés, oubliés et faux positifs marqués.
function bocal_html(array $surlignes, bool $correction = false): string
{
    $sur = array_flip($surlignes);
    $h = '';
    foreach (bocal_decoupe()['jetons'] as $j) {
        if ($j['i'] === null) {
            $h .= e($j['t']);
            continue;
        }
        $classes = ['mot'];
        if (isset($sur[$j['i']])) {
            $classes[] = 'surligne';
        }
        if ($correction) {
            $classes[] = $j['s'] !== null ? (isset($sur[$j['i']]) ? 'trouve' : 'oublie') : (isset($sur[$j['i']]) ? 'faux' : '');
        }
        $h .= '<span class="' . trim(implode(' ', $classes)) . '" data-i="' . $j['i'] . '">' . e($j['t']) . '</span>';
    }
    return $h;
}

// Copie d'un participant pour la séance, surlignages décodés
function bocal_copie(int $participant_id): ?array
{
    $st = db()->prepare('SELECT * FROM bocal_copies WHERE formation = ? AND seance = ? AND participant_id = ?');
    $st->execute([formation_slug(), aujourdhui_local(), $participant_id]);
    $c = $st->fetch();
    if (!$c) {
        return null;
    }
    $c['surlignes'] = bocal_nettoyer(json_decode($c['surlignes'], true) ?: []);
    return $c;
}

// Enregistre le brouillon (ou la copie rendue) ; refusé une fois la copie rendue
function bocal_sauver(int $participant_id, array $surlignes, string $prompt, bool $rendre = false): bool
{
    $actuelle = bocal_copie($participant_id);
    if ($actuelle && $actuelle['rendu']) {
        return false;
    }
    $st = db()->prepare('INSERT INTO bocal_copies (formation, seance, participant_id, surlignes, prompt, rendu)
                         VALUES (?, ?, ?, ?, ?, ?)
                         ON DUPLICATE KEY UPDATE surlignes = VALUES(surlignes), prompt = VALUES(prompt), rendu = VALUES(rendu)');
    $st->execute([formation_slug(), aujourdhui_local(), $participant_id,
                  json_encode(bocal_nettoyer($surlignes)), mb_substr(trim($prompt), 0, BOCAL_MAX_PROMPT), $rendre ? 1 : 0]);
    return true;
}

// Rouvre une copie rendue, tant que le formateur ne l'a pas notée
function bocal_reprendre(int $participant_id): void
{
    if (bocal_points($participant_id) === ['surlignage' => null, 'prompt' => null]) {
        $st = db()->prepare('UPDATE bocal_copies SET rendu = 0 WHERE formation = ? AND seance = ? AND participant_id = ?');
        $st->execute([formation_slug(), aujourdhui_local(), $participant_id]);
    }
}

// Toutes les copies de la séance, avec prénom et équipe, rangées par équipe
function bocal_copies(): array
{
    $st = db()->prepare('SELECT c.*, p.prenom, p.equipe FROM bocal_copies c
                         JOIN participants p ON p.id = c.participant_id
                         WHERE c.formation = ? AND c.seance = ?
                         ORDER BY p.equipe IS NULL, p.equipe, p.prenom, c.id');
    $st->execute([formation_slug(), aujourdhui_local()]);
    return array_map(function ($c) {
        $c['surlignes'] = bocal_nettoyer(json_decode($c['surlignes'], true) ?: []);
        return $c;
    }, $st->fetchAll());
}

// Points déjà attribués à un participant pour l'exercice (null : pas encore noté)
function bocal_points(int $participant_id): array
{
    return ['surlignage' => points_joueur_source($participant_id, 'bocal-surlignage'),
            'prompt'     => points_joueur_source($participant_id, 'bocal-prompt')];
}

// Le bocal à secrets : le bouton vers bocal.php, derrière l'étape $cle
function bloc_bocal(string $cle = 'bocal'): string
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
    $copie = bocal_table_ok() ? bocal_copie((int)$p['id']) : null;
    if ($copie && $copie['rendu']) {
        return $h . '    <p class="lead">Copie rendue. Le formateur la teste et la note.</p>' . "\n"
                  . '    <a class="btn btn-ghost" href="' . e(avec_f('bocal.php')) . '">Revoir ma copie</a>' . "\n"
                  . '  </section>';
    }
    return $h . '    <p class="lead">Un mail plein de secrets : repérez ce qui ne doit pas partir chez une IA, puis réécrivez la demande.</p>' . "\n"
              . '    <a class="btn btn-primary" href="' . e(avec_f('bocal.php')) . '">' . ($copie ? 'Reprendre ma copie' : 'Ouvrir le bocal') . '</a>' . "\n"
              . '  </section>';
}

