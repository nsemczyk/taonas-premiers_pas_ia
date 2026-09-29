<?php
// Points et classement de la séance. Deux vues :
//   - gestion (par défaut) : donner ou retirer des points aux équipes et aux
//     joueurs, depuis le téléphone du formateur ;
//   - ?vue=projection : le classement plein écran, pour le vidéoprojecteur,
//     qui suit les changements en direct.
// Points d'équipe et points individuels sont indépendants.
require __DIR__ . '/core/points.php';

// Accès réservé à l'animateur
$cle = (string)($_REQUEST['cle'] ?? '');
if (!hash_equals(CLE_ANIMATEUR, $cle)) {
    http_response_code(403);
    exit('Accès réservé. Ajoutez ?cle=... à l\'adresse.');
}
$base       = 'scores.php?cle=' . rawurlencode(CLE_ANIMATEUR);
$moi        = avec_f($base);
$projection = ($_GET['vue'] ?? '') === 'projection';
$table_ok   = points_table_ok();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $table_ok) {
    $cible  = (string)($_POST['cible'] ?? '');   // « e:glacier » ou « j:12 »
    $valeur = (int)($_POST['valeur'] ?? 0);
    $motif  = trim((string)($_POST['motif'] ?? ''));
    if (str_starts_with($cible, 'e:')) {
        points_ajouter(substr($cible, 2), null, $valeur, $motif);
    } elseif (str_starts_with($cible, 'j:')) {
        points_ajouter(null, (int)substr($cible, 2), $valeur, $motif);
    }
    header('Location: ' . $moi . '#' . preg_replace('/[^a-z0-9:-]/', '', $cible), true, 303);
    exit;
}

$equipes = $table_ok ? points_equipes() : [];
$joueurs = $table_ok ? points_joueurs() : [];
// Seules les équipes qui ont des membres ou des points
$membres = array_count_values(array_filter(array_column(participants_seance(), 'equipe')));
$equipes = array_filter($equipes, fn($t, $eq) => $t !== 0 || !empty($membres[$eq]), ARRAY_FILTER_USE_BOTH);

$empreinte = md5(json_encode([$equipes, array_map(fn($j) => [$j['id'], $j['total'], $j['equipe']], $joueurs)]));
if (($_GET['json'] ?? '') === 'etat') {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['empreinte' => $empreinte]);
    exit;
}

// Un bouton de points (+1, -1, +5…) pour une cible donnée
function bouton_points(string $moi, string $cible, int $valeur): string
{
    return '<form method="post" action="' . e($moi) . '">'
         . '<input type="hidden" name="cible" value="' . e($cible) . '">'
         . '<input type="hidden" name="valeur" value="' . $valeur . '">'
         . '<button class="pts-btn' . ($valeur < 0 ? ' moins' : '') . '">' . ($valeur > 0 ? '+' : '−') . abs($valeur) . '</button>'
         . '</form>';
}
?><!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="icon" href="assets/favicon-couleur.png">
<meta name="robots" content="noindex">
<title><?= $projection ? 'Classement' : 'Points et classement' ?></title>
<link rel="stylesheet" href="style.css">
</head>
<body class="<?= $projection ? 'classement-projete' : '' ?>">

<?php if ($projection): $rangsE = points_rangs($equipes); $rangsJ = points_rangs(array_column($joueurs, 'total')); ?>

<header class="cl-tete">
  <h1>Classement</h1>
  <a href="<?= e($moi) ?>">Gérer les points</a>
</header>
<main class="cl-grille">
  <section>
    <h2>Équipes</h2>
    <?php $i = 0; foreach ($equipes as $eq => $total): $def = equipes()[$eq]; ?>
    <div class="cl-equipe" style="--equipe:<?= e($def['couleur'] ?? '#1B3E90') ?>">
      <span class="cl-rang"><?= $rangsE[$i++] ?></span>
      <span class="cl-nom"><?= e($def['nom']) ?></span>
      <span class="cl-total"><?= $total ?> <small>pts</small></span>
    </div>
    <?php endforeach; ?>
    <?php if (!$equipes): ?><p class="cl-vide">Pas encore d'équipes.</p><?php endif; ?>
  </section>
  <section>
    <h2>Joueurs</h2>
    <ol class="cl-joueurs">
      <?php foreach ($joueurs as $i => $j): ?>
      <li class="<?= $rangsJ[$i] <= 3 && $j['total'] > 0 ? 'podium podium-' . $rangsJ[$i] : '' ?>">
        <span class="cl-rang"><?= $rangsJ[$i] ?></span>
        <span class="cl-nom"><?= e($j['prenom']) ?> <?= $j['equipe'] ? badge_equipe($j['equipe']) : '' ?></span>
        <span class="cl-total"><?= $j['total'] ?></span>
      </li>
      <?php endforeach; ?>
    </ol>
    <?php if (!$joueurs): ?><p class="cl-vide">Personne pour l'instant.</p><?php endif; ?>
  </section>
