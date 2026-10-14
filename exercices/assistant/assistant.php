<?php
// La fiche de poste de votre assistant, côté stagiaire : les rubriques de la
// fiche, enregistrées au fil de la frappe, et un bouton pour copier
// l'assistant dans son outil d'IA et l'essayer. Toujours modifiable : on y
// revient en fin de journée. Quand le formateur ouvre le speed-dating, la
// grille pour noter l'assistant d'un binôme apparaît, et les retours reçus
// s'affichent sous la fiche.
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

$speed = $pret && assistant_speed_ouvert();

// Grille du speed-dating
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $speed && ($_POST['action'] ?? '') === 'evaluer') {
    $cible = (int)($_POST['cible'] ?? 0);
    $ok = assistant_evaluer((int)$moi['id'], $cible, (array)($_POST['grille'] ?? []), (string)($_POST['remarque'] ?? ''));
    header('Location: ' . avec_f('assistant.php?' . ($ok ? 'note=' . $cible : 'erreur=1')) . '#speed-dating', true, 303);
    exit;
}

$fiche   = $pret ? assistant_fiche((int)$moi['id']) : [];
$recus   = $pret ? assistant_retours((int)$moi['id']) : [];
$donnes  = $speed ? array_column(assistant_retours((int)$moi['id'], false), null, 'cible_id') : [];
$binomes = $speed ? array_values(array_filter(participants_seance(), fn($p) => (int)$p['id'] !== (int)$moi['id'])) : [];
$choisi  = (int)($_GET['note'] ?? 0);

// Empreinte : la page propose de se mettre à jour quand le speed-dating s'ouvre
// ou qu'un retour arrive, sans recharger sous les doigts du stagiaire
$empreinte = md5(json_encode([$speed, array_map(fn($r) => [$r['id'], $r['updated_at']], $recus)]));
if (($_GET['json'] ?? '') === 'etat') {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['empreinte' => $empreinte]);
    exit;
}

// Ligne de grille d'un retour : ✓ / ✗, en alerte quand la réponse signale un problème
function ligne_grille(string $col, bool $oui): string
{
    [$question, $alerte] = ASSISTANT_GRILLE[$col];
    return '<li class="' . ($oui === $alerte ? 'grille-alerte' : 'grille-ok') . '">'
         . '<span aria-hidden="true">' . ($oui ? '✓' : '✗') . '</span> ' . e($question) . ' <strong>' . ($oui ? 'Oui' : 'Non') . '</strong></li>';
}
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
      <?php if ($cle === 'documents'): ?>
      <details class="doc trois-questions">
        <summary>Le test des 3 questions</summary>
        <p class="muted">Avant de coller une information dans une IA :</p>
        <ol>
          <?php foreach (ASSISTANT_TROIS_QUESTIONS as [$q, $explication]): ?>
          <li><strong><?= e($q) ?></strong> <?= e($explication) ?></li>
          <?php endforeach; ?>
        </ol>
      </details>
      <?php endif; ?>
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

  <?php if ($recus): ?>
  <section class="card">
    <h2>Les retours sur votre assistant</h2>
    <p class="consigne">Ce que vos testeurs ont constaté : de quoi corriger la fiche.</p>
    <?php foreach ($recus as $r): ?>
    <div class="retour">
      <p class="retour-qui">Testé par <?= e($r['prenom']) ?> <?= $r['equipe'] ? badge_equipe($r['equipe']) : '' ?></p>
      <ul class="grille-resultat">
        <?php foreach (array_keys(ASSISTANT_GRILLE) as $col): ?><?= ligne_grille($col, (bool)$r[$col]) ?><?php endforeach; ?>
      </ul>
      <?php if ($r['remarque'] !== ''): ?><p class="retour-remarque">« <?= e($r['remarque']) ?> »</p><?php endif; ?>
    </div>
    <?php endforeach; ?>
  </section>
  <?php endif; ?>

  <?php if ($speed): ?>
  <section class="card a-toi" id="speed-dating">
    <h2>Speed-dating d'assistants</h2>
    <p class="consigne">Changez de poste avec votre binôme. Testez SON assistant avec une vraie demande, puis cochez ce que vous avez constaté.</p>
    <?php if (isset($_GET['erreur'])): ?>
      <p class="err">Choisissez votre binôme dans la liste.</p>
    <?php elseif ($choisi && isset($donnes[$choisi])): ?>
      <p class="merci">Merci ! Votre retour est transmis à <?= e($donnes[$choisi]['prenom']) ?>.</p>
    <?php endif; ?>
    <?php if (!$binomes): ?>
      <p class="lead">Personne d'autre n'est encore inscrit à la séance.</p>
    <?php else: ?>
    <form method="post" action="<?= e(avec_f('assistant.php')) ?>" id="form-grille" class="form-grille">
      <input type="hidden" name="action" value="evaluer">
      <label class="field" for="cible">L'assistant de</label>
      <select class="field" id="cible" name="cible" required>
        <option value="">Choisir mon binôme…</option>
        <?php foreach ($binomes as $b): ?>
        <option value="<?= (int)$b['id'] ?>"<?= (int)$b['id'] === $choisi ? ' selected' : '' ?>>
          <?= e($b['prenom']) ?><?= $b['equipe'] ? ' (' . e(equipes()[$b['equipe']]['nom'] ?? $b['equipe']) . ')' : '' ?><?= isset($donnes[(int)$b['id']]) ? ' ✓ déjà noté' : '' ?>
        </option>
        <?php endforeach; ?>
      </select>
      <div class="grille">
        <?php foreach (ASSISTANT_GRILLE as $col => [$question]): ?>
        <label class="grille-case"><input type="checkbox" name="grille[<?= e($col) ?>]" value="1" data-col="<?= e($col) ?>"> <?= e($question) ?></label>
        <?php endforeach; ?>
      </div>
      <label class="field" for="remarque">Une remarque pour son auteur (facultatif)</label>
      <textarea class="field saisie" id="remarque" name="remarque" rows="2" maxlength="<?= ASSISTANT_MAX_REMARQUE ?>"
                placeholder="Ex. : il a donné un prix sans me le demander."></textarea>
      <button class="btn btn-primary">Envoyer mon retour</button>
    </form>
    <?php endif; ?>
  </section>
  <?php endif; ?>
