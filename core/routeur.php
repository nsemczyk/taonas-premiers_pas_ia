<?php
// Routeur du serveur intégré de PHP, pour travailler en local sans Apache :
//
//   php -S localhost:8000 core/routeur.php      (depuis la racine du site)
//
// Il reproduit le .htaccess, qu'il ignore : adresses /n2/…, pages des
// exercices servies à la racine, fichiers internes refusés. Il trouve seul
// le dossier d'un exercice (exercices/*/page.php) : rien à déclarer ici.
// Inutile en production : Apache s'appuie sur le .htaccess (ce dossier lui est fermé).

$racine = dirname(__DIR__);
$chemin = rawurldecode(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/');

function refuser(int $code): bool
{
    http_response_code($code);
    echo $code === 404 ? 'Page introuvable.' : 'Accès interdit.';
    return true;
}

if (str_contains($chemin, '..')) {
    return refuser(404);
}
// Fichiers internes : jamais servis
if (preg_match('#^/(config\.php|core/|exercices/|formations/.*\.php$|\.)#', $chemin)) {
    return refuser(preg_match('#^/exercices/#', $chemin) ? 404 : 403);
}

// /n2 → /n2/ ; /n2/page → page, formation mémorisée comme le fait le .htaccess
$formation = '';
if (preg_match('#^/([a-z0-9-]+)$#', $chemin, $m) && !file_exists($racine . $chemin)) {
    header('Location: ' . $chemin . '/' . (($q = $_SERVER['QUERY_STRING'] ?? '') !== '' ? '?' . $q : ''), true, 301);
    return true;
}
if (preg_match('#^/([a-z0-9-]+)(/.*)$#', $chemin, $m) && !file_exists($racine . '/' . $m[1])) {
    [$formation, $chemin] = [$m[1], $m[2]];
}
$_SERVER['URL_PROPRES'] = '1';
$_SERVER['FORMATION_URL'] = $formation;

if (str_ends_with($chemin, '/')) {
    $chemin .= 'index.php';
}

// Page : à la racine, sinon dans le dossier d'un exercice
if (str_ends_with($chemin, '.php')) {
    $script = is_file($racine . $chemin) ? $chemin : null;
    if ($script === null && preg_match('#^/[a-z0-9-]+\.php$#', $chemin)) {
        $trouve = glob($racine . '/exercices/*' . $chemin);
        $script = $trouve ? substr($trouve[0], strlen($racine)) : null;
    }
    if ($script === null) {
        return refuser(404);
    }
    $_SERVER['SCRIPT_NAME'] = $_SERVER['PHP_SELF'] = $script;
    $_SERVER['SCRIPT_FILENAME'] = $racine . $script;
    chdir(dirname($racine . $script));
    $_page = $racine . $script;
    unset($racine, $chemin, $formation, $script, $trouve, $m);   // la page démarre sans les variables du routeur
    require $_page;
    return true;
}

// Fichier statique : servi tel quel à la racine, à la main sous /n2/
if (!is_file($racine . $chemin)) {
    return refuser(404);
}
if ($formation === '') {
    return false;
}
$types = ['css' => 'text/css', 'js' => 'text/javascript', 'png' => 'image/png', 'jpg' => 'image/jpeg',
          'svg' => 'image/svg+xml', 'ico' => 'image/x-icon', 'woff2' => 'font/woff2', 'woff' => 'font/woff',
          'pdf' => 'application/pdf', 'txt' => 'text/plain; charset=utf-8'];
header('Content-Type: ' . ($types[strtolower(pathinfo($chemin, PATHINFO_EXTENSION))] ?? 'application/octet-stream'));
readfile($racine . $chemin);
return true;