</main>

<?php else: ?>

<header class="site-head">
  <h1>Points et classement</h1>
  <p><?= e(formation()['titre']) ?> · équipes et joueurs, comptés séparément</p>
</header>

<main class="wrap">

  <?= selecteur_formation($base) ?>

<?php if (!$table_ok): ?>
  <section class="card">
    <h2>Migration à jouer</h2>
    <p>La table des points n'existe pas encore. Jouez une fois, avec un compte administrateur&nbsp;:</p>
    <pre>mysql --default-character-set=utf8mb4 -u root -p formation_ia &lt; migration-points.sql</pre>
  </section>
<?php else: ?>

  <section class="card">
    <a class="btn btn-primary" href="<?= e(avec_f($base . '&vue=projection')) ?>" target="_blank">Afficher le classement (vidéoprojecteur)</a>
  </section>

  <section class="card">
    <h2>Équipes</h2>
    <?php foreach ($equipes as $eq => $total): $def = equipes()[$eq]; ?>
    <div class="pts-ligne" id="<?= e('e:' . $eq) ?>">
      <span class="pts-nom"><?= badge_equipe($eq) ?></span>
      <span class="pts-total"><?= $total ?></span>
      <span class="pts-actions">
        <?= bouton_points($moi, 'e:' . $eq, -1) ?><?= bouton_points($moi, 'e:' . $eq, 1) ?>
        <?= bouton_points($moi, 'e:' . $eq, 5) ?><?= bouton_points($moi, 'e:' . $eq, 10) ?>
      </span>
    </div>
    <?php endforeach; ?>
    <?php if (!$equipes): ?><p class="muted">Aucune équipe constituée pour l'instant.</p><?php endif; ?>
  </section>

  <section class="card">
    <h2>Joueurs</h2>
    <?php foreach ($joueurs as $j): ?>
    <div class="pts-ligne" id="<?= e('j:' . $j['id']) ?>">
      <span class="pts-nom"><?= e($j['prenom']) ?> <?= $j['equipe'] ? badge_equipe($j['equipe']) : '' ?></span>
      <span class="pts-total"><?= $j['total'] ?></span>
      <span class="pts-actions">
        <?= bouton_points($moi, 'j:' . $j['id'], -1) ?><?= bouton_points($moi, 'j:' . $j['id'], 1) ?>
        <?= bouton_points($moi, 'j:' . $j['id'], 5) ?><?= bouton_points($moi, 'j:' . $j['id'], 10) ?>
      </span>
    </div>
    <?php endforeach; ?>
    <?php if (!$joueurs): ?><p class="muted">Personne pour l'instant.</p><?php endif; ?>
  </section>

  <?php if ($equipes || $joueurs): ?>
  <section class="card">
    <h2>Autre montant</h2>
    <form method="post" action="<?= e($moi) ?>" class="pts-libre">
      <label class="field" for="cible">Pour</label>
      <select class="field" id="cible" name="cible" required>
        <?php foreach ($equipes as $eq => $t): ?>
          <option value="<?= e('e:' . $eq) ?>">Équipe <?= e(equipes()[$eq]['nom']) ?></option>
        <?php endforeach; ?>
        <?php foreach ($joueurs as $j): ?>
          <option value="<?= e('j:' . $j['id']) ?>"><?= e($j['prenom']) ?></option>
        <?php endforeach; ?>
      </select>
      <label class="field" for="valeur">Points (négatif pour retirer)</label>
      <input class="field" id="valeur" name="valeur" type="number" min="-1000" max="1000" value="10" required>
      <label class="field" for="motif">Motif (facultatif)</label>
      <input class="field" id="motif" name="motif" type="text" maxlength="120" placeholder="Bonne question, bonus rapidité…">
      <button class="btn btn-primary">Attribuer</button>
    </form>
  </section>
  <?php endif; ?>

<?php endif; ?>

  <section class="card">
    <a class="btn btn-ghost" href="<?= e(avec_f('pilotage.php?cle=' . rawurlencode(CLE_ANIMATEUR))) ?>">Retour au pilotage</a>
  </section>

</main>

<?php endif; ?>

<?php if ($table_ok): ?>
<script>
// Classement et totaux en direct : rechargement dès qu'un point bouge
(function () {
  var EMPREINTE = <?= json_encode($empreinte) ?>;
  var enSaisie = function () { var a = document.activeElement; return a && /INPUT|SELECT/.test(a.tagName); };
  setInterval(function () {
    if (enSaisie()) { return; }
    fetch(<?= json_encode($moi . '&json=etat') ?>)
      .then(function (r) { return r.json(); })
      .then(function (d) { if (d.empreinte !== EMPREINTE) { location.reload(); } })
      .catch(function () {});
  }, 3000);
})();
</script>
<?php endif; ?>

</body>
</html>
