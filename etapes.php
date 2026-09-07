<?php
// Étapes de la journée : le contenu de l'accueil s'ouvre depuis pilotage.php.
// Ce fichier ne produit aucune sortie, il ne définit que des helpers.
require_once __DIR__ . '/config.php';

// Toutes les étapes, dans l'ordre du déroulé (volumes minuscules : une requête suffit).
// null si la table n'existe pas encore : migration-etapes.sql n'a pas été jouée.
function etapes_toutes(): ?array
{
    static $etapes = false;
    if ($etapes === false) {
        try {
            $etapes = db()->query('SELECT cle, titre, ouverte FROM etapes ORDER BY ordre')->fetchAll();
        } catch (PDOException $e) {
            $etapes = null;
        }
    }
    return $etapes;
}

function etapes_disponibles(): bool
{
    return etapes_toutes() !== null;
}

function etape_ouverte(string $cle): bool
{
    // Sans la table, on retrouve le comportement d'avant l'ouverture progressive :
    // tout est visible. Mieux vaut un accueil complet qu'un accueil vide ou en erreur.
    if (!etapes_disponibles()) {
        return true;
    }
    foreach (etapes_toutes() as $e) {
        if ($e['cle'] === $cle) {
            return (bool)$e['ouverte'];
        }
    }
    return false; // étape inconnue : fermée par défaut, jamais dévoilée par accident
}

// Clés ouvertes, pour le suivi côté navigateur
function etapes_ouvertes(): array
{
    $out = [];
    foreach (etapes_toutes() ?? [] as $e) {
        if ($e['ouverte']) {
            $out[] = $e['cle'];
        }
    }
    return $out;
}

function etape_titre(string $cle): string
{
    foreach (etapes_toutes() ?? [] as $e) {
        if ($e['cle'] === $cle) {
            return $e['titre'];
        }
    }
    return '';
}

// Carte affichée à la place d'une section encore fermée : le titre, jamais le contenu
function carte_verrouillee(string $cle): string
{
    return '<section class="card card-locked">'
         . '<h2><span class="lock" aria-hidden="true">🔒</span> ' . e(etape_titre($cle)) . '</h2>'
         . '<p class="lead">Cette étape sera ouverte par le formateur.</p>'
         . '</section>';
}
