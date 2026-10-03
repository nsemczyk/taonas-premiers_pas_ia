<?php
// Le bocal à secrets, côté stagiaire : le faux mail à gauche, où l'on touche
// les mots à surligner ; la demande réécrite, anonymisée, à droite (en dessous
// sur téléphone). Brouillon enregistré au fil de l'eau, puis copie rendue au
// formateur, qui la teste et la note.
require __DIR__ . '/fonctions.php';

const ETAPE_BOCAL = 'bocal';

$ouverte = etape_ouverte(ETAPE_BOCAL);
$moi     = participant_courant();
$pret    = $ouverte && $moi && bocal_config() && bocal_table_ok();
$pid     = $moi ? (int)$moi['id'] : 0;

function lire_surlignes(string $brut): array
{
    $v = json_decode($brut, true);
    return is_array($v) ? $v : [];
}

// Brouillon enregistré au fil de l'eau
if (($_GET['json'] ?? '') === 'sauver' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $in = json_decode(file_get_contents('php://input'), true) ?: [];
    $ok = $pret && bocal_sauver($pid, (array)($in['surlignes'] ?? []), (string)($in['prompt'] ?? ''));
    http_response_code($ok ? 200 : 409);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => $ok, 'heure' => date('H:i:s')]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $pret) {
    $action = $_POST['action'] ?? '';
    if ($action === 'rendre') {
        bocal_sauver($pid, lire_surlignes((string)($_POST['surlignes'] ?? '')), (string)($_POST['prompt'] ?? ''), true);
    } elseif ($action === 'reprendre') {
        bocal_reprendre($pid);
    }
    header('Location: ' . avec_f('bocal.php'), true, 303);
    exit;
}

$copie  = $pret ? bocal_copie($pid) : null;
$rendue = $copie && $copie['rendu'];
$notee  = $rendue && bocal_points($pid) !== ['surlignage' => null, 'prompt' => null];
?><!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="icon" href="assets/favicon-couleur.png">
<meta name="robots" content="noindex">
<title>Le bocal à secrets</title>
<link rel="stylesheet" href="style.css">
</head>
<body>

<header class="site-head">
  <h1>Le bocal à secrets</h1>
  <p>Ce qui ne doit jamais partir chez une IA</p>
</header>

<main class="wrap<?= $pret ? ' wrap-large' : '' ?>">

<?php if (!$ouverte): ?>
  <section class="card"><h2>Pas encore ouvert</h2><p class="lead">Le formateur ouvrira le bocal dans un instant.</p></section>

<?php elseif (!$moi): ?>
  <section class="card"><h2>D'abord, votre prénom</h2><p class="lead">Indiquez votre prénom sur l'accueil.</p></section>

<?php elseif (!$pret): ?>
  <section class="card"><h2>Bientôt…</h2><p class="lead">L'exercice n'est pas encore prêt.</p></section>

<?php else: ?>
  <section class="card">
    <p class="consigne"><?= e(bocal_config()['consigne'] ?? '') ?></p>
    <?php if (!$rendue): ?>
      <p class="muted">Touchez un mot pour le surligner, touchez-le à nouveau pour l'effacer.</p>
    <?php endif; ?>
  </section>

  <div class="bocal-grille">
    <section class="card">
      <h2>Le mail <span class="bocal-compte" id="compte"></span></h2>
      <div class="bocal-mail<?= $rendue ? '' : ' actif' ?>" id="mail"><?= bocal_html($copie['surlignes'] ?? []) ?></div>
    </section>

    <section class="card<?= $rendue ? '' : ' a-toi' ?>">
      <h2>Ma version anonymisée</h2>
      <?php if ($rendue): ?>
        <div class="brique-texte deja"><?= $copie['prompt'] !== '' ? e($copie['prompt']) : '<em>(vide)</em>' ?></div>
        <p class="lead">Copie rendue. <?= $notee ? 'Le formateur l\'a notée.' : 'Le formateur va la tester avec son IA.' ?></p>
        <?php if (!$notee): ?>
        <form method="post" action="<?= e(avec_f('bocal.php')) ?>">
          <input type="hidden" name="action" value="reprendre">
          <button class="btn btn-ghost">Modifier ma copie</button>
        </form>
        <?php endif; ?>
      <?php else: ?>
        <p class="consigne">La demande que vous enverriez à l'IA : même sens, plus aucun secret.</p>
        <textarea class="field saisie" id="prompt" rows="10" maxlength="<?= BOCAL_MAX_PROMPT ?>"
                  placeholder="Point RH sur un technicien…"><?= e($copie['prompt'] ?? '') ?></textarea>
        <p class="sauvegarde" id="sauvegarde"><?= $copie ? 'Brouillon enregistré.' : 'Rien d\'enregistré pour l\'instant.' ?></p>
        <form method="post" action="<?= e(avec_f('bocal.php')) ?>" id="rendre">
          <input type="hidden" name="action" value="rendre">
          <input type="hidden" name="surlignes" id="champ-surlignes">
          <input type="hidden" name="prompt" id="champ-prompt">
          <button class="btn btn-primary">Rendre ma copie</button>
        </form>
      <?php endif; ?>
    </section>
  </div>
<?php endif; ?>

  <section class="card">
    <a class="btn btn-ghost" href="<?= e(avec_f('index.php')) ?>">Retour à l'accueil</a>
  </section>

</main>

<?php if ($pret): ?>
<script>
(function () {
  var URL_SAUVER = <?= json_encode(avec_f('bocal.php?json=sauver')) ?>;
  var mail = document.getElementById('mail');
  var prompt = document.getElementById('prompt');
  var note = document.getElementById('sauvegarde');
  var compte = document.getElementById('compte');

  function surlignes() {
    return Array.prototype.map.call(mail.querySelectorAll('.mot.surligne'), function (m) { return +m.dataset.i; });
  }
  function compter() {
    var n = mail.querySelectorAll('.mot.surligne').length;
    compte.textContent = n ? '· ' + n + ' mot' + (n > 1 ? 's' : '') + ' surligné' + (n > 1 ? 's' : '') : '';
  }
  compter();
  if (!prompt) { return; }   // copie rendue : lecture seule

  var attente = null;
  function sauver() {
    clearTimeout(attente);
    return fetch(URL_SAUVER, {
      method: 'POST', headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ surlignes: surlignes(), prompt: prompt.value })
    }).then(function (r) { return r.json(); }).then(function (d) {
      note.textContent = d.ok ? 'Brouillon enregistré à ' + d.heure + '.' : 'Copie déjà rendue.';
    }).catch(function () { note.textContent = 'Connexion perdue : nouvel essai à la prochaine modification.'; });
  }
  function plusTard() {
    note.textContent = 'Modification en cours…';
    clearTimeout(attente);
    attente = setTimeout(sauver, 1000);
  }

  mail.addEventListener('click', function (ev) {
    var mot = ev.target.closest('.mot');
    if (!mot) { return; }
    mot.classList.toggle('surligne');
    compter();
    plusTard();
  });
  prompt.addEventListener('input', plusTard);

  document.getElementById('rendre').addEventListener('submit', function (ev) {
    if (!confirm('Rendre la copie ? Vous pourrez encore la modifier tant que le formateur ne l\'a pas notée.')) {
      ev.preventDefault();
      return;
    }
    clearTimeout(attente);
    document.getElementById('champ-surlignes').value = JSON.stringify(surlignes());
    document.getElementById('champ-prompt').value = prompt.value;
  });
})();
</script>
<?php endif; ?>

</body>
</html>
