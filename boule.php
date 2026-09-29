<?php
// Le prompt boule de neige, côté joueur. Au moment de jouer, on voit tout ce
// qui précède ; le reste du temps, seulement ses propres briques. Tout se
// dévoile à l'équipe à la fin. La page suit la partie toute seule.
require __DIR__ . '/core/boule.php';
require __DIR__ . '/core/etapes.php';

const ETAPE_BOULE = 'boule';

$ouverte = etape_ouverte(ETAPE_BOULE);
$moi     = participant_courant();
$equipe  = $moi && equipe_existe($moi['equipe']) ? $moi['equipe'] : null;
$etat    = $ouverte && $equipe ? boule_etat($equipe) : null;

// Empreinte de la partie : la page se recharge quand elle change
function boule_empreinte(?array $etat): string
{
    return $etat ? md5(json_encode([$etat['etape'], $etat['joueur']['id'] ?? 0, $etat['tache']])) : '';
}

if (($_GET['json'] ?? '') === 'etat') {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['empreinte' => boule_empreinte($etat), 'ouverte' => $ouverte]);
    exit;
}

$erreur = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $etat && !$etat['fini']) {
    $etape = (int)($_POST['etape'] ?? 0);
    $max   = $etape > BOULE_DERNIERE_BRIQUE ? BOULE_MAX_REPONSE : BOULE_MAX_BRIQUE;
    $texte = boule_texte_clean($_POST['texte'] ?? '', $max);
    if ($texte === '') {
        $erreur = 'Écris quelque chose avant de valider.';
    } else {
        boule_valider($equipe, $moi, $etape, $texte);   // refus silencieux : la page montre l'état réel
        header('Location: ' . avec_f('boule.php'), true, 303);
        exit;
    }
}

$aMoi    = $etat && !$etat['fini'] && (int)($etat['joueur']['id'] ?? 0) === (int)$moi['id'];
$mesBriques = $etat ? array_filter($etat['briques'], fn($b) => (int)$b['participant_id'] === (int)$moi['id']) : [];
?><!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="icon" href="assets/favicon-couleur.png">
<meta name="robots" content="noindex">
<title>Le prompt boule de neige</title>
<link rel="stylesheet" href="style.css">
</head>
<body>

<header class="site-head">
  <h1>Le prompt boule de neige</h1>
  <p>Un prompt, quatre mains, une IA</p>
</header>

<main class="wrap">

<?php if (!$ouverte): ?>
  <section class="card">
    <h2>Pas encore ouvert</h2>
    <p class="lead">Le formateur lancera le jeu dans un instant.</p>
  </section>

<?php elseif (!$moi): ?>
  <section class="card">
    <h2>D'abord, votre prénom</h2>
    <p class="lead">Indiquez votre prénom sur l'accueil, puis attendez d'être placé dans une équipe.</p>
  </section>

<?php elseif (!$equipe): ?>
  <section class="card">
    <h2>Pas encore d'équipe</h2>
    <p class="lead">Le formateur vous place dans une équipe ; cette page s'ouvrira alors toute seule.</p>
  </section>

