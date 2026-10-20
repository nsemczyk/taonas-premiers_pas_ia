<?php
// Jeu de tri, côté stagiaire : les 12 cartes, chacune avec ses 5 catégories
// à toucher. Enregistré à chaque choix, modifiable jusqu'à ce que le
// formateur clôture le tri. Pendant la correction, chaque carte dévoilée
// indique au joueur s'il l'avait bien placée.
require __DIR__ . '/fonctions.php';

const ETAPE_TRI = 'tri';

$ouverte = etape_ouverte(ETAPE_TRI);
$moi     = participant_courant();
$equipe  = $moi && equipe_existe($moi['equipe']);
$pret    = $ouverte && $equipe && tri_config() && tri_table_ok();
$pid     = $moi ? (int)$moi['id'] : 0;

function repondre(array $data, int $code = 200): void
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

if (($_GET['json'] ?? '') === 'ranger' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $in = json_decode(file_get_contents('php://input'), true) ?: [];
    $ok = $pret && tri_ranger($pid, (int)($in['carte'] ?? -1), (string)($in['categorie'] ?? ''));
    repondre(['ok' => $ok], $ok ? 200 : 409);
}

$config   = tri_config();
$partie   = $pret ? tri_partie() : null;
$reponses = $pret ? tri_reponses($pid) : [];
$nb       = count($config['cartes'] ?? []);
// Cartes dont la réponse est dévoilée : celles déjà passées, et la carte projetée si révélée
$devoilees = $partie ? max(0, min($nb, $partie['carte'] - ($partie['revele'] || $partie['carte'] > $nb ? 0 : 1))) : 0;

$justes    = count(array_filter(array_keys($reponses), fn($n) => $n < $devoilees && tri_juste($n, $reponses[$n])));

$empreinte = md5(json_encode([$ouverte, $equipe, $partie['clos'] ?? 0, $devoilees]));
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
<title>Jeu de tri : qui est quoi ?</title>
<link rel="stylesheet" href="style.css">
</head>
<body>

<header class="site-head">
  <h1>Jeu de tri : qui est quoi ?</h1>
  <p>Prompt, assistant, workflow, automatisation ou agent</p>
</header>

<main class="wrap">

<?php if (!$ouverte): ?>
  <section class="card"><h2>Pas encore ouvert</h2><p class="lead">Le formateur lancera le jeu dans un instant.</p></section>

<?php elseif (!$moi): ?>
  <section class="card"><h2>D'abord, votre prénom</h2><p class="lead">Indiquez votre prénom sur l'accueil.</p></section>

<?php elseif (!$equipe): ?>
  <section class="card"><h2>Un jeu en équipe</h2><p class="lead">Attendez d'être placé dans votre équipe : votre score comptera pour elle.</p></section>

<?php elseif (!$pret): ?>
  <section class="card"><h2>Bientôt…</h2><p class="lead">Le jeu n'est pas encore prêt.</p></section>

<?php else: ?>
  <section class="card">
    <?php if (!$partie['clos']): ?>
      <p class="lead">Pour chaque carte, touchez sa catégorie. Votre score compte pour l'équipe <?= badge_equipe($moi['equipe']) ?> :
        débattez, mais chacun choisit.</p>
      <p class="tri-compte"><strong id="compte"><?= count($reponses) ?></strong> / <?= $nb ?> cartes rangées</p>
    <?php elseif (!$devoilees): ?>
      <h2>Tri terminé</h2>
      <p class="lead">Place à la correction, carte par carte, au vidéoprojecteur.</p>
    <?php else: ?>
      <h2>Correction</h2>
      <p class="lead"><?= $justes ?> bonne<?= $justes > 1 ? 's' : '' ?> réponse<?= $justes > 1 ? 's' : '' ?>
        sur <?= $devoilees ?> carte<?= $devoilees > 1 ? 's' : '' ?> corrigée<?= $devoilees > 1 ? 's' : '' ?>.</p>
    <?php endif; ?>
  </section>

  <?php foreach ($config['cartes'] as $n => $carte):
      $choix = $reponses[$n] ?? null;
      $corrigee = $n < $devoilees; ?>
  <section class="card tri-carte<?= $corrigee ? (tri_juste($n, $choix) ? ' juste' : ' faux') : '' ?>" data-carte="<?= $n ?>">
    <p class="tri-texte"><span class="tri-num"><?= $n + 1 ?></span> <?= e($carte['texte']) ?></p>
    <div class="tri-choix">
      <?php foreach ($config['categories'] as $cle => $libelle): ?>
      <button type="button" class="tri-cat<?= $choix === $cle ? ' choisie' : '' ?><?= $corrigee && in_array($cle, $carte['reponses'], true) ? ' bonne' : '' ?>"
              data-cat="<?= e($cle) ?>"<?= $partie['clos'] ? ' disabled' : '' ?>><?= e($libelle) ?></button>
      <?php endforeach; ?>
    </div>
    <?php if ($corrigee): ?>
      <p class="tri-verdict"><?= tri_juste($n, $choix) ? '✓ Bien vu' : '✗ C\'était : ' . e(tri_libelles($carte['reponses'])) ?>
        <?= !empty($carte['debat']) ? '<br><span class="muted">' . e($carte['debat']) . '</span>' : '' ?></p>
    <?php endif; ?>
  </section>
  <?php endforeach; ?>
<?php endif; ?>

  <section class="card">
    <a class="btn btn-ghost" href="<?= e(avec_f('index.php')) ?>">Retour à l'accueil</a>
  </section>

</main>

<?php if ($pret): ?>
<script>
(function () {
  var EMPREINTE = <?= json_encode($empreinte) ?>;
  var URL_ETAT = <?= json_encode(avec_f('tri.php?json=etat')) ?>;
  var URL_RANGER = <?= json_encode(avec_f('tri.php?json=ranger')) ?>;
  var compte = document.getElementById('compte');

  document.querySelectorAll('.tri-carte').forEach(function (carte) {
    carte.addEventListener('click', function (ev) {
      var b = ev.target.closest('.tri-cat');
      if (!b || b.disabled) { return; }
      var deja = b.classList.contains('choisie');   // toucher le choix actuel le retire
      carte.querySelectorAll('.tri-cat').forEach(function (x) { x.classList.remove('choisie'); });
      if (!deja) { b.classList.add('choisie'); }
      if (compte) { compte.textContent = document.querySelectorAll('.tri-cat.choisie').length; }
      fetch(URL_RANGER, {
        method: 'POST', headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ carte: +carte.dataset.carte, categorie: deja ? '' : b.dataset.cat })
      }).then(function (r) { if (!r.ok) { location.reload(); } }).catch(function () {});
    });
  });

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
