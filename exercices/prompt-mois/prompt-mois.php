<?php
// Un prompt pour dans un mois, côté stagiaire, dans l'ordre de la consigne :
// la tâche récurrente (objectif du jour rappelé), la version 1 en 7 blocs à
// copier pour la tester, l'analyse, la version 2 (repartie de la version 1),
// puis la copie du prompt final pour sa bibliothèque. Enregistré au fil de la frappe.
require __DIR__ . '/fonctions.php';

const ETAPE_PROMPT_MOIS = 'promptmois';

$ouverte = etape_ouverte(ETAPE_PROMPT_MOIS);
$moi     = participant_courant();
$pret    = $ouverte && $moi && prompt_mois_table_ok();

if (($_GET['json'] ?? '') === 'sauver' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $in = json_decode(file_get_contents('php://input'), true);
    $ok = $pret && is_array($in);
    if ($ok) {
        prompt_mois_sauver((int)$moi['id'], $in);
    }
    http_response_code($ok ? 200 : 409);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => $ok, 'heure' => date('H:i:s')]);
    exit;
}

$fiche    = $pret ? prompt_mois_fiche((int)$moi['id']) : prompt_mois_vide();
$objectif = $pret ? objectif_de((int)$moi['id']) : null;

// Les 7 blocs d'une version, champs nommés v1[objectif]…
function blocs_html(string $version, array $valeurs): string
{
    $h = '<div class="pm-blocs">';
    foreach (PROMPT_MOIS_BLOCS as $cle => [$titre, $aide, $exemple]) {
        $id = $version . '-' . $cle;
        $h .= '<div class="pm-bloc">'
            . '<label class="pm-titre" for="' . e($id) . '">' . e($titre) . '</label>'
            . '<p class="pm-aide">' . e($aide) . '</p>'
            . '<textarea class="field saisie" id="' . e($id) . '" data-version="' . e($version) . '" data-bloc="' . e($cle) . '" rows="3"'
            . ' maxlength="' . PROMPT_MOIS_MAX . '" placeholder="' . e($exemple) . '">' . e($valeurs[$cle]) . '</textarea>'
            . '</div>';
    }
    return $h . '</div>';
}
?><!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="icon" href="assets/favicon-couleur.png">
<meta name="robots" content="noindex">
<title>Un prompt pour dans un mois</title>
<link rel="stylesheet" href="style.css">
</head>
<body>

<header class="site-head">
  <h1>Un prompt pour dans un mois</h1>
  <p>Un prompt réutilisable, testé et amélioré, pour une tâche qui revient souvent</p>
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
    <h2>1. Votre tâche récurrente</h2>
    <?php if ($objectif): ?>
      <p class="pm-objectif">Votre objectif du jour : « <?= e(OBJECTIF_AMORCE . ' ' . $objectif['texte']) ?> »</p>
    <?php endif; ?>
    <p class="consigne">Choisissez une tâche qui revient souvent<?= $objectif ? ', liée si possible à cet objectif' : '' ?>.</p>
    <input class="field" id="tache" type="text" maxlength="<?= PROMPT_MOIS_MAX ?>" value="<?= e($fiche['tache']) ?>"
           placeholder="Ex. : le compte rendu de la réunion d'équipe du lundi">
  </section>

  <section class="card">
    <h2>2. Votre prompt, version 1</h2>
    <p class="consigne">Remplissez les 7 blocs. Ce qui change d'une fois sur l'autre devient une variable entre <strong>[crochets]</strong> :
      [date], [nom du client], [notes]… Puis copiez le prompt et testez-le dans votre outil d'IA.</p>
    <?= blocs_html('v1', $fiche['v1']) ?>
    <button type="button" class="btn btn-gold" data-copier="v1">Copier la version 1 pour la tester</button>
    <div class="copied-note" id="note-v1">Copiée : collez-la dans votre outil d'IA.</div>
    <details class="doc"><summary>Voir le prompt assemblé</summary><pre id="apercu-v1"></pre></details>
  </section>

  <section class="card">
    <h2>3. Analysez le résultat</h2>
    <label class="field" for="bon">Qu'est-ce qui est bon ?</label>
    <textarea class="field saisie" id="bon" rows="3" maxlength="<?= PROMPT_MOIS_MAX ?>"><?= e($fiche['bon']) ?></textarea>
    <label class="field" for="manque">Qu'est-ce qui manque ?</label>
    <textarea class="field saisie" id="manque" rows="3" maxlength="<?= PROMPT_MOIS_MAX ?>"><?= e($fiche['manque']) ?></textarea>
  </section>

  <section class="card">
    <h2>4. Version 2 : améliorez, retestez</h2>
    <p class="consigne">Repartez de votre version 1 et corrigez ce qui manquait.</p>
    <button type="button" class="btn btn-ghost" id="reprendre">Reprendre ma version 1 ici</button>
    <?= blocs_html('v2', $fiche['v2']) ?>
    <button type="button" class="btn btn-gold" data-copier="v2">Copier la version 2 pour la tester</button>
    <div class="copied-note" id="note-v2">Copiée : collez-la dans votre outil d'IA.</div>
    <details class="doc"><summary>Voir le prompt assemblé</summary><pre id="apercu-v2"></pre></details>
  </section>

  <section class="card a-toi">
    <h2>5. Enregistrez-le dans votre bibliothèque de prompts</h2>
    <p class="consigne">Copiez votre prompt final (la version 2, ou la version 1 si vous n'en avez pas fait d'autre) et collez-le là où vous rangez vos prompts : notes, document, outil d'IA. Dans un mois, il suffira de remplacer les [variables].</p>
    <button type="button" class="btn btn-primary" data-copier="final">Copier mon prompt final</button>
    <div class="copied-note" id="note-final">Copié : rangez-le dans votre bibliothèque.</div>
    <p class="sauvegarde" id="sauvegarde"><?= $fiche['tache'] !== '' || implode('', $fiche['v1']) !== '' ? 'Travail enregistré.' : 'Rien d\'enregistré pour l\'instant.' ?></p>
  </section>
