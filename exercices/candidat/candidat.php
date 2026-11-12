<?php
// Le candidat qui répond à côté, côté stagiaire. Selon la phase :
//   - rédaction : le secrétaire de l'équipe saisit les manques, le prompt
//     réécrit et la réponse de son IA (enregistré au fil de la frappe) ; ses
//     coéquipiers suivent la copie en direct et peuvent prendre la main ;
//   - vote : copies anonymes, un vote, jamais pour la copie de son équipe ;
//   - révélation : la copie gagnante, puis toutes les copies avec leur équipe.
require __DIR__ . '/fonctions.php';

const ETAPE_CANDIDAT = 'candidat';

$ouverte = etape_ouverte(ETAPE_CANDIDAT);
$moi     = participant_courant();
$pret    = $ouverte && $moi && candidat_config() && candidat_table_ok();
$equipe  = $moi && equipe_existe($moi['equipe']) ? $moi['equipe'] : null;

function repondre(array $data, int $code = 200): void
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

if (($_GET['json'] ?? '') === 'sauver' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $in = json_decode(file_get_contents('php://input'), true);
    $ok = $pret && is_array($in) && candidat_sauver($moi, $in);
    repondre(['ok' => $ok, 'heure' => date('H:i:s')], $ok ? 200 : 409);
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $pret) {
    $action = $_POST['action'] ?? '';
    if ($action === 'secretaire') {
        candidat_prendre_la_main($moi);
    } elseif ($action === 'voter') {
        candidat_voter($moi, (int)($_POST['copie'] ?? 0));
    }
    header('Location: ' . avec_f('candidat.php'), true, 303);
    exit;
}

$partie     = $pret ? candidat_partie() : null;
$copie      = $pret && $equipe ? candidat_copie($equipe) : null;
$secretaire = $copie && (int)$copie['secretaire_id'] === (int)$moi['id'];
$enLice     = $partie && $partie['etat'] !== 'redaction' ? candidat_en_lice() : [];
$monVote    = $partie && $partie['etat'] === 'vote' ? candidat_mon_vote((int)$moi['id']) : null;
$voix       = $partie && $partie['etat'] === 'revele' ? candidat_decompte() : [];
$gagnants   = $partie && $partie['etat'] === 'revele' ? candidat_gagnants() : [];

// Rechargement quand la phase change, quand la main passe, et (pour les
// coéquipiers qui suivent) quand la copie avance — jamais sous les doigts du secrétaire
$empreinte = md5(json_encode([$ouverte, $equipe, $partie['etat'] ?? '', $copie['secretaire_id'] ?? 0,
                              $secretaire ? '' : ($copie['updated_at'] ?? '')]));
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
<title>Le candidat qui répond à côté</title>
<link rel="stylesheet" href="style.css">
</head>
<body>

<header class="site-head">
  <h1>Le candidat qui répond à côté</h1>
  <p>Ce candidat IA a mal compris la mission. Qu'est-ce que le recruteur a oublié de lui dire ?</p>
</header>

<main class="wrap<?= $pret ? ' wrap-large' : '' ?>">

<?php if (!$ouverte): ?>
  <section class="card"><h2>Pas encore ouvert</h2><p class="lead">Le formateur lancera le défi dans un instant.</p></section>

<?php elseif (!$moi): ?>
  <section class="card"><h2>D'abord, votre prénom</h2><p class="lead">Indiquez votre prénom sur l'accueil.</p></section>

<?php elseif (!$pret): ?>
  <section class="card"><h2>Bientôt…</h2><p class="lead">Le défi n'est pas encore prêt.</p></section>

