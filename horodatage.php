<?php
// Le serveur stocke les horodatages dans son propre fuseau — souvent UTC —
// alors que l'animateur raisonne en heure locale. Rien n'est modifié en base :
// la conversion se fait uniquement à la lecture et à l'affichage.
//
// Pour un serveur ou une formation dans un autre fuseau, ajoutez dans config.php :
//   define('FUSEAU_SERVEUR', 'UTC');
//   define('FUSEAU_AFFICHAGE', 'Europe/Paris');
require_once __DIR__ . '/config.php';

if (!defined('FUSEAU_SERVEUR')) {
    define('FUSEAU_SERVEUR', 'UTC');
}
if (!defined('FUSEAU_AFFICHAGE')) {
    define('FUSEAU_AFFICHAGE', 'Europe/Paris');
}

function fuseau(string $nom): DateTimeZone
{
    static $cache = [];
    return $cache[$nom] ??= new DateTimeZone($nom);
}

// Horodatage SQL (fuseau serveur) converti dans le fuseau d'affichage
function moment_local(string $horodatage_sql): DateTimeImmutable
{
    return (new DateTimeImmutable($horodatage_sql, fuseau(FUSEAU_SERVEUR)))
        ->setTimezone(fuseau(FUSEAU_AFFICHAGE));
}

function heure_locale(string $horodatage_sql): string
{
    return moment_local($horodatage_sql)->format('H:i');
}

// Journée à laquelle l'enregistrement appartient, vu de l'heure locale
function jour_local(string $horodatage_sql): string
{
    return moment_local($horodatage_sql)->format('Y-m-d');
}

function aujourdhui_local(): string
{
    return (new DateTimeImmutable('now', fuseau(FUSEAU_AFFICHAGE)))->format('Y-m-d');
}

/**
 * Bornes d'une journée locale, exprimées dans le fuseau du serveur.
 * Permet de filtrer sur `created_at >= debut AND created_at < fin` :
 * exact au changement d'heure, et l'index reste utilisable.
 */
function bornes_journee(string $jour_local): array
{
    $debut = new DateTimeImmutable($jour_local . ' 00:00:00', fuseau(FUSEAU_AFFICHAGE));
    $tz    = fuseau(FUSEAU_SERVEUR);
    return [
        $debut->setTimezone($tz)->format('Y-m-d H:i:s'),
        $debut->modify('+1 day')->setTimezone($tz)->format('Y-m-d H:i:s'),
    ];
}