<?php else: ?>

  <section class="card boule-tete" style="--equipe:<?= e(equipes()[$equipe]['couleur'] ?? '#1B3E90') ?>">
    <p class="boule-equipe">Équipe <?= badge_equipe($equipe) ?></p>
    <?php if ($etat['tache'] !== ''): ?>
      <p class="boule-tache-label">Votre situation</p>
      <p class="boule-tache"><?= e($etat['tache']) ?></p>
    <?php endif; ?>
    <ol class="boule-ordre">
      <?php foreach (BOULE_ETAPES as $n => $def):
          $j = $etat['briques'][$n]['prenom'] ?? (boule_joueur($etat['membres'], $n)['prenom'] ?? '?');
          $classe = isset($etat['briques'][$n]) ? 'faite' : ($n === $etat['etape'] ? 'en-cours' : ''); ?>
        <li class="<?= $classe ?>"><span><?= e($def['titre']) ?></span> <strong><?= e($j) ?></strong></li>
      <?php endforeach; ?>
    </ol>
  </section>

  <?php if ($etat['fini']): ?>
  <section class="card">
    <h2>Le prompt de votre équipe</h2>
    <?php for ($n = 1; $n <= BOULE_DERNIERE_BRIQUE; $n++): $b = $etat['briques'][$n]; ?>
      <div class="brique"><p class="brique-titre"><?= e(BOULE_ETAPES[$n]['titre']) ?> · <?= e($b['prenom']) ?></p>
        <p class="brique-texte"><?= e($b['texte']) ?></p></div>
    <?php endfor; ?>
  </section>
  <section class="card">
    <h2>La réponse de l'IA</h2>
    <p class="brique-titre">Passée à l'IA par <?= e($etat['briques'][5]['prenom']) ?></p>
    <div class="brique-texte reponse-ia"><?= e($etat['briques'][5]['texte']) ?></div>
  </section>

  <?php elseif ($aMoi): $n = $etat['etape']; $def = BOULE_ETAPES[$n]; ?>
  <section class="card a-toi">
    <h2>C'est à toi ! <span class="muted">Étape <?= $n ?> / <?= count(BOULE_ETAPES) ?></span></h2>

    <?php if ($n > 1): ?>
      <p class="brique-titre">Ce que ton équipe a écrit jusqu'ici</p>
      <div class="brique-texte deja"><?= e(boule_prompt($etat['briques'])) ?></div>
    <?php endif; ?>

    <?php if ($n > BOULE_DERNIERE_BRIQUE): ?>
      <button type="button" class="btn btn-gold" id="btn-copier">Copier le prompt</button>
      <div class="copied-note" id="note-copie">C'est copié ! Colle-le dans ton outil d'IA.</div>
      <pre id="prompt-complet" hidden><?= e(boule_prompt($etat['briques'])) ?></pre>
    <?php endif; ?>

    <form method="post" action="<?= e(avec_f('boule.php')) ?>">
      <?php if ($erreur): ?><div class="err"><?= e($erreur) ?></div><?php endif; ?>
      <input type="hidden" name="etape" value="<?= $n ?>">
      <label class="field" for="texte"><?= e($def['titre']) ?></label>
      <p class="consigne"><?= e($def['consigne']) ?></p>
      <textarea class="field saisie" id="texte" name="texte" required
                rows="<?= $n > BOULE_DERNIERE_BRIQUE ? 10 : 4 ?>"
                maxlength="<?= $n > BOULE_DERNIERE_BRIQUE ? BOULE_MAX_REPONSE : BOULE_MAX_BRIQUE ?>"><?= e($_POST['texte'] ?? '') ?></textarea>
      <button class="btn btn-primary"><?= $n > BOULE_DERNIERE_BRIQUE ? 'Partager la réponse avec l\'équipe' : 'Valider et passer la main' ?></button>
    </form>
  </section>

  <?php else: ?>
  <section class="card">
    <h2>C'est au tour de <?= e($etat['joueur']['prenom'] ?? '…') ?></h2>
    <p class="lead">Étape <?= $etat['etape'] ?> / <?= count(BOULE_ETAPES) ?> : <?= e(BOULE_ETAPES[$etat['etape']]['titre']) ?>.
      Le prompt complet se dévoilera à toute l'équipe à la fin.</p>
  </section>
  <?php foreach ($mesBriques as $n => $b): ?>
  <section class="card">
    <p class="brique-titre">Ta brique · <?= e(BOULE_ETAPES[$n]['titre']) ?></p>
    <p class="brique-texte"><?= e($b['texte']) ?></p>
  </section>
  <?php endforeach; ?>
  <?php endif; ?>

<?php endif; ?>

  <section class="card">
    <a class="btn btn-ghost" href="<?= e(avec_f('index.php')) ?>">Retour à l'accueil</a>
  </section>

</main>

<script>
(function () {
  // La partie avance sans que personne ne touche à cette page : on suit
  var EMPREINTE = <?= json_encode(boule_empreinte($etat)) ?>, OUVERTE = <?= json_encode($ouverte) ?>;
  var enSaisie = !!document.getElementById('texte');
  setInterval(function () {
    fetch(<?= json_encode(avec_f('boule.php?json=etat')) ?>)
      .then(function (r) { return r.json(); })
      .then(function (d) {
        if (d.empreinte === EMPREINTE && d.ouverte === OUVERTE) { return; }
        // Le joueur en train d'écrire n'est pas interrompu, sauf si la main lui a été retirée
        if (enSaisie && document.getElementById('texte').value.trim() !== '' && d.ouverte) {
          if (!confirm('La partie a changé (étape annulée par le formateur ?). Recharger la page ?')) { EMPREINTE = d.empreinte; return; }
        }
        location.reload();
      })
      .catch(function () {});
  }, 3000);

  var copier = document.getElementById('btn-copier');
  if (copier) {
    copier.addEventListener('click', function () {
      var texte = document.getElementById('prompt-complet').textContent;
      var note = document.getElementById('note-copie');
      function fait() { note.classList.add('show'); setTimeout(function () { note.classList.remove('show'); }, 3000); }
      function repli() {
        var ta = document.createElement('textarea');
        ta.value = texte; ta.style.position = 'fixed'; ta.style.opacity = '0';
        document.body.appendChild(ta); ta.select();
        try { document.execCommand('copy'); fait(); } catch (e) {}
        document.body.removeChild(ta);
      }
      if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(texte).then(fait).catch(repli);
      } else { repli(); }
    });
  }
})();
</script>

</body>
</html>
