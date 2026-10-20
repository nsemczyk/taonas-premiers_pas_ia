<?php
// Étapes de la journée : le contenu de l'accueil s'ouvre depuis pilotage.php.
// Chaque formation a ses propres étapes (colonne `formation`).
// Ce fichier ne produit aucune sortie, il ne définit que des helpers.
require_once __DIR__ . '/formation.php';

// Toutes les étapes, dans l'ordre du déroulé (volumes minuscules : une requête suffit).
// null si la table n'existe pas encore : migration-etapes.sql (ou
// migration-formations.sql) n'a pas été jouée.
function etapes_toutes(): ?array
{
    static $etapes = false;
    if ($etapes === false) {
        try {
            $st = db()->prepare('SELECT cle, titre, ouverte FROM etapes WHERE formation = ? ORDER BY ordre');
            $st->execute([formation_slug()]);
            $etapes = $st->fetchAll();
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
    return formation()['etapes'][$cle] ?? '';   // pas encore en base : titre déclaré
}

// Crée en base les étapes déclarées dans formations/<slug>/formation.php
// (clé 'etapes' : cle => titre, dans l'ordre de la journée) et aligne titres et
// ordre sur la déclaration. L'état ouvert/fermé n'est jamais touché, et une
// étape présente en base mais absente de la déclaration est laissée telle quelle.
// Appelée par la télécommande : ajouter une étape = une ligne de configuration.
function etapes_synchroniser(): void
{
    $declarees = formation()['etapes'] ?? [];
    if (!$declarees) {
        return;
    }
    try {
        $st = db()->prepare('INSERT INTO etapes (formation, cle, titre, ordre, ouverte) VALUES (?, ?, ?, ?, 0)
                             ON DUPLICATE KEY UPDATE titre = VALUES(titre), ordre = VALUES(ordre)');
        $ordre = 0;
        foreach ($declarees as $cle => $titre) {
            $st->execute([formation_slug(), $cle, $titre, ++$ordre]);
        }
    } catch (PDOException $e) {
        // Table ou colonne absente : la télécommande affiche la migration à jouer
    }
}

// Carte affichée à la place d'une section encore fermée : le titre, jamais le contenu
function carte_verrouillee(string $cle): string
{
    return '<section class="card card-locked">'
         . '<h2><span class="lock" aria-hidden="true">🔒</span> ' . e(etape_titre($cle)) . '</h2>'
         . '<p class="lead">Cette étape sera ouverte par le formateur.</p>'
         . '</section>';
}
