<?php
// Mon carnet de la journée : en fin de journée, chaque participant télécharge
// un PDF de ce qu'il a fait (et de ce que son équipe a fait), corrigés compris.
// Les sections sont déclarées par les exercices eux-mêmes (core/carnet.php) :
// rien à changer ici quand un exercice s'ajoute. Pas de table : tout est relu
// dans les données des exercices.
//
// Aucune sortie : ce fichier ne définit que des helpers.
require_once __DIR__ . '/../../core/etapes.php';
require_once __DIR__ . '/../../core/participants.php';

const CARNET_ETAPE = 'carnet';

// Mon carnet : le bouton de téléchargement, derrière l'étape $cle
function bloc_carnet(string $cle = CARNET_ETAPE): string
{
    if (!etape_ouverte($cle)) {
        return carte_verrouillee($cle);
    }
    $h = '<section class="card a-toi">' . "\n"
       . '    <h2>' . e(etape_titre($cle)) . '</h2>' . "\n";
    if (!participant_courant()) {
        return $h . '    <p class="lead">Indiquez d\'abord votre prénom ci-dessus.</p>' . "\n" . '  </section>';
    }
    return $h . '    <p class="lead">Tout ce que vous avez produit aujourd\'hui, exercice par exercice, avec les corrigés et les méthodes à retenir : un PDF à garder.</p>' . "\n"
              . '    <a class="btn btn-primary" href="' . e(avec_f('carnet.php')) . '">Télécharger mon carnet (PDF)</a>' . "\n"
              . '  </section>';
}
