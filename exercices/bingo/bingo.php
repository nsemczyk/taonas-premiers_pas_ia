<?php
// Bingo de l'hallucination, côté stagiaire : sa grille 3×3 (cases mélangées
// pour lui), à cocher une fois le texte projeté. Une ligne complète fait
// apparaître « Bingo ! » ; le formateur vérifie à l'oral et valide. Le texte,
// lui, n'est qu'au vidéoprojecteur.
require __DIR__ . '/fonctions.php';

const ETAPE_BINGO = 'bingo';

$ouverte = etape_ouverte(ETAPE_BINGO);
$moi     = participant_courant();
$pret    = $ouverte && $moi && bingo_config() && bingo_table_ok();
$pid     = $moi ? (int)$moi['id'] : 0;

function repondre(array $data, int $code = 200): void
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

if (($_GET['json'] ?? '') === 'cocher' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $in = json_decode(file_get_contents('php://input'), true);
    $ok = $pret && bingo_cocher($pid, (array)($in['coches'] ?? []));
    repondre(['ok' => $ok], $ok ? 200 : 409);
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $pret && ($_POST['action'] ?? '') === 'bingo') {
    bingo_annoncer($pid);
    header('Location: ' . avec_f('bingo.php'), true, 303);
    exit;
}

$partie  = $pret ? bingo_partie() : null;
$grille  = $pret ? bingo_grille($pid) : null;
$gagnant = $pret ? bingo_gagnant() : null;
$cases   = bingo_config()['cases'] ?? [];
$jouable = $partie && $partie['texte_visible'] && !$gagnant && !in_array($grille['statut'], ['attente', 'valide'], true);

// La page se recharge quand la partie change pour ce joueur (texte, décision, gagnant) — pas à ses propres coches
$empreinte = md5(json_encode([$ouverte, $partie['texte_visible'] ?? 0, $grille['statut'] ?? null, $gagnant['id'] ?? 0]));
if (($_GET['json'] ?? '') === 'etat') {
    repondre(['empreinte' => $empreinte]);
}
?><!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="icon" href="assets/favicon-couleur.png">
<meta name="robots" content="noindex">
<title>Bingo de l'hallucination</title>
<link rel="stylesheet" href="style.css">
</head>
<body>

<header class="site-head">
  <h1>Bingo de l'hallucination</h1>
  <p>Cochez les cases. Première ligne complète gagne.</p>
</header>

<main class="wrap">

<?php if (!$ouverte): ?>
  <section class="card"><h2>Pas encore ouvert</h2><p class="lead">Le formateur lancera le bingo dans un instant.</p></section>

<?php elseif (!$moi): ?>
  <section class="card"><h2>D'abord, votre prénom</h2><p class="lead">Indiquez votre prénom sur l'accueil.</p></section>

<?php elseif (!$pret): ?>
  <section class="card"><h2>Bientôt…</h2><p class="lead">Le jeu n'est pas encore prêt.</p></section>

<?php else: ?>
  <section class="card<?= $grille['statut'] === 'valide' ? ' a-toi' : '' ?>">
    <?php if ($gagnant && (int)$gagnant['participant_id'] === $pid): ?>
      <h2>Bingo ! Vous avez gagné</h2>
      <p class="lead">Bravo, première ligne complète validée<?= points_actifs() ? ' : +' . BINGO_POINTS . ' points' : '' ?>.</p>
    <?php elseif ($gagnant): ?>
      <h2>Bingo pour <?= e($gagnant['prenom']) ?> !</h2>
      <p class="lead">La partie est terminée. Place au corrigé.</p>
    <?php elseif ($grille['statut'] === 'attente'): ?>
      <h2>Bingo annoncé !</h2>
      <p class="lead">Le formateur vérifie : préparez-vous à citer, pour chaque case de votre ligne, le passage du texte.</p>
    <?php elseif (!$partie['texte_visible']): ?>
      <h2>Prêt ?</h2>
      <p class="lead">Le texte va s'afficher au vidéoprojecteur. Écoutez bien : vous cocherez dès qu'il apparaît.</p>
    <?php elseif ($grille['statut'] === 'refuse'): ?>
      <div class="err">Bingo refusé : vérifiez vos cases, la partie continue.</div>
      <p class="lead">Touchez une case quand vous repérez l'erreur dans le texte projeté.</p>
    <?php else: ?>
      <p class="lead">Touchez une case quand vous repérez l'erreur dans le texte projeté.</p>
    <?php endif; ?>
  </section>

  <section class="card">
    <div class="bingo-grille<?= $jouable ? ' jouable' : '' ?>" id="grille">
      <?php foreach ($grille['ordre'] as $pos => $n): ?>
      <button type="button" class="bingo-case<?= in_array($pos, $grille['coches'], true) ? ' cochee' : '' ?>"
              data-pos="<?= $pos ?>"<?= $jouable ? '' : ' disabled' ?>><?= e($cases[$n]) ?></button>
      <?php endforeach; ?>
    </div>
    <?php if ($jouable): ?>
    <form method="post" action="<?= e(avec_f('bingo.php')) ?>" id="form-bingo" hidden>
      <input type="hidden" name="action" value="bingo">
      <button class="btn btn-gold bingo-bouton">BINGO !</button>
    </form>
    <?php endif; ?>
  </section>
<?php endif; ?>

  <section class="card">
    <a class="btn btn-ghost" href="<?= e(avec_f('index.php')) ?>">Retour à l'accueil</a>
  </section>

</main>

<?php if ($pret): ?>
<script>
(function () {
  var EMPREINTE = <?= json_encode($empreinte) ?>;
  var URL_ETAT = <?= json_encode(avec_f('bingo.php?json=etat')) ?>;
  var URL_COCHER = <?= json_encode(avec_f('bingo.php?json=cocher')) ?>;
  var LIGNES = <?= json_encode(BINGO_LIGNES) ?>;
  var grille = document.getElementById('grille');
  var form = document.getElementById('form-bingo');

  function coches() {
    return Array.prototype.map.call(grille.querySelectorAll('.cochee'), function (c) { return +c.dataset.pos; });
  }
  // Une ligne complète : les cases gagnantes s'allument, le bouton apparaît
  function verifier() {
    var c = coches();
    var ligne = LIGNES.filter(function (l) { return l.every(function (p) { return c.indexOf(p) >= 0; }); })[0];
    grille.querySelectorAll('.bingo-case').forEach(function (b) {
      b.classList.toggle('ligne', !!ligne && ligne.indexOf(+b.dataset.pos) >= 0);
    });
    if (form) { form.hidden = !ligne; }
  }
  verifier();

  if (grille.classList.contains('jouable')) {
    grille.addEventListener('click', function (ev) {
      var b = ev.target.closest('.bingo-case');
      if (!b || b.disabled) { return; }
      b.classList.toggle('cochee');
      verifier();
      fetch(URL_COCHER, {
        method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ coches: coches() })
      }).then(function (r) { if (!r.ok) { location.reload(); } }).catch(function () {});
    });
  }

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
