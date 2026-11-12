<?php
// Bingo de l'hallucination, côté formateur. Deux vues :
//   - pilotage (par défaut, téléphone) : afficher le texte, file des bingos
//     annoncés (dans l'ordre d'arrivée, avec la ligne de chacun) à valider ou
//     refuser après vérification orale, corrigé, recommencer ;
//   - ?vue=projection : l'écran du vidéoprojecteur (le texte, les bingos
//     annoncés, le gagnant, puis le corrigé), qui suit la partie en direct.
require __DIR__ . '/fonctions.php';

// Accès réservé à l'animateur
$cle = (string)($_REQUEST['cle'] ?? '');
if (!hash_equals(CLE_ANIMATEUR, $cle)) {
    http_response_code(403);
    exit('Accès réservé. Ajoutez ?cle=... à l\'adresse.');
}
$base       = 'bingo-tableau.php?cle=' . rawurlencode(CLE_ANIMATEUR);
$moi        = avec_f($base);
$projection = ($_GET['vue'] ?? '') === 'projection';
$pret       = bingo_config() && bingo_table_ok();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $pret) {
    $action = $_POST['action'] ?? '';
    if (in_array($action, ['texte_visible', 'corrige_visible'], true)) {
        bingo_basculer($action);
    } elseif (in_array($action, ['valider', 'refuser', 'annuler'], true)) {
        bingo_decider((int)($_POST['grille'] ?? 0), $action);
    } elseif ($action === 'recommencer') {
        bingo_recommencer();
    }
    header('Location: ' . $moi, true, 303);
    exit;
}

$config   = bingo_config();
$partie   = $pret ? bingo_partie() : null;
$annonces = $pret ? bingo_annonces() : [];
$gagnant  = $pret ? bingo_gagnant() : null;
$joueurs  = $pret ? bingo_nb_joueurs() : 0;

$empreinte = md5(json_encode([$partie, array_map(fn($a) => [$a['id'], $a['statut'], $a['bingo_at']], $annonces), $joueurs]));
if (($_GET['json'] ?? '') === 'etat') {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['empreinte' => $empreinte]);
    exit;
}

// Les cases de la ligne gagnante d'une grille, dans l'ordre de la ligne
function cases_ligne(array $g, array $cases): array
{
    return array_map(fn($pos) => $cases[$g['ordre'][$pos]], bingo_ligne($g['coches']) ?? []);
}

function bouton(string $moi, string $action, string $libelle, string $classe, int $grille = 0, string $confirmer = ''): string
{
    return '<form method="post" action="' . e($moi) . '"' . ($confirmer !== '' ? ' onsubmit="return confirm(\'' . e($confirmer) . '\')"' : '') . '>'
         . '<input type="hidden" name="action" value="' . e($action) . '">'
         . ($grille ? '<input type="hidden" name="grille" value="' . $grille . '">' : '')
         . '<button class="' . e($classe) . '">' . e($libelle) . '</button></form>';
}

$libelleStatut = ['attente' => 'à vérifier', 'valide' => 'gagnant', 'refuse' => 'refusé'];
?><!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="icon" href="assets/favicon-couleur.png">
<meta name="robots" content="noindex">
<title><?= $projection ? 'Bingo de l\'hallucination' : 'Bingo de l\'hallucination — pilotage' ?></title>
<link rel="stylesheet" href="style.css">
</head>
<body class="<?= $projection ? 'battle-projete bingo-projete' : '' ?>">

<?php if ($projection): ?>
<!-- ============================================================ PROJECTION -->
<header class="bp-tete">
  <h1>Bingo de l'hallucination</h1>
  <a href="<?= e($moi) ?>">Pilotage</a>
</header>
<main class="bp-scene">
<?php if (!$pret): ?>
  <p class="bp-attente">Jeu non configuré ou tables absentes : jouez exercices/bingo/migration.sql.</p>
<?php elseif (!$partie['texte_visible']): ?>
  <p class="bp-attente">Un texte produit par une IA va s'afficher.<br>Cochez les cases. Première ligne complète gagne.</p>
<?php else: ?>
  <?php if ($gagnant): ?>
    <p class="bp-victoire">🏆 Bingo pour <?= e($gagnant['prenom']) ?> !</p>
  <?php elseif ($annonces && array_filter($annonces, fn($a) => $a['statut'] === 'attente')): ?>
    <p class="bp-victoire">Bingo ! <?= e(implode(', ', array_column(array_filter($annonces, fn($a) => $a['statut'] === 'attente'), 'prenom'))) ?></p>
  <?php endif; ?>
  <article class="bingo-texte<?= $partie['corrige_visible'] ? ' corrige' : '' ?>">
    <?= bingo_texte_html((bool)$partie['corrige_visible']) ?>
  </article>
  <?php if ($partie['corrige_visible']): ?>
  <table class="bingo-corrige">
    <?php foreach ($config['corrige'] as $i => [$passage, $erreur, $case]): ?>
    <tr><td class="n"><?= $i + 1 ?></td><td><?= e($erreur) ?></td><td><?= e($case) ?></td></tr>
    <?php endforeach; ?>
  </table>
  <p class="bingo-avertissement"><?= e($config['avertissement'] ?? '') ?></p>
  <?php endif; ?>
