<?php
// Formations : chaque dossier formations/<slug>/ décrit une formation (titres,
// contenus de l'accueil, réglages). Le code commun, à la racine et dans core/,
// ne connaît que la formation courante, lue ici.
//
// La formation courante vient de l'adresse :
//   - /n2/equipes.php : dossier virtuel, réécrit par .htaccess (mod_rewrite) ;
//   - /equipes.php?f=n2 : repli, toujours valable, sans réécriture ;
//   - ni l'un ni l'autre : FORMATION_DEFAUT ('n1', réglable dans config.php).
// Les URL de la formation par défaut restent celles d'avant le passage au
// multi-formations, anciens liens et favoris compris.
//
// Les liens internes sont relatifs : sous /n2/, « quiz.php » reste dans /n2/.
// Seuls les liens qui changent de formation (onglets, mail) sont absolus.
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

// Variable posée par .htaccess. Chaque réécriture interne la préfixe d'un
// REDIRECT_ de plus : on accepte tous les niveaux.
function variable_reecriture(string $nom): string
{
    foreach ($_SERVER as $cle => $valeur) {
        if (is_string($cle) && preg_match('/^(REDIRECT_)*' . $nom . '$/', $cle)) {
            return (string)$valeur;
        }
    }
    return '';
}

// Les adresses /<formation>/ sont-elles servies (mod_rewrite actif) ?
function urls_propres(): bool
{
    return variable_reecriture('URL_PROPRES') !== '';
}

// Formation lue dans le chemin (/n2/…), '' pour une page à la racine
function formation_url(): string
{
    return variable_reecriture('FORMATION_URL');
}

// Chemin du site, sans barre finale ('' à la racine du domaine, '/formation' dans un sous-dossier)
function base_url(): string
{
    // Une page d'exercice tourne dans exercices/<nom>/, mais elle est servie à la racine
    $dossier = preg_replace('#/exercices/[^/]+$#', '', str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')));
    return rtrim($dossier, '/');
}

// Formation de la requête. Une formation inconnue s'arrête net plutôt que de
// retomber sans bruit sur une autre : on n'enregistre rien au mauvais endroit.
function formation_slug(): string
{
    static $slug = null;
    if ($slug === null) {
        $f = formation_url() ?: ($_GET['f'] ?? '');
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

// Ajoute ?f=<slug> à une URL, sauf pour la formation par défaut (mode sans réécriture)
function ajouter_f(string $url, string $slug): string
{
    if ($slug === FORMATION_DEFAUT) {
        return $url;
    }
    return $url . (str_contains($url, '?') ? '&' : '?') . 'f=' . rawurlencode($slug);
}

// Adresse absolue d'une page ('equipes.php?cle=…') dans une formation donnée,
// depuis n'importe où : /n2/equipes.php si la réécriture est active,
// /equipes.php?f=n2 sinon. $base : préfixe du site (SITE_URL pour un mail).
// URL brute : l'échapper avec e() dans un attribut HTML, json_encode() en JavaScript.
function url_formation(string $page, string $slug, ?string $base = null): string
{
    $base ??= base_url();
    if (urls_propres()) {
        return $base . '/' . ($slug === FORMATION_DEFAUT ? '' : rawurlencode($slug) . '/') . $page;
    }
    return ajouter_f($base . '/' . $page, $slug);
}

// Lien vers une page de la formation courante : tout lien interne passe par là.
// Sous /n2/, un lien relatif y reste de lui-même ; à la racine, on ajoute ?f=.
function avec_f(string $url): string
{
    return formation_url() !== '' ? $url : ajouter_f($url, formation_slug());
}

// Suffixe « &f=<slug> » pour les URL composées en JavaScript ('' si inutile)
function param_f(): string
{
    if (formation_url() !== '' || formation_slug() === FORMATION_DEFAUT) {
        return '';
    }
    return '&f=' . rawurlencode(formation_slug());
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
