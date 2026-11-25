<?php
// Carnet de la journée : le PDF que chaque participant télécharge en fin de
// journée, avec ce qu'il a fait (et ce que son équipe a fait), exercice par
// exercice, corrigés compris.
//
// Évolutif : chaque exercice déclare lui-même sa section, dans son
// fonctions.php, par carnet_section('<clé d'étape>', function (CarnetPdf $pdf, array $participant) {…}).
// La fonction ne dessine rien s'il n'y a rien à montrer ; sinon elle ouvre
// sa section par $pdf->exercice() puis écrit avec les helpers de CarnetPdf
// (core/carnet-pdf.php). Les sections suivent l'ordre des étapes de la formation.
// carnet_memo('<clé d'étape>', …) ajoute un encadré à la page « À retenir »,
// pour les formations qui ont cette étape.
//
// Aucune sortie : ce fichier ne définit que des helpers.

// Le registre : ['sections' => [étape => [rendus…]], 'memo' => [[étape, rendu]…]]
function &carnet_registre(): array
{
    static $registre = ['sections' => [], 'memo' => []];
    return $registre;
}

// Section du carnet pour l'étape $etape : $rendu(CarnetPdf $pdf, array $participant)
function carnet_section(string $etape, callable $rendu): void
{
    $r = &carnet_registre();
    $r['sections'][$etape][] = $rendu;
}

// Encadré de la page « À retenir » : $rendu(CarnetPdf $pdf)
function carnet_memo(string $etape, callable $rendu): void
{
    $r = &carnet_registre();
    $r['memo'][] = [$etape, $rendu];
}
