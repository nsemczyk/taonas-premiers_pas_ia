<?php
// Documents remis aux participants (guides PDF), déclarés par la formation :
//   'documents' => ['cle' => ['titre' => …, 'fichier' => 'x.pdf', 'description' => …,
//                             'etape' => 'cle d'étape' (facultatif)], …],
// Les fichiers sont dans assets/documents/, en accès direct (liens durables).
// Un document avec 'etape' a sa carte sur l'accueil, dévoilée avec l'étape ;
// tous figurent avec leur lien dans le carnet de la journée.
//
// Aucune sortie : ce fichier ne définit que des helpers.
require_once __DIR__ . '/etapes.php';

const DOCUMENTS_DOSSIER = 'assets/documents/';

function documents(): array
{
    return formation()['documents'] ?? [];
}

// Lien relatif, pour les pages du site (servies à la racine ou sous /n2/)
function document_lien(array $doc): string
{
    return DOCUMENTS_DOSSIER . rawurlencode($doc['fichier']);
}

// Adresse absolue, pour un lien hors du site (carnet PDF) : SITE_URL si
// config.php le déclare, sinon l'adresse de la requête en cours
function document_url(array $doc): string
{
    if (defined('SITE_URL') && SITE_URL !== '') {
        $site = rtrim(SITE_URL, '/');
    } else {
        $https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
        $site = ($https ? 'https://' : 'http://') . ($_SERVER['HTTP_HOST'] ?? 'localhost') . base_url();
    }
    return $site . '/' . document_lien($doc);
}

// Carte d'accueil du document rattaché à l'étape $cle
function bloc_document(string $cle): string
{
    if (!etape_ouverte($cle)) {
        return carte_verrouillee($cle);
    }
    foreach (documents() as $doc) {
        if (($doc['etape'] ?? null) === $cle) {
            return '<section class="card">' . "\n"
                 . '    <h2>' . e($doc['titre']) . '</h2>' . "\n"
                 . '    <p class="lead">' . e($doc['description']) . '</p>' . "\n"
                 . '    <a class="btn btn-primary" href="' . e(document_lien($doc)) . '" target="_blank" rel="noopener">Ouvrir le guide (PDF)</a>' . "\n"
                 . '    <p class="muted">Il vous attend aussi dans votre carnet de fin de journée.</p>' . "\n"
                 . '  </section>';
        }
    }
    return '';
}
