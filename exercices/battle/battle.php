<?php
// Prompt Battle, côté stagiaire. Selon la manche et son rôle :
//   - volontaire en jeu : la tâche, le chrono, son prompt et le résultat de son
//     IA, enregistrés au fil de la frappe jusqu'à la fin du chrono ;
//   - pendant le vote : les copies anonymes et ses bulletins (sauf volontaires) ;
//   - à la révélation : la copie gagnante et les prompts, pour le débrief.
require __DIR__ . '/fonctions.php';

const ETAPE_BATTLE = 'battle';

$ouverte = etape_ouverte(ETAPE_BATTLE);
$moi     = participant_courant();
$m       = $ouverte && $moi && battle_table_ok() ? battle_courante() : null;
$role    = $m ? battle_role($m, $moi) : null;

function repondre(array $data, int $code = 200): void
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

// Empreinte : la page se recharge quand la manche change d'état
$empreinte = md5(json_encode([$ouverte, $m['id'] ?? 0, $m['etat'] ?? '', $role]));

if (($_GET['json'] ?? '') === 'etat') {
    repondre(['empreinte' => $empreinte, 'reste' => $m ? battle_reste($m) : 0]);
}

// Enregistrement au fil de la frappe (volontaire, pendant le chrono)
if (($_GET['json'] ?? '') === 'sauver' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $in = json_decode(file_get_contents('php://input'), true) ?: [];
    $ok = $m && battle_sauver($m, $moi,
        battle_texte_clean($in['prompt'] ?? '', BATTLE_MAX_PROMPT),
        battle_texte_clean($in['resultat'] ?? '', BATTLE_MAX_RESULT));
    repondre(['ok' => $ok, 'heure' => date('H:i:s')], $ok ? 200 : 409);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $m && $moi) {
    if (($_POST['action'] ?? '') === 'voter') {
        battle_voter($m, $moi, (int)($_POST['copie'] ?? 0));
    }
    header('Location: ' . avec_f('battle.php'), true, 303);
    exit;
}

$copie   = $m && $role ? battle_ma_copie($m, $moi) : null;
$enLice  = $m && in_array($m['etat'], ['vote', 'revele'], true) ? battle_copies_en_lice((int)$m['id']) : [];
$monVote = $m && $m['etat'] === 'vote' && !$role ? battle_mon_vote($m, $moi) : null;
$voix    = $m && $m['etat'] === 'revele' ? battle_decompte((int)$m['id']) : [];
$gagnants = $m && $m['etat'] === 'revele' ? battle_gagnants((int)$m['id']) : [];
?><!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="icon" href="assets/favicon-couleur.png">
<meta name="robots" content="noindex">
<title>Prompt Battle</title>
<link rel="stylesheet" href="style.css">
</head>
<body>

<header class="site-head">
  <h1>Prompt Battle</h1>
  <p>Une tâche surprise, 5 minutes, le groupe tranche</p>
</header>

<main class="wrap">

<?php if (!$ouverte): ?>
  <section class="card"><h2>Pas encore ouvert</h2><p class="lead">Le formateur lancera la battle dans un instant.</p></section>

<?php elseif (!$moi): ?>
  <section class="card"><h2>D'abord, votre prénom</h2><p class="lead">Indiquez votre prénom sur l'accueil.</p></section>

<?php elseif (!$m): ?>
  <section class="card"><h2>Bientôt…</h2><p class="lead">Le formateur prépare la première manche.</p></section>

<?php elseif ($m['etat'] === 'preparation'): ?>
  <section class="card<?= $role ? ' a-toi' : '' ?>">
    <h2>Manche <?= (int)$m['numero'] ?> en préparation</h2>
    <?php if ($role): ?>
      <p class="lead"><strong>Tu es volontaire</strong> pour l'équipe <?= badge_equipe($m['equipe_' . $role]) ?> !
        Garde ton outil d'IA ouvert : la tâche s'affichera ici au top départ.</p>
    <?php else: ?>
      <p class="lead">Deux volontaires vont s'affronter. Vous voterez pour la meilleure copie.</p>
    <?php endif; ?>
  </section>

<?php elseif ($m['etat'] === 'jeu'): ?>
  <section class="card battle-tache">
    <p class="boule-tache-label">Manche <?= (int)$m['numero'] ?> · la tâche</p>
    <p class="boule-tache"><?= e($m['tache']) ?></p>
    <p class="chrono" id="chrono" data-reste="<?= battle_reste($m) ?>"></p>
  </section>
  <?php if ($role): ?>
  <section class="card a-toi">
    <h2>À toi de jouer !</h2>
    <p class="consigne">Écris ton prompt, passe-le à ton outil d'IA, puis colle sa réponse. Tout est enregistré au fil de la frappe ; à la fin du chrono, ta copie part au vote telle quelle.</p>
    <label class="field" for="prompt">Ton prompt</label>
    <textarea class="field saisie" id="prompt" rows="5" maxlength="<?= BATTLE_MAX_PROMPT ?>"><?= e($copie['prompt'] ?? '') ?></textarea>
    <label class="field" for="resultat">La réponse de l'IA</label>
    <textarea class="field saisie" id="resultat" rows="10" maxlength="<?= BATTLE_MAX_RESULT ?>"><?= e($copie['resultat'] ?? '') ?></textarea>
    <p class="sauvegarde" id="sauvegarde"><?= $copie ? 'Copie enregistrée.' : 'Rien d\'enregistré pour l\'instant.' ?></p>
  </section>
  <?php else: ?>
  <section class="card">
    <h2>Les volontaires sont à l'œuvre</h2>
    <p class="lead">Et vous, comment l'auriez-vous demandé ? Le vote s'ouvre à la fin du chrono.</p>
  </section>
  <?php endif; ?>

