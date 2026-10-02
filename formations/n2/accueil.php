<?php
// Accueil du niveau 2 : les cartes de la journée, dans l'ordre du déroulé.
// Inclus par index.php, dans <main> ; les clés d'étape (objectifs…) sont
// celles de la table `etapes` pour cette formation.
?>
  <?= bloc_arrivee() ?>

  <?= bloc_objectif('objectifs') ?>

  <?= bloc_boule('boule') ?>

  <?= bloc_battle('battle') ?>

  <?= bloc_bocal('bocal') ?>
