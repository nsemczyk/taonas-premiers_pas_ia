<?php
// Jeu de tri, côté formateur. Deux vues :
//   - pilotage (par défaut, téléphone) : avancement par équipe, clôture du tri,
//     correction carte par carte (carte, réponse, suivante), scores d'équipe
//     et points, recommencer ;
//   - ?vue=projection : l'écran du vidéoprojecteur, qui suit en direct — les
//     5 catégories pendant le tri, puis chaque carte avec les réponses des
//     équipes et la bonne colonne, puis les scores.
require __DIR__ . '/fonctions.php';

// Accès réservé à l'animateur
$cle = (string)($_REQUEST['cle'] ?? '');
if (!hash_equals(CLE_ANIMATEUR, $cle)) {
    http_response_code(403);
    exit('Accès réservé. Ajoutez ?cle=... à l\'adresse.');
}
$base       = 'tri-tableau.php?cle=' . rawurlencode(CLE_ANIMATEUR);
$moi        = avec_f($base);
$projection = ($_GET['vue'] ?? '') === 'projection';
$config     = tri_config();
$pret       = $config && tri_table_ok();
$nb         = count($config['cartes'] ?? []);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $pret) {
    $p = tri_partie();
    switch ($_POST['action'] ?? '') {
        case 'clore':    tri_maj(['clos' => 1]); break;
        case 'rouvrir':  tri_maj(['clos' => 0, 'carte' => 0, 'revele' => 0]); break;
        case 'reveler':  tri_maj(['revele' => 1]); break;
        case 'suivante': tri_maj(['carte' => min($nb + 1, $p['carte'] + 1), 'revele' => 0]); break;
        case 'precedente': tri_maj(['carte' => max(0, $p['carte'] - 1), 'revele' => 1]); break;
        case 'points':
            if (points_actifs() && points_table_ok()) {
                tri_basculer_points();
            }
            break;
        case 'recommencer': tri_recommencer(); break;
    }
    header('Location: ' . $moi, true, 303);
    exit;
}

$partie  = $pret ? tri_partie() : null;
$joueurs = $pret ? tri_joueurs() : [];
$scores  = $pret ? tri_scores() : [];
$points  = points_actifs() && points_table_ok();
$donnes  = $pret && $points && tri_points_donnes();
$carte   = $partie && $partie['carte'] >= 1 && $partie['carte'] <= $nb ? $partie['carte'] - 1 : null;
$rep     = $carte !== null ? tri_repartition($carte) : [];

// Avancement par équipe : joueurs, et combien ont rangé toutes les cartes
$avancement = [];
foreach ($joueurs as $j) {
    $avancement[$j['equipe']]['joueurs'] = ($avancement[$j['equipe']]['joueurs'] ?? 0) + 1;
    $avancement[$j['equipe']]['finis'] = ($avancement[$j['equipe']]['finis'] ?? 0) + (count($j['reponses']) === $nb ? 1 : 0);
}

$empreinte = md5(json_encode([$partie, array_map(fn($j) => [$j['id'], $j['reponses']], $joueurs), $donnes]));
if (($_GET['json'] ?? '') === 'etat') {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['empreinte' => $empreinte]);
    exit;
}

function bouton(string $moi, string $action, string $libelle, string $classe, string $confirmer = ''): string
{
    return '<form method="post" action="' . e($moi) . '"' . ($confirmer !== '' ? ' onsubmit="return confirm(\'' . e($confirmer) . '\')"' : '') . '>'
         . '<input type="hidden" name="action" value="' . e($action) . '">'
         . '<button class="' . e($classe) . '">' . e($libelle) . '</button></form>';
}

function moyenne(float $m): string
{
    return number_format($m, 1, ',', '');
}
?><!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="icon" href="assets/favicon-couleur.png">
<meta name="robots" content="noindex">
<title><?= $projection ? 'Jeu de tri' : 'Jeu de tri — pilotage' ?></title>
<link rel="stylesheet" href="style.css">
</head>
<body class="<?= $projection ? 'battle-projete' : '' ?>">

