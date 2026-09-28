<?php
require __DIR__ . '/core/blocs.php';   // -> étapes, formation, config

$ouvertes = etapes_ouvertes();

// Suivi léger de l'ouverture des étapes, interrogé toutes les 10 s par la page
if (($_GET['json'] ?? '') === 'etat') {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ouvertes' => $ouvertes]);
    exit;
}

$F = formation();
?><!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="icon" href="assets/favicon-couleur.png">
<meta name="robots" content="noindex">
<title><?= e($F['titre']) ?></title>
<link rel="stylesheet" href="style.css">
</head>
<body>

<header class="site-head">
  <h1><?= e($F['titre']) ?></h1>
  <p><?= e($F['accroche']) ?></p>
</header>

<main class="wrap">

  <?php require formation_chemin('accueil.php'); ?>

  <p class="rgpd">
    Seul votre prénom et votre score sont enregistrés, pour le suivi de la formation.
    Aucune autre donnée personnelle n'est collectée. Suppression sur simple demande
    auprès du formateur.
  </p>

</main>

<div class="bandeau" id="bandeau" hidden>
  <span>Une nouvelle étape est disponible.</span>
  <button class="btn btn-gold" id="btn-afficher">Afficher</button>
</div>

<script>
// Suivi des étapes : le formateur les ouvre depuis pilotage.php
(function () {
  var OUVERTES = <?= json_encode($ouvertes) ?>;
  var bandeau = document.getElementById('bandeau');

  document.getElementById('btn-afficher').addEventListener('click', function () {
    location.reload();
  });

  setInterval(function () {
    fetch(<?= json_encode(avec_f('index.php?json=etat')) ?>).then(function (r) { return r.json(); }).then(function (d) {
      var nouvelles = d.ouvertes.filter(function (c) { return OUVERTES.indexOf(c) < 0; });
      if (nouvelles.length) {
        bandeau.hidden = false;          // on laisse le participant choisir son moment
      } else if (d.ouvertes.length < OUVERTES.length) {
        location.reload();               // une étape a été refermée : on applique tout de suite
      }
    }).catch(function () {});
  }, 10000);
})();

// Copie vers le presse-papiers, avec repli pour les anciens téléphones
document.querySelectorAll('[data-copy]').forEach(function (btn) {
  btn.addEventListener('click', function () {
    var id = btn.getAttribute('data-copy');
    var text = document.getElementById('txt-' + id).textContent;
    var note = document.getElementById('note-' + id);
    function done() {
      note.classList.add('show');
      setTimeout(function () { note.classList.remove('show'); }, 3000);
    }
    if (navigator.clipboard && navigator.clipboard.writeText) {
      navigator.clipboard.writeText(text).then(done).catch(function () { fallback(); });
    } else {
      fallback();
    }
    function fallback() {
      var ta = document.createElement('textarea');
      ta.value = text;
      ta.style.position = 'fixed';
      ta.style.opacity = '0';
      document.body.appendChild(ta);
      ta.select();
      try { document.execCommand('copy'); done(); } catch (e) {}
      document.body.removeChild(ta);
    }
  });
});
</script>

</body>
</html>