<?php endif; ?>

  <section class="card">
    <a class="btn btn-ghost" href="<?= e(avec_f('index.php')) ?>">Retour à l'accueil</a>
  </section>

</main>

<?php if ($pret): ?>
<script>
(function () {
  var URL_SAUVER = <?= json_encode(avec_f('prompt-mois.php?json=sauver')) ?>;
  var TITRES = <?= json_encode(array_map(fn($b) => $b[0], PROMPT_MOIS_BLOCS), JSON_UNESCAPED_UNICODE) ?>;
  var note = document.getElementById('sauvegarde');
  function champ(v, bloc) { return document.querySelector('[data-version="' + v + '"][data-bloc="' + bloc + '"]'); }

  function fiche() {
    var f = { tache: document.getElementById('tache').value, bon: document.getElementById('bon').value,
              manque: document.getElementById('manque').value, v1: {}, v2: {} };
    Object.keys(TITRES).forEach(function (b) { f.v1[b] = champ('v1', b).value; f.v2[b] = champ('v2', b).value; });
    return f;
  }
  // Le prompt assemblé : un titre par bloc rempli
  function prompt(v) {
    return Object.keys(TITRES).map(function (b) {
      var t = champ(v, b).value.trim();
      return t ? '## ' + TITRES[b] + '\n' + t : '';
    }).filter(Boolean).join('\n\n');
  }
  function vide(v) { return prompt(v) === ''; }
  function apercus() {
    document.getElementById('apercu-v1').textContent = prompt('v1') || '(vide)';
    document.getElementById('apercu-v2').textContent = prompt('v2') || '(vide)';
  }
  apercus();

  var attente = null;
  function sauver() {
    clearTimeout(attente);
    attente = null;
    return fetch(URL_SAUVER, {
      method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(fiche())
    }).then(function (r) { return r.json(); }).then(function (d) {
      note.textContent = d.ok ? 'Travail enregistré à ' + d.heure + '.' : 'Enregistrement impossible : l\'exercice est fermé.';
    }).catch(function () { note.textContent = 'Connexion perdue : nouvel essai à la prochaine frappe.'; });
  }
  function plusTard() {
    note.textContent = 'Modification en cours…';
    apercus();
    clearTimeout(attente);
    attente = setTimeout(sauver, 1000);
  }
  document.querySelectorAll('main input.field, main textarea').forEach(function (x) { x.addEventListener('input', plusTard); });
  window.addEventListener('pagehide', function () {
    if (attente) { navigator.sendBeacon(URL_SAUVER, new Blob([JSON.stringify(fiche())], { type: 'application/json' })); }
  });

  // La version 2 repart de la version 1
  document.getElementById('reprendre').addEventListener('click', function () {
    if (!vide('v2') && !confirm('Remplacer votre version 2 par la version 1 ?')) { return; }
    Object.keys(TITRES).forEach(function (b) { champ('v2', b).value = champ('v1', b).value; });
    plusTard();
  });

  // Copie vers le presse-papiers, avec repli pour les anciens téléphones
  document.querySelectorAll('[data-copier]').forEach(function (bouton) {
    bouton.addEventListener('click', function () {
      var quoi = bouton.dataset.copier;
      var texte = quoi === 'final' ? (vide('v2') ? prompt('v1') : prompt('v2')) : prompt(quoi);
      var confirmation = document.getElementById('note-' + quoi);
      if (!texte) { alert('Ce prompt est encore vide.'); return; }
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
  });
})();
</script>
<?php endif; ?>

</body>
</html>