<?php endif; ?>

<div class="bandeau" id="bandeau" hidden>
  <span><?= $speed ? 'Nouveau retour sur votre assistant.' : 'Le speed-dating est ouvert.' ?></span>
  <button class="btn btn-gold" id="btn-afficher">Afficher</button>
</div>

  <section class="card">
    <a class="btn btn-ghost" href="<?= e(avec_f('index.php')) ?>">Retour à l'accueil</a>
  </section>

</main>

<?php if ($pret): ?>
<script>
(function () {
  var URL_SAUVER = <?= json_encode(avec_f('assistant.php?json=sauver')) ?>;
  var URL_ETAT = <?= json_encode(avec_f('assistant.php?json=etat')) ?>;
  var EMPREINTE = <?= json_encode($empreinte) ?>;
  // Grilles déjà remplies par ce testeur, pour les retrouver en changeant de binôme
  var DONNES = <?= json_encode(array_map(fn($r) => array_intersect_key($r, ASSISTANT_GRILLE + ['remarque' => 1]), $donnes), JSON_UNESCAPED_UNICODE) ?>;
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

  // Grille : reprend le retour déjà donné au binôme choisi
  var cible = document.getElementById('cible');
  function remplirGrille() {
    var d = DONNES[cible.value] || {};
    document.querySelectorAll('[data-col]').forEach(function (c) { c.checked = d[c.dataset.col] == 1; });
    document.getElementById('remarque').value = d.remarque || '';
  }
  if (cible) { cible.addEventListener('change', remplirGrille); remplirGrille(); }

  // Speed-dating ouvert ou nouveau retour : on propose de mettre à jour, la fiche est enregistrée avant
  var bandeau = document.getElementById('bandeau');
  document.getElementById('btn-afficher').addEventListener('click', function () {
    (attente ? sauver() : Promise.resolve()).then(function () { location.reload(); });
  });
  setInterval(function () {
    fetch(URL_ETAT).then(function (r) { return r.json(); }).then(function (d) {
      if (d.empreinte !== EMPREINTE) { bandeau.hidden = false; }
    }).catch(function () {});
  }, 10000);
})();
</script>
<?php endif; ?>

</body>
</html>