<?php if ($projection): ?>
<!-- ============================================================ PROJECTION -->
<header class="bp-tete">
  <h1>Jeu de tri : qui est quoi ?<?= $carte !== null ? ' <span>· carte ' . ($carte + 1) . ' / ' . $nb . '</span>' : '' ?></h1>
  <a href="<?= e($moi) ?>">Pilotage</a>
</header>
<main class="bp-scene">
<?php if (!$pret): ?>
  <p class="bp-attente">Jeu non configuré ou tables absentes : jouez exercices/tri/migration.sql.</p>

<?php elseif ($partie['carte'] > $nb): ?>
  <p class="bp-label">Les scores</p>
  <div class="tp-scores">
    <?php foreach ($scores as $eq => $s): $def = equipes()[$eq]; ?>
    <div class="bp-camp" style="--equipe:<?= e($def['couleur']) ?>"><?= e($def['nom']) ?>
      <small><?= moyenne($s['moyenne']) ?> cartes justes en moyenne · <?= $s['points'] ?> pts</small></div>
    <?php endforeach; ?>
  </div>

<?php elseif ($carte !== null): $c = $config['cartes'][$carte]; ?>
  <p class="bp-tache tp-carte"><?= e($c['texte']) ?></p>
  <div class="tp-colonnes">
    <?php foreach ($config['categories'] as $k => $libelle): $bonne = $partie['revele'] && in_array($k, $c['reponses'], true); ?>
    <div class="tp-colonne<?= $bonne ? ' bonne' : '' ?>">
      <p class="tp-cat"><?= e($libelle) ?></p>
      <?php foreach (array_keys($scores) as $eq): if (!empty($rep[$eq][$k])): ?>
        <p><?= badge_equipe($eq) ?> × <?= $rep[$eq][$k] ?></p>
      <?php endif; endforeach; ?>
    </div>
    <?php endforeach; ?>
  </div>
  <?php if ($partie['revele'] && !empty($c['debat'])): ?>
    <p class="tp-debat">Débat : <?= e($c['debat']) ?></p>
  <?php endif; ?>

<?php else: ?>
  <div class="tp-colonnes tp-vides">
    <?php foreach ($config['categories'] as $libelle): ?><div class="tp-colonne"><p class="tp-cat"><?= e($libelle) ?></p></div><?php endforeach; ?>
  </div>
  <p class="bp-label"><?= $partie['clos'] ? 'Tri terminé : place à la correction.' : 'Rangez les ' . $nb . ' cartes sur votre téléphone.' ?></p>
  <div class="tp-scores">
    <?php foreach ($avancement as $eq => $a): ?>
      <p><?= badge_equipe($eq) ?> <?= $a['finis'] ?> / <?= $a['joueurs'] ?> ont fini</p>
    <?php endforeach; ?>
  </div>
<?php endif; ?>
</main>

<?php else: ?>
<!-- ============================================================ PILOTAGE -->
<header class="site-head">
  <h1>Jeu de tri</h1>
  <p><?= e(formation()['titre']) ?> · qui est quoi ?</p>
</header>

<main class="wrap">
  <?= selecteur_formation($base) ?>

<?php if (!$config): ?>
  <section class="card"><h2>Pas de jeu</h2><p>Cette formation ne déclare pas de clé <code>tri</code> dans son <code>formation.php</code>.</p></section>
<?php elseif (!$pret): ?>
  <section class="card">
    <h2>Migration à jouer</h2>
    <pre>mysql --default-character-set=utf8mb4 -u root -p formation_ia &lt; exercices/tri/migration.sql</pre>
  </section>
