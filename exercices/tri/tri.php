<?php
// Jeu de tri, côté stagiaire : les 12 cartes, chacune avec ses 5 catégories
// à toucher sur téléphone ; sur ordinateur (grand écran et souris), un plateau
// à 5 colonnes où l'on glisse les cartes depuis la pioche du bas. Enregistré à chaque choix, modifiable jusqu'à ce que le
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

<main class="wrap<?= $pret ? ' wrap-large' : '' ?>">

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
      <p class="lead"><span class="tri-tactile">Pour chaque carte, touchez sa catégorie.</span>
        <span class="tri-souris">Glissez chaque carte de la pioche vers sa colonne ; vous pouvez la déplacer à nouveau, ou la remettre dans la pioche.</span>
        Votre score compte pour l'équipe <?= badge_equipe($moi['equipe']) ?> : débattez, mais chacun choisit.</p>
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

  <?php
  // Une carte du plateau, à glisser (ordinateur)
  $jeton = function (int $n) use ($config, $reponses, $devoilees, $partie): string {
      $carte = $config['cartes'][$n];
      $choix = $reponses[$n] ?? null;
      $etat  = $n < $devoilees ? (tri_juste($n, $choix) ? ' juste' : ' faux') : '';
      $h = '<div class="tri-jeton' . $etat . '" data-carte="' . $n . '"' . ($partie['clos'] ? '' : ' draggable="true"') . '>'
         . '<span class="tri-num">' . ($n + 1) . '</span> ' . e($carte['texte']);
      if ($etat === ' faux') {
          $h .= '<span class="tri-jeton-verdict">✗ C\'était : ' . e(tri_libelles($carte['reponses'])) . '</span>';
      } elseif ($etat === ' juste') {
          $h .= '<span class="tri-jeton-verdict">✓ Bien vu</span>';
      }
      return $h . '</div>';
  };
  ?>
  <section class="card tri-plateau">
    <div class="tri-colonnes">
      <?php foreach ($config['categories'] as $cle => $libelle): ?>
      <div class="tri-zone" data-cat="<?= e($cle) ?>">
        <p class="tri-zone-titre"><?= e($libelle) ?></p>
        <?php foreach ($config['cartes'] as $n => $carte): if (($reponses[$n] ?? null) === $cle): ?><?= $jeton($n) ?><?php endif; endforeach; ?>
      </div>
      <?php endforeach; ?>
    </div>
    <?php if (!$partie['clos'] || count($reponses) < $nb): ?>
    <p class="tri-pioche-titre"><?= $partie['clos'] ? 'Cartes non rangées' : 'La pioche : glissez les cartes vers leur colonne' ?></p>
    <div class="tri-zone tri-pioche" data-cat="">
      <?php foreach ($config['cartes'] as $n => $carte): if (!isset($reponses[$n])): ?><?= $jeton($n) ?><?php endif; endforeach; ?>
    </div>
    <?php endif; ?>
  </section>

  <div class="tri-liste">
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
  </div>
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

  // Range la carte n dans la catégorie cat ('' : la remet dans la pioche).
  // La liste (téléphone) et le plateau (ordinateur) restent synchronisés.
  function ranger(n, cat) {
    var liste = document.querySelector('.tri-carte[data-carte="' + n + '"]');
    liste.querySelectorAll('.tri-cat').forEach(function (b) { b.classList.toggle('choisie', b.dataset.cat === cat); });
    var jeton = document.querySelector('.tri-jeton[data-carte="' + n + '"]');
    var zone = document.querySelector('.tri-zone[data-cat="' + cat + '"]');
    if (jeton && zone) {
      // à sa place dans la colonne, dans l'ordre des numéros
      var apres = Array.prototype.filter.call(zone.querySelectorAll('.tri-jeton'), function (j) { return +j.dataset.carte > n; })[0];
      zone.insertBefore(jeton, apres || null);
    }
    if (compte) { compte.textContent = document.querySelectorAll('.tri-liste .tri-cat.choisie').length; }
    fetch(URL_RANGER, {
      method: 'POST', headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ carte: n, categorie: cat })
    }).then(function (r) { if (!r.ok) { location.reload(); } }).catch(function () {});
  }

  // Téléphone : toucher une catégorie ; toucher le choix actuel le retire
  document.querySelectorAll('.tri-carte').forEach(function (carte) {
    carte.addEventListener('click', function (ev) {
      var b = ev.target.closest('.tri-cat');
      if (!b || b.disabled) { return; }
      ranger(+carte.dataset.carte, b.classList.contains('choisie') ? '' : b.dataset.cat);
    });
  });

  // Ordinateur : glisser-déposer entre la pioche et les colonnes
  var enCours = null;
  document.querySelectorAll('.tri-jeton[draggable]').forEach(function (j) {
    j.addEventListener('dragstart', function (ev) {
      enCours = j;
      ev.dataTransfer.effectAllowed = 'move';
      ev.dataTransfer.setData('text/plain', j.dataset.carte);
      setTimeout(function () { j.classList.add('deplace'); }, 0);
    });
    j.addEventListener('dragend', function () {
      j.classList.remove('deplace');
      enCours = null;
      document.querySelectorAll('.tri-zone.survol').forEach(function (z) { z.classList.remove('survol'); });
    });
  });
  document.querySelectorAll('.tri-zone').forEach(function (zone) {
    zone.addEventListener('dragover', function (ev) {
      if (!enCours) { return; }
      ev.preventDefault();
      ev.dataTransfer.dropEffect = 'move';
      zone.classList.add('survol');
    });
    zone.addEventListener('dragleave', function (ev) {
      if (!zone.contains(ev.relatedTarget)) { zone.classList.remove('survol'); }
    });
    zone.addEventListener('drop', function (ev) {
      ev.preventDefault();
      zone.classList.remove('survol');
      if (!enCours || enCours.parentNode === zone) { return; }
      ranger(+enCours.dataset.carte, zone.dataset.cat);
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
