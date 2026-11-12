<?php
// Atelier workflow, côté formateur. Deux vues :
//   - par défaut : les schémas de tous les stagiaires, en vignettes, mis à
//     jour en direct, chacun avec un bouton pour le projeter ;
//   - ?vue=projection&p=ID : le schéma d'un stagiaire en plein écran pour le
//     débrief, qui suit ses modifications en direct.
require __DIR__ . '/fonctions.php';

// Accès réservé à l'animateur
$cle = (string)($_REQUEST['cle'] ?? '');
if (!hash_equals(CLE_ANIMATEUR, $cle)) {
    http_response_code(403);
    exit('Accès réservé. Ajoutez ?cle=... à l\'adresse.');
}
$base       = 'workflow-tableau.php?cle=' . rawurlencode(CLE_ANIMATEUR);
$moi        = avec_f($base);
$projection = ($_GET['vue'] ?? '') === 'projection';
$pret       = workflow_table_ok();
$schemas    = $pret ? workflow_schemas() : [];
$choisi     = (int)($_GET['p'] ?? 0);

if ($projection) {
    $schemas = array_values(array_filter($schemas, fn($s) => (int)$s['participant_id'] === $choisi));
}
$empreinte = md5(json_encode(array_map(fn($s) => [$s['participant_id'], $s['updated_at']], $schemas)));
if (($_GET['json'] ?? '') === 'etat') {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['empreinte' => $empreinte]);
    exit;
}

function compte(array $schema, string $type): int
{
    return count(array_filter($schema['noeuds'], fn($n) => $n['type'] === $type));
}
?><!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="icon" href="assets/favicon-couleur.png">
<meta name="robots" content="noindex">
<title><?= $projection ? 'Atelier workflow' : 'Atelier workflow — les schémas' ?></title>
<link rel="stylesheet" href="style.css">
</head>
<body class="<?= $projection ? 'wf-projete' : '' ?>">

<?php if ($projection): $s = $schemas[0] ?? null; ?>
<!-- ============================================================ PROJECTION -->
<header class="wf-projete-tete">
  <h1><?= $s ? e($s['schema']['titre'] !== '' ? $s['schema']['titre'] : 'Sans titre') : 'Schéma introuvable' ?>
    <?php if ($s): ?><span>· <?= e($s['prenom']) ?></span><?php endif; ?></h1>
  <a href="<?= e($moi) ?>">Tous les schémas</a>
</header>
<?php if ($s): ?>
  <svg class="wf-grand" data-schema="<?= e(json_encode($s['schema'], JSON_UNESCAPED_UNICODE)) ?>"></svg>
<?php endif; ?>

<?php else: ?>
<!-- ============================================================ GALERIE -->
<header class="site-head">
  <h1>Atelier workflow</h1>
  <p><?= e(formation()['titre']) ?> · <?= count($schemas) ?> schéma<?= count($schemas) > 1 ? 's' : '' ?></p>
</header>

<main class="wrap wrap-large">
  <?= selecteur_formation($base) ?>

<?php if (!$pret): ?>
  <section class="card">
    <h2>Migration à jouer</h2>
    <pre>mysql --default-character-set=utf8mb4 -u root -p formation_ia &lt; exercices/workflow/migration.sql</pre>
  </section>
<?php elseif (!$schemas): ?>
  <section class="card"><p class="lead">Aucun schéma pour l'instant : ils apparaîtront ici dès que les stagiaires commenceront.</p></section>
<?php else: ?>
  <div class="wf-galerie">
    <?php foreach ($schemas as $s): ?>
    <section class="card wf-vignette">
      <h2><?= e($s['prenom']) ?> <?= $s['equipe'] ? badge_equipe($s['equipe']) : '' ?></h2>
      <p class="lead"><?= $s['schema']['titre'] !== '' ? e($s['schema']['titre']) : '<span class="muted">Sans titre</span>' ?></p>
      <svg class="wf-mini" data-schema="<?= e(json_encode($s['schema'], JSON_UNESCAPED_UNICODE)) ?>"></svg>
      <p class="muted"><?= compte($s['schema'], 'declencheur') ?> déclencheur(s) · <?= compte($s['schema'], 'etape') ?> étape(s)
        · <?= compte($s['schema'], 'condition') ?> condition(s) · <?= count($s['schema']['liens']) ?> flèche(s)</p>
      <a class="btn btn-gold" href="<?= e(avec_f($base . '&vue=projection&p=' . (int)$s['participant_id'])) ?>" target="_blank">Projeter</a>
    </section>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

  <section class="card">
    <a class="btn btn-ghost" href="<?= e(avec_f('pilotage.php?cle=' . rawurlencode(CLE_ANIMATEUR))) ?>">Retour au pilotage</a>
  </section>
</main>
<?php endif; ?>

<?php if ($pret): ?>
<?= workflow_script() ?>
<script>
(function () {
  document.querySelectorAll('svg[data-schema]').forEach(function (svg) {
    Schema.dessiner(svg, JSON.parse(svg.getAttribute('data-schema')), {});
  });
  // Les schémas suivent les stagiaires en direct
  var EMPREINTE = <?= json_encode($empreinte) ?>;
  var URL_ETAT = <?= json_encode(avec_f($base . ($projection ? '&vue=projection&p=' . $choisi : '') . '&json=etat')) ?>;
  setInterval(function () {
    fetch(URL_ETAT).then(function (r) { return r.json(); }).then(function (d) {
      if (d.empreinte !== EMPREINTE) { location.reload(); }
    }).catch(function () {});
  }, 3000);
})();
</script>
<?php endif; ?>

</body>
</html>
