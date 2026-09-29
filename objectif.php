<?php
// Mon objectif du jour : le participant complète la phrase du mur des
// objectifs. Son post-it apparaît sur le tableau du formateur (mur.php) et
// reste modifiable tant que l'étape « objectifs » est ouverte.
require __DIR__ . '/core/objectifs.php';
require __DIR__ . '/core/etapes.php';

const ETAPE_OBJECTIFS = 'objectifs';

$ouverte = etape_ouverte(ETAPE_OBJECTIFS);
$moi     = participant_courant();
$erreur  = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $ouverte && $moi) {
    $texte = objectif_texte_clean($_POST['texte'] ?? '');
    if ($texte === '') {
        $erreur = 'Complétez la phrase avant d\'envoyer votre post-it.';
    } else {
        objectif_enregistrer((int)$moi['id'], $texte);
        header('Location: ' . avec_f('objectif.php?colle=1'), true, 303);
        exit;
    }
}

$objectif = $moi ? objectif_de((int)$moi['id']) : null;
$colle    = isset($_GET['colle']) && $objectif;
$saisie   = $_POST['texte'] ?? ($objectif['texte'] ?? '');
?><!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="icon" href="assets/favicon-couleur.png">
<meta name="robots" content="noindex">
<title>Mon objectif du jour</title>
<link rel="stylesheet" href="style.css">
</head>
<body>

<header class="site-head">
  <h1>Mon objectif du jour</h1>
  <p>Votre post-it rejoindra ceux du groupe au tableau</p>
</header>

<main class="wrap">

<?php if (!$ouverte): ?>
  <section class="card">
    <h2>Pas encore ouvert</h2>
    <p class="lead">Le formateur ouvrira le mur des objectifs dans un instant.</p>
    <?php if ($objectif): ?>
      <p>Votre objectif : « <?= e($objectif['texte']) ?> »</p>
    <?php endif; ?>
  </section>

<?php elseif (!$moi): ?>
  <section class="card">
    <h2>D'abord, votre prénom</h2>
    <p class="lead">Indiquez votre prénom sur l'accueil : il signera votre post-it.</p>
  </section>

<?php else: ?>

  <?php if ($colle): ?>
  <section class="card colle">
    <h2>C'est collé au tableau !</h2>
    <p class="lead">Vous pouvez encore le modifier tant que le formateur n'a pas fermé le mur.</p>
  </section>
  <?php endif; ?>

  <section class="card">
    <div class="postit postit-apercu" id="apercu" style="--papier:<?= objectif_papier($objectif) ?>">
      <p class="postit-amorce"><?= e(OBJECTIF_AMORCE) ?>…</p>
      <p class="postit-texte" id="apercu-texte"><?= e($saisie) ?></p>
      <p class="postit-prenom"><?= e($moi['prenom']) ?></p>
    </div>

    <form method="post" action="<?= e(avec_f('objectif.php')) ?>">
      <?php if ($erreur): ?><div class="err"><?= e($erreur) ?></div><?php endif; ?>
      <label class="field" for="texte"><?= e(OBJECTIF_AMORCE) ?>…</label>
      <textarea class="field saisie" id="texte" name="texte" maxlength="<?= OBJECTIF_MAX ?>" rows="3" required
                placeholder="Par exemple : écrire mes mails plus vite"><?= e($saisie) ?></textarea>
      <p class="compteur" id="compteur"></p>
      <button class="btn btn-primary"><?= $objectif ? 'Mettre à jour mon post-it' : 'Coller mon post-it au tableau' ?></button>
    </form>
  </section>

<?php endif; ?>

  <section class="card">
    <a class="btn btn-ghost" href="<?= e(avec_f('index.php')) ?>">Retour à l'accueil</a>
  </section>

</main>

<script>
// Aperçu du post-it pendant la saisie
(function () {
  var champ = document.getElementById('texte');
  if (!champ) { return; }
  var apercu = document.getElementById('apercu-texte');
  var compteur = document.getElementById('compteur');
  function maj() {
    apercu.textContent = champ.value;
    apercu.dataset.longueur = champ.value.length > 100 ? 'long' : (champ.value.length > 50 ? 'moyen' : 'court');
    var reste = champ.maxLength - champ.value.length;
    compteur.textContent = reste + ' caractère' + (reste > 1 ? 's' : '') + ' restant' + (reste > 1 ? 's' : '');
  }
  champ.addEventListener('input', maj);
  maj();
})();
</script>

</body>
</html>