<?php endif; ?>
</main>

<?php else: ?>
<!-- ============================================================ PILOTAGE -->
<header class="site-head">
  <h1>Bingo de l'hallucination</h1>
  <p><?= e(formation()['titre']) ?> · <?= $joueurs ?> grille<?= $joueurs > 1 ? 's' : '' ?> ouverte<?= $joueurs > 1 ? 's' : '' ?></p>
</header>

<main class="wrap">
  <?= selecteur_formation($base) ?>

<?php if (!$config): ?>
  <section class="card"><h2>Pas de jeu</h2><p>Cette formation ne déclare pas de clé <code>bingo</code> (9 cases) dans son <code>formation.php</code>.</p></section>
<?php elseif (!$pret): ?>
  <section class="card">
    <h2>Migration à jouer</h2>
    <pre>mysql --default-character-set=utf8mb4 -u root -p formation_ia &lt; exercices/bingo/migration.sql</pre>
  </section>
<?php else: ?>
  <section class="card">
    <a class="btn btn-gold" href="<?= e(avec_f($base . '&vue=projection')) ?>" target="_blank">Ouvrir l'écran de projection</a>
    <?php if (!$partie['texte_visible']): ?>
      <p class="lead">Les stagiaires voient leur grille, sans pouvoir cocher. Le texte apparaît au vidéoprojecteur quand vous l'affichez : les grilles s'ouvrent alors.</p>
      <?= bouton($moi, 'texte_visible', 'Afficher le texte', 'btn btn-primary') ?>
    <?php else: ?>
      <?= bouton($moi, 'texte_visible', 'Masquer le texte (et bloquer les grilles)', 'btn btn-ghost') ?>
    <?php endif; ?>
  </section>

  <section class="card">
    <h2>Les bingos</h2>
    <?php if (!$annonces): ?>
      <p class="muted">Aucun bingo annoncé pour l'instant.</p>
    <?php endif; ?>
    <?php foreach ($annonces as $i => $a): ?>
    <div class="bingo-annonce statut-<?= e($a['statut']) ?>">
      <p><strong><?= $i + 1 ?>. <?= e($a['prenom']) ?></strong> <?= $a['equipe'] ? badge_equipe($a['equipe']) : '' ?>
        · <?= e($libelleStatut[$a['statut']] ?? '') ?> · <?= e(moment_local(substr($a['bingo_at'], 0, 19))->format('H:i:s')) ?></p>
      <?php if ($a['statut'] !== 'refuse'): ?>
      <p class="muted">Sa ligne : <?= e(implode(' · ', cases_ligne($a, $config['cases']))) ?></p>
      <?php endif; ?>
      <div class="bingo-actions">
        <?php if ($a['statut'] === 'attente' && !$gagnant): ?>
          <?= bouton($moi, 'valider', 'Valider' . (points_actifs() ? ' (+' . BINGO_POINTS . ' pts)' : ''), 'btn btn-primary', (int)$a['id']) ?>
        <?php endif; ?>
        <?php if ($a['statut'] === 'attente'): ?>
          <?= bouton($moi, 'refuser', 'Refuser', 'btn btn-ghost', (int)$a['id']) ?>
        <?php elseif ($a['statut'] === 'valide'): ?>
          <?= bouton($moi, 'annuler', 'Annuler la validation', 'btn btn-ghost', (int)$a['id'], 'Annuler la victoire et ses points ?') ?>
        <?php endif; ?>
      </div>
    </div>
    <?php endforeach; ?>
  </section>

  <section class="card">
    <h2>Débrief</h2>
    <?= bouton($moi, 'corrige_visible', $partie['corrige_visible'] ? 'Masquer le corrigé au vidéoprojecteur' : 'Afficher le corrigé au vidéoprojecteur',
               $partie['corrige_visible'] ? 'btn btn-ghost' : 'btn btn-primary') ?>
    <p class="muted"><?= e($config['avertissement'] ?? '') ?></p>
    <details class="doc">
      <summary>Le corrigé</summary>
      <table class="bocal-corrige">
        <tr><th>Erreur</th><th>Case du bingo</th></tr>
        <?php foreach ($config['corrige'] as [, $erreur, $case]): ?>
        <tr><td><?= e($erreur) ?></td><td><?= e($case) ?></td></tr>
        <?php endforeach; ?>
      </table>
    </details>
  </section>

  <section class="card">
    <?= bouton($moi, 'recommencer', 'Recommencer la partie', 'btn btn-ghost', 0, 'Effacer toutes les grilles, les bingos et les points du bingo ?') ?>
    <a class="btn btn-ghost" href="<?= e(avec_f('pilotage.php?cle=' . rawurlencode(CLE_ANIMATEUR))) ?>">Retour au pilotage</a>
  </section>
<?php endif; ?>
</main>
<?php endif; ?>

<?php if ($pret): ?>
<script>
// Suit la partie en direct : rechargement dès qu'un bingo arrive ou qu'une décision tombe
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