<?php else: ?>
  <section class="card">
    <a class="btn btn-gold" href="<?= e(avec_f($base . '&vue=projection')) ?>" target="_blank">Ouvrir l'écran de projection</a>
    <h2><?= $partie['clos'] ? 'Tri clos' : 'Tri en cours' ?></h2>
    <?php foreach ($avancement as $eq => $a): ?>
      <p><?= badge_equipe($eq) ?> <?= $a['finis'] ?> / <?= $a['joueurs'] ?> joueur<?= $a['joueurs'] > 1 ? 's' : '' ?> ont rangé les <?= $nb ?> cartes</p>
    <?php endforeach; ?>
    <?php if (!$avancement): ?><p class="muted">Aucun joueur placé dans une équipe pour l'instant.</p><?php endif; ?>
    <?php if (!$partie['clos']): ?>
      <?= bouton($moi, 'clore', 'Clore le tri et passer à la correction', 'btn btn-primary', 'Plus aucune modification ne sera possible. Clore le tri ?') ?>
    <?php else: ?>
      <?= bouton($moi, 'rouvrir', 'Rouvrir le tri', 'btn btn-ghost', 'Rouvrir le tri ? La correction repart de zéro.') ?>
    <?php endif; ?>
  </section>

  <?php if ($partie['clos']): ?>
  <section class="card a-toi">
    <h2>Correction</h2>
    <?php if ($carte === null && $partie['carte'] === 0): ?>
      <p class="lead">Les cartes défilent une à une au vidéoprojecteur : d'abord les réponses des équipes, puis la bonne catégorie.</p>
      <?= bouton($moi, 'suivante', 'Projeter la carte 1', 'btn btn-primary') ?>
    <?php elseif ($carte !== null): $c = $config['cartes'][$carte]; ?>
      <p class="lead"><strong>Carte <?= $carte + 1 ?> / <?= $nb ?></strong> : <?= e($c['texte']) ?></p>
      <p>Réponse : <strong><?= e(tri_libelles($c['reponses'])) ?></strong><?= !empty($c['debat']) ? '<br><span class="muted">' . e($c['debat']) . '</span>' : '' ?></p>
      <?php if (!$partie['revele']): ?>
        <?= bouton($moi, 'reveler', 'Révéler la réponse', 'btn btn-gold') ?>
      <?php else: ?>
        <?= bouton($moi, 'suivante', $carte + 1 < $nb ? 'Carte suivante' : 'Afficher les scores', 'btn btn-primary') ?>
      <?php endif; ?>
      <?= bouton($moi, 'precedente', 'Carte précédente', 'btn btn-ghost') ?>
    <?php else: ?>
      <p class="lead">Correction terminée : les scores sont au vidéoprojecteur.</p>
      <?= bouton($moi, 'precedente', 'Revenir à la dernière carte', 'btn btn-ghost') ?>
    <?php endif; ?>
  </section>
  <?php endif; ?>

  <section class="card">
    <h2>Scores des équipes</h2>
    <p class="muted">Moyenne des cartes bien placées par les membres, arrondie, × <?= TRI_POINTS_CARTE ?> pts. Les cartes ambiguës acceptent deux réponses.</p>
    <?php foreach ($scores as $eq => $s): ?>
      <p><?= badge_equipe($eq) ?> <?= moyenne($s['moyenne']) ?> / <?= $nb ?> (<?= $s['membres'] ?> joueur<?= $s['membres'] > 1 ? 's' : '' ?>) → <strong><?= $s['points'] ?> pts</strong></p>
    <?php endforeach; ?>
    <?php if ($points && $scores && $partie['clos']): ?>
      <?= bouton($moi, 'points', $donnes ? 'Reprendre les points du tri' : 'Donner les points aux équipes', $donnes ? 'btn btn-ghost' : 'btn btn-gold') ?>
    <?php endif; ?>
  </section>

  <section class="card">
    <?= bouton($moi, 'recommencer', 'Recommencer le jeu', 'btn btn-ghost', 'Effacer tous les tris, la correction et les points du jeu ?') ?>
    <a class="btn btn-ghost" href="<?= e(avec_f('pilotage.php?cle=' . rawurlencode(CLE_ANIMATEUR))) ?>">Retour au pilotage</a>
  </section>
<?php endif; ?>
</main>
<?php endif; ?>

<?php if ($pret): ?>
<script>
// Suit le jeu en direct
(function () {
  var EMPREINTE = <?= json_encode($empreinte) ?>;
  var URL_ETAT = <?= json_encode($moi . '&json=etat') ?>;
  setInterval(function () {
    fetch(URL_ETAT).then(function (r) { return r.json(); }).then(function (d) {
      if (d.empreinte !== EMPREINTE) { location.reload(); }
    }).catch(function () {});
  }, 2000);
})();
</script>
<?php endif; ?>

</body>
</html>
