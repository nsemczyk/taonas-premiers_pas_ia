<?php
// Mon carnet de la journée : le PDF du participant courant, généré à la demande.
// core/blocs.php charge tous les exercices, donc toutes les sections du carnet.
require __DIR__ . '/../../core/blocs.php';
require __DIR__ . '/../../core/carnet-pdf.php';

$moi = participant_courant();
if (!etape_ouverte(CARNET_ETAPE) || !$moi) {
    http_response_code(409);
    ?><!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="icon" href="assets/favicon-couleur.png">
<meta name="robots" content="noindex">
<title>Mon carnet de la journée</title>
<link rel="stylesheet" href="style.css">
</head>
<body>
<header class="site-head"><h1>Mon carnet de la journée</h1></header>
<main class="wrap">
  <section class="card">
    <h2><?= $moi ? 'Pas encore disponible' : 'D\'abord, votre prénom' ?></h2>
    <p class="lead"><?= $moi ? 'Le formateur ouvrira le carnet en fin de journée.' : 'Indiquez votre prénom sur l\'accueil.' ?></p>
    <a class="btn btn-ghost" href="<?= e(avec_f('index.php')) ?>">Retour à l'accueil</a>
  </section>
</main>
</body>
</html>
<?php
    exit;
}

$nom = preg_replace('/[^a-z0-9]+/', '-', strtolower((string)iconv('UTF-8', 'ASCII//TRANSLIT', $moi['prenom']))) ?: 'participant';
carnet_pdf($moi)->Output('D', 'carnet-' . trim($nom, '-') . '-' . aujourdhui_local() . '.pdf');