<?php elseif ($partie['etat'] === 'redaction'): ?>
  <section class="card"><?= candidat_cas_html() ?></section>

  <?php if (!$equipe): ?>
  <section class="card"><h2>Un défi en équipe</h2><p class="lead">Attendez d'être placé dans votre équipe : vous voterez ensuite pour la meilleure version.</p></section>

  <?php else: ?>
  <section class="card<?= $secretaire ? ' a-toi' : '' ?>">
    <h2>Votre mission, en équipe <?= badge_equipe($equipe) ?></h2>
    <ol class="cd-mission">
      <li>Listez tout ce qui manque au recruteur pour être compris.</li>
      <li>Réécrivez le prompt en une consigne professionnelle exploitable.</li>
      <li>Testez-le dans votre outil d'IA et collez sa réponse.</li>
    </ol>
    <p class="muted">On compare ensuite les résultats : le groupe vote pour la meilleure version (<?= CANDIDAT_POINTS ?> points à l'équipe gagnante).</p>

    <?php if (!$copie || !$copie['secretaire_id']): ?>
      <p class="lead">Un membre de l'équipe tient la plume : c'est lui qui saisit la copie, les autres la suivent en direct.</p>
      <form method="post" action="<?= e(avec_f('candidat.php')) ?>">
        <input type="hidden" name="action" value="secretaire">
        <button class="btn btn-primary">Je tiens la plume pour l'équipe</button>
      </form>

    <?php elseif ($secretaire): ?>
      <p class="lead"><strong>Vous tenez la plume.</strong> Tout est enregistré au fil de la frappe.</p>
      <label class="field" for="manques">1. Ce qui manquait au recruteur</label>
      <textarea class="field saisie" id="manques" data-champ="manques" rows="5" maxlength="<?= CANDIDAT_MAX_TEXTE ?>"
                placeholder="Le destinataire, l'objet du mail, le contexte, le ton…"><?= e($copie['manques']) ?></textarea>
      <label class="field" for="prompt">2. Le prompt réécrit</label>
      <textarea class="field saisie" id="prompt" data-champ="prompt" rows="7" maxlength="<?= CANDIDAT_MAX_TEXTE ?>"><?= e($copie['prompt']) ?></textarea>
      <label class="field" for="resultat">3. La réponse de votre IA</label>
      <textarea class="field saisie" id="resultat" data-champ="resultat" rows="10" maxlength="<?= CANDIDAT_MAX_RESULT ?>"><?= e($copie['resultat']) ?></textarea>
      <p class="sauvegarde" id="sauvegarde">Copie enregistrée.</p>

    <?php else: ?>
      <p class="lead"><strong><?= e($copie['secretaire'] ?? 'Un coéquipier') ?></strong> tient la plume. La copie de l'équipe, en direct :</p>
      <p class="brique-titre">1. Ce qui manquait au recruteur</p>
      <div class="brique-texte deja"><?= $copie['manques'] !== '' ? e($copie['manques']) : '<em>…</em>' ?></div>
      <p class="brique-titre">2. Le prompt réécrit</p>
      <div class="brique-texte deja"><?= $copie['prompt'] !== '' ? e($copie['prompt']) : '<em>…</em>' ?></div>
      <p class="brique-titre">3. La réponse de l'IA</p>
      <div class="brique-texte deja"><?= $copie['resultat'] !== '' ? e($copie['resultat']) : '<em>…</em>' ?></div>
      <form method="post" action="<?= e(avec_f('candidat.php')) ?>" onsubmit="return confirm('Prendre la plume ? <?= e($copie['secretaire'] ?? '') ?> passera en lecture.')">
        <input type="hidden" name="action" value="secretaire">
        <button class="btn btn-ghost">Prendre la plume</button>
      </form>
    <?php endif; ?>
  </section>
  <?php endif; ?>

<?php elseif ($partie['etat'] === 'vote'): ?>
  <section class="card">
    <h2>Votez pour la meilleure version</h2>
    <p class="lead">Copies anonymes. Vous ne pouvez pas voter pour celle de votre équipe ; vous pouvez changer d'avis tant que le vote est ouvert.</p>
  </section>
  <?php if (!$enLice): ?>
    <section class="card"><p class="lead">Aucune copie n'a été rendue.</p></section>
  <?php endif; ?>
  <?php foreach ($enLice as $c): $mienne = $c['equipe'] === $equipe; $choisie = $monVote === (int)$c['id']; ?>
  <section class="card copie<?= $choisie ? ' choisie' : '' ?>">
    <p class="copie-lettre">Version <?= e($c['lettre']) ?><?= $mienne ? ' · votre équipe' : '' ?></p>
    <p class="brique-titre">Le prompt</p>
    <div class="brique-texte deja"><?= e($c['prompt']) ?></div>
    <p class="brique-titre">La réponse de l'IA</p>
    <div class="brique-texte reponse-ia"><?= $c['resultat'] !== '' ? e($c['resultat']) : '<em>(pas de réponse collée)</em>' ?></div>
    <?php if (!$mienne): ?>
    <form method="post" action="<?= e(avec_f('candidat.php')) ?>">
      <input type="hidden" name="action" value="voter">
      <input type="hidden" name="copie" value="<?= (int)$c['id'] ?>">
      <button class="btn <?= $choisie ? 'btn-gold' : 'btn-primary' ?>"><?= $choisie ? '✓ Mon vote : version ' . e($c['lettre']) : 'Voter pour la version ' . e($c['lettre']) ?></button>
    </form>
    <?php endif; ?>
  </section>
  <?php endforeach; ?>

<?php else: /* revele */ ?>
  <section class="card">
    <h2>Résultat</h2>
    <?php if ($gagnants): ?>
      <p class="lead">Victoire de <?= implode(' et ', array_map('badge_equipe', $gagnants)) ?><?= points_actifs() ? ' · +' . CANDIDAT_POINTS . ' pts' : '' ?></p>
    <?php else: ?>
      <p class="lead">Pas de vote, pas de vainqueur.</p>
    <?php endif; ?>
  </section>
  <?php foreach ($enLice as $c): ?>
  <section class="card copie<?= in_array($c['equipe'], $gagnants, true) ? ' gagnante' : '' ?>">
    <p class="copie-lettre">Version <?= e($c['lettre']) ?> · <?= badge_equipe($c['equipe']) ?> · <?= $voix[(int)$c['id']] ?? 0 ?> voix</p>
    <p class="brique-titre">Ce qui manquait</p>
    <div class="brique-texte deja"><?= e($c['manques']) ?></div>
    <p class="brique-titre">Le prompt</p>
    <div class="brique-texte deja"><?= e($c['prompt']) ?></div>
    <p class="brique-titre">La réponse de l'IA</p>
    <div class="brique-texte reponse-ia"><?= e($c['resultat']) ?></div>
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
  var URL_ETAT = <?= json_encode(avec_f('candidat.php?json=etat')) ?>;
  var URL_SAUVER = <?= json_encode(avec_f('candidat.php?json=sauver')) ?>;
  var champs = document.querySelectorAll('[data-champ]');
  var note = document.getElementById('sauvegarde');
  var attente = null, envoi = false;

  function copie() {
    var c = {};
    champs.forEach(function (x) { c[x.dataset.champ] = x.value; });
    return c;
  }
  function sauver() {
    clearTimeout(attente);
    attente = null;
    envoi = true;
    return fetch(URL_SAUVER, {
      method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(copie())
    }).then(function (r) { return r.json(); }).then(function (d) {
      note.textContent = d.ok ? 'Copie enregistrée à ' + d.heure + '.' : 'Vous ne tenez plus la plume, ou la rédaction est close.';
    }).catch(function () { note.textContent = 'Connexion perdue : nouvel essai à la prochaine frappe.'; })
      .then(function () { envoi = false; });
  }
  champs.forEach(function (x) {
    x.addEventListener('input', function () {
      note.textContent = 'Modification en cours…';
      clearTimeout(attente);
      attente = setTimeout(sauver, 1000);
    });
  });
  window.addEventListener('pagehide', function () {
    if (attente) { navigator.sendBeacon(URL_SAUVER, new Blob([JSON.stringify(copie())], { type: 'application/json' })); }
  });

  setInterval(function () {
    fetch(URL_ETAT).then(function (r) { return r.json(); }).then(function (d) {
      if (d.empreinte === EMPREINTE || envoi) { return; }
      // le secrétaire enregistre ce qu'il tapait avant que la page change
      (attente ? sauver() : Promise.resolve()).then(function () { location.reload(); });
    }).catch(function () {});
  }, 3000);
})();
</script>
<?php endif; ?>

</body>
</html>
