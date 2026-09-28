<?php
// Formations : chaque dossier formations/<slug>/ décrit une formation (titres,
// contenus de l'accueil, réglages). Le code commun, à la racine et dans core/,
// ne connaît que la formation courante, lue ici.
//
// La formation courante vient du paramètre ?f=<slug> ; sans paramètre, c'est
// FORMATION_DEFAUT ('n1' par défaut, réglable dans config.php). Les URL de la
// formation par défaut ne portent jamais de ?f= : ce sont celles d'avant le
// passage au multi-formations, anciens liens et favoris compris.
//
// Aucune sortie : ce fichier ne définit que des helpers.
require_once __DIR__ . '/../config.php';

if (!defined('FORMATION_DEFAUT')) {
    define('FORMATION_DEFAUT', 'n1');
}

const DOSSIER_FORMATIONS = __DIR__ . '/../formations';

// Un slug est un nom de dossier : minuscules, chiffres, tirets.
function formation_existe(string $slug): bool
{
    return preg_match('/^[a-z0-9-]{1,32}$/', $slug)
        && is_file(DOSSIER_FORMATIONS . '/' . $slug . '/formation.php');
}

// Slugs des formations installées. Un dossier préfixé par _ (modèle, brouillon)
// n'est pas une formation.
function formations_disponibles(): array
{
    static $slugs = null;
    if ($slugs === null) {
        $slugs = [];
        foreach (glob(DOSSIER_FORMATIONS . '/*/formation.php') ?: [] as $f) {
            $slug = basename(dirname($f));
            if (formation_existe($slug)) {
                $slugs[] = $slug;
            }
        }
        sort($slugs);
    }
    return $slugs;
}

// Formation de la requête. Un ?f= inconnu s'arrête net plutôt que de retomber
// sans bruit sur une autre formation : on n'enregistre rien au mauvais endroit.
function formation_slug(): string
{
    static $slug = null;
    if ($slug === null) {
        $f = $_GET['f'] ?? '';
        if (!is_string($f) || $f === '') {
            $slug = FORMATION_DEFAUT;
        } elseif (formation_existe($f)) {
            $slug = $f;
        } else {
            http_response_code(404);
            exit('Formation inconnue.');
        }
    }
    return $slug;
}

// Réglages d'une formation (formations/<slug>/formation.php), la courante par
// défaut. Un slug disparu depuis (lien ancien) retombe sur la formation par défaut.
function formation(?string $slug = null): array
{
    static $cache = [];
    $slug ??= formation_slug();
    if (!formation_existe($slug)) {
        $slug = FORMATION_DEFAUT;
    }
    return $cache[$slug] ??= ['slug' => $slug] + require DOSSIER_FORMATIONS . '/' . $slug . '/formation.php';
}

// Chemin d'un fichier propre à la formation courante
function formation_chemin(string $fichier): string
{
    return DOSSIER_FORMATIONS . '/' . formation_slug() . '/' . $fichier;
}

// Ajoute f=<slug> à une URL, sauf pour la formation par défaut. URL brute :
// l'échapper avec e() dans un attribut HTML, json_encode() dans du JavaScript.
function url_formation(string $url, string $slug): string
{
    if ($slug === FORMATION_DEFAUT) {
        return $url;
    }
    return $url . (str_contains($url, '?') ? '&' : '?') . 'f=' . rawurlencode($slug);
}

// Même chose pour la formation courante : tout lien interne passe par là.
function avec_f(string $url): string
{
    return url_formation($url, formation_slug());
}

// Suffixe « &f=<slug> » pour les URL composées en JavaScript ('' par défaut)
function param_f(): string
{
    return formation_slug() === FORMATION_DEFAUT ? '' : '&f=' . rawurlencode(formation_slug());
}

// Onglets de choix de la formation, pour les pages de l'animateur. Rien tant
// qu'une seule formation est installée. $url : la page, sans f=.
function selecteur_formation(string $url): string
{
    $slugs = formations_disponibles();
    if (count($slugs) < 2) {
        return '';
    }
    $h = '<nav class="formations" aria-label="Formation">';
    foreach ($slugs as $slug) {
        $f = formation($slug);
        $h .= '<a href="' . e(url_formation($url, $slug)) . '"'
            . ($slug === formation_slug() ? ' class="active" aria-current="page"' : '') . '>'
            . e($f['titre_court'] ?? $f['titre']) . '</a>';
    }
    return $h . '</nav>';
}