<?php elseif ($m['etat'] === 'vote'): ?>
  <section class="card battle-tache">
    <p class="boule-tache-label">Manche <?= (int)$m['numero'] ?> · la tâche</p>
    <p class="boule-tache"><?= e($m['tache']) ?></p>
  </section>
  <?php if ($role): ?>
  <section class="card a-toi">
    <h2>Le groupe vote</h2>
    <p class="lead">Ta copie est en lice, anonymement. Verdict dans un instant !</p>
  </section>
  <?php elseif (!$enLice): ?>
  <section class="card"><h2>Aucune copie</h2><p class="lead">Aucun volontaire n'a rendu de copie à temps.</p></section>
  <?php else: ?>
  <section class="card">
    <h2>Votez pour la meilleure réponse</h2>
    <p class="lead">Copies anonymes. Vous pouvez changer d'avis tant que le vote est ouvert.</p>
  </section>
    <?php foreach ($enLice as $c): $choisie = $monVote === (int)$c['id']; ?>
    <section class="card copie<?= $choisie ? ' choisie' : '' ?>">
      <p class="copie-lettre">Copie <?= e($c['lettre']) ?></p>
      <div class="brique-texte reponse-ia"><?= e($c['resultat']) ?></div>
      <form method="post" action="<?= e(avec_f('battle.php')) ?>">
        <input type="hidden" name="action" value="voter">
        <input type="hidden" name="copie" value="<?= (int)$c['id'] ?>">
        <button class="btn <?= $choisie ? 'btn-gold' : 'btn-primary' ?>"><?= $choisie ? '✓ Mon vote : copie ' . e($c['lettre']) : 'Voter pour la copie ' . e($c['lettre']) ?></button>
      </form>
    </section>
    <?php endforeach; ?>
  <?php endif; ?>

<?php else: /* revele */ ?>
  <section class="card battle-tache">
    <p class="boule-tache-label">Manche <?= (int)$m['numero'] ?> · résultat</p>
    <p class="boule-tache"><?= e($m['tache']) ?></p>
    <?php if ($gagnants): ?>
      <p class="lead">Victoire de <?= implode(' et ', array_map('badge_equipe', $gagnants)) ?>
        <?= points_actifs() ? '· +' . BATTLE_POINTS . ' pts' : '' ?></p>
    <?php else: ?>
      <p class="lead">Pas de vote, pas de vainqueur.</p>
    <?php endif; ?>
  </section>
  <?php foreach ($enLice as $c): ?>
  <section class="card copie<?= in_array($c['equipe'], $gagnants, true) ? ' gagnante' : '' ?>">
    <p class="copie-lettre">Copie <?= e($c['lettre']) ?> · <?= badge_equipe($c['equipe']) ?> <?= e($c['prenom']) ?>
      · <?= $voix[(int)$c['id']] ?? 0 ?> voix</p>
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

<script>
(function () {
  var EMPREINTE = <?= json_encode($empreinte) ?>;
  var URL_ETAT = <?= json_encode(avec_f('battle.php?json=etat')) ?>;
  var URL_SAUVER = <?= json_encode(avec_f('battle.php?json=sauver')) ?>;

  // Chrono local, recalé sur le serveur à chaque sondage
  var chrono = document.getElementById('chrono');
  var fin = chrono ? Date.now() + 1000 * (+chrono.dataset.reste) : 0;
  function afficherChrono() {
    if (!chrono) { return; }
    var s = Math.max(0, Math.round((fin - Date.now()) / 1000));
    chrono.textContent = Math.floor(s / 60) + ':' + String(s % 60).padStart(2, '0');
    chrono.classList.toggle('urgent', s <= 30);
  }
  if (chrono) { afficherChrono(); setInterval(afficherChrono, 250); }

  // Copie du volontaire : enregistrée 1 s après la dernière frappe, et à la fin du chrono
  var prompt = document.getElementById('prompt'), resultat = document.getElementById('resultat');
  var note = document.getElementById('sauvegarde');
  var attente = null, envoiEnCours = false;
  function sauver() {
    if (!prompt) { return Promise.resolve(); }
    clearTimeout(attente);
    envoiEnCours = true;
    return fetch(URL_SAUVER, {
      method: 'POST', headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ prompt: prompt.value, resultat: resultat.value })
    }).then(function (r) { return r.json(); }).then(function (d) {
      note.textContent = d.ok ? 'Copie enregistrée à ' + d.heure + '.' : 'Temps écoulé : la copie n\'est plus modifiable.';
    }).catch(function () { note.textContent = 'Connexion perdue : nouvel essai à la prochaine frappe.'; })
      .then(function () { envoiEnCours = false; });
  }
  if (prompt) {
    [prompt, resultat].forEach(function (champ) {
      champ.addEventListener('input', function () {
        note.textContent = 'Modification en cours…';
        clearTimeout(attente);
        attente = setTimeout(sauver, 1000);
      });
    });
  }

  var dernierEnvoi = false;
  setInterval(function () {
    if (chrono && !dernierEnvoi && fin - Date.now() < 2500) {   // dernier enregistrement juste avant la fin
      dernierEnvoi = true;
      sauver();
    }
    fetch(URL_ETAT).then(function (r) { return r.json(); }).then(function (d) {
      if (chrono && d.reste > 0) { fin = Date.now() + 1000 * d.reste; }
      if (d.empreinte !== EMPREINTE && !envoiEnCours) { location.reload(); }
    }).catch(function () {});
  }, 2000);
})();
</script>

</body>
</html>
