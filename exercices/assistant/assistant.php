<?php
// La fiche de poste de votre assistant, côté stagiaire : les rubriques de la
// fiche, enregistrées au fil de la frappe, et un bouton pour copier
// l'assistant dans son outil d'IA et l'essayer. Toujours modifiable : on y
// revient en fin de journée.
require __DIR__ . '/fonctions.php';

const ETAPE_ASSISTANT = 'assistant';

$ouverte = etape_ouverte(ETAPE_ASSISTANT);
$moi     = participant_courant();
$pret    = $ouverte && $moi && assistant_table_ok();

// Enregistrement au fil de la frappe
if (($_GET['json'] ?? '') === 'sauver' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $in = json_decode(file_get_contents('php://input'), true);
    $ok = $pret && is_array($in);
    if ($ok) {
        assistant_sauver((int)$moi['id'], $in);
    }
    http_response_code($ok ? 200 : 409);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => $ok, 'heure' => date('H:i:s')]);
    exit;
}

$fiche = $pret ? assistant_fiche((int)$moi['id']) : [];
?><!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="icon" href="assets/favicon-couleur.png">
<meta name="robots" content="noindex">
<title>La fiche de poste de votre assistant</title>
<link rel="stylesheet" href="style.css">
</head>
<body>

<header class="site-head">
  <h1>La fiche de poste de votre assistant</h1>
  <p>Un assistant IA taillé pour votre travail</p>
</header>

<main class="wrap<?= $pret ? ' wrap-large' : '' ?>">

<?php if (!$ouverte): ?>
  <section class="card"><h2>Pas encore ouvert</h2><p class="lead">Le formateur ouvrira l'exercice dans un instant.</p></section>

<?php elseif (!$moi): ?>
  <section class="card"><h2>D'abord, votre prénom</h2><p class="lead">Indiquez votre prénom sur l'accueil.</p></section>

<?php elseif (!$pret): ?>
  <section class="card"><h2>Bientôt…</h2><p class="lead">L'exercice n'est pas encore prêt.</p></section>

<?php else: ?>
  <section class="card">
    <p class="consigne">Vous recrutez un assistant IA pour une tâche que vous faites souvent. Rédigez sa fiche de poste,
      rubrique par rubrique : plus elle est précise, plus il sera utile. Tout est enregistré au fil de la frappe.</p>
  </section>

  <div class="assistant-grille">
    <?php foreach (ASSISTANT_RUBRIQUES as $cle => [$titre, $aide, $exemple]): ?>
    <section class="card assistant-rubrique<?= $cle === 'documents' ? ' assistant-documents' : '' ?>">
      <label class="assistant-titre" for="r-<?= e($cle) ?>"><?= e($titre) ?></label>
      <p class="assistant-aide"><?= e($aide) ?></p>
      <textarea class="field saisie" id="r-<?= e($cle) ?>" data-rubrique="<?= e($cle) ?>" rows="<?= $cle === 'contexte' ? 5 : 3 ?>"
                maxlength="<?= ASSISTANT_MAX ?>" placeholder="<?= e($exemple) ?>"><?= e($fiche[$cle]) ?></textarea>
    </section>
    <?php endforeach; ?>
  </div>

  <section class="card a-toi">
    <h2>Essayez votre assistant</h2>
    <p class="consigne">Copiez la fiche, collez-la dans votre outil d'IA (dans un projet, un GPT personnalisé ou en début
      de conversation), joignez vos documents anonymisés, puis posez-lui une vraie demande. Ajustez la fiche selon ses réponses.</p>
    <p class="sauvegarde" id="sauvegarde"><?= assistant_vide($fiche) ? 'Rien d\'enregistré pour l\'instant.' : 'Fiche enregistrée.' ?></p>
    <button class="btn btn-gold" id="copier">Copier mon assistant</button>
    <div class="copied-note" id="note-copie">Copié : collez-le dans votre outil d'IA.</div>
    <details class="doc">
      <summary>Voir le texte copié</summary>
      <pre id="apercu"></pre>
    </details>
  </section>
<?php endif; ?>

  <section class="card">
    <a class="btn btn-ghost" href="<?= e(avec_f('index.php')) ?>">Retour à l'accueil</a>
  </section>

</main>

<?php if ($pret): ?>
<script>
(function () {
  var URL_SAUVER = <?= json_encode(avec_f('assistant.php?json=sauver')) ?>;
  // Titres du texte copié : même mise en forme que assistant_prompt() en PHP
  var TITRES = <?= json_encode(array_map(fn($r) => $r[3], ASSISTANT_RUBRIQUES), JSON_UNESCAPED_UNICODE) ?>;
  var champs = document.querySelectorAll('[data-rubrique]');
  var note = document.getElementById('sauvegarde');
  var apercu = document.getElementById('apercu');

  function fiche() {
    var f = {};
    champs.forEach(function (c) { f[c.dataset.rubrique] = c.value; });
    return f;
  }
  function prompt() {
    var f = fiche(), parties = ['Tu es mon assistant. Voici ta fiche de poste.'];
    Object.keys(TITRES).forEach(function (cle) {
      var t = (f[cle] || '').trim();
      if (!t) { return; }
      if (cle === 'documents') {
        t = t.split('\n').map(function (l) { return l.trim(); }).filter(Boolean)
             .map(function (l) { return '- ' + l.replace(/^[-•\s]+/, ''); }).join('\n');
      }
      parties.push('## ' + TITRES[cle] + '\n' + t);
    });
    return parties.join('\n\n');
  }
  function majApercu() { apercu.textContent = prompt(); }
  majApercu();

  var attente = null;
  function sauver() {
    clearTimeout(attente);
    return fetch(URL_SAUVER, {
      method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(fiche())
    }).then(function (r) { return r.json(); }).then(function (d) {
      note.textContent = d.ok ? 'Fiche enregistrée à ' + d.heure + '.' : 'Enregistrement impossible : l\'exercice est fermé.';
    }).catch(function () { note.textContent = 'Connexion perdue : nouvel essai à la prochaine frappe.'; });
  }
  champs.forEach(function (c) {
    c.addEventListener('input', function () {
      note.textContent = 'Modification en cours…';
      majApercu();
      clearTimeout(attente);
      attente = setTimeout(sauver, 1000);
    });
  });
  window.addEventListener('pagehide', function () {
    if (attente) { navigator.sendBeacon(URL_SAUVER, new Blob([JSON.stringify(fiche())], { type: 'application/json' })); }
  });

  // Copie vers le presse-papiers, avec repli pour les anciens téléphones
  document.getElementById('copier').addEventListener('click', function () {
    var texte = prompt(), confirmation = document.getElementById('note-copie');
    function fait() {
      confirmation.classList.add('show');
      setTimeout(function () { confirmation.classList.remove('show'); }, 3000);
    }
    function secours() {
      var t = document.createElement('textarea');
      t.value = texte; t.style.position = 'fixed'; t.style.opacity = '0';
      document.body.appendChild(t); t.select();
      try { document.execCommand('copy'); fait(); } catch (e) {}
      document.body.removeChild(t);
    }
    if (navigator.clipboard && navigator.clipboard.writeText) {
      navigator.clipboard.writeText(texte).then(fait, secours);
    } else { secours(); }
    if (attente) { sauver(); }
  });
})();
</script>
<?php endif; ?>

</body>
</html>
