<?php
// Atelier workflow, côté stagiaire : un tableau blanc où l'on pose des
// déclencheurs (ronds), des étapes (boîtes) et des conditions (losanges),
// reliés par des flèches. Enregistré au fil de l'eau ; export en image.
// Sur téléphone, le schéma s'affiche en lecture seule.
require __DIR__ . '/fonctions.php';

const ETAPE_WORKFLOW = 'workflow';

$ouverte = etape_ouverte(ETAPE_WORKFLOW);
$moi     = participant_courant();
$pret    = $ouverte && $moi && workflow_table_ok();

if (($_GET['json'] ?? '') === 'sauver' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $in = json_decode(file_get_contents('php://input'), true);
    $ok = $pret && is_array($in);
    if ($ok) {
        workflow_sauver((int)$moi['id'], $in);
    }
    http_response_code($ok ? 200 : 409);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => $ok, 'heure' => date('H:i:s')]);
    exit;
}

$schema = $pret ? workflow_de((int)$moi['id']) : workflow_vide();
?><!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="icon" href="assets/favicon-couleur.png">
<meta name="robots" content="noindex">
<title>Atelier workflow</title>
<link rel="stylesheet" href="style.css">
</head>
<body>

<header class="site-head">
  <h1>Atelier workflow</h1>
  <p>Dessinez un processus : ce qui le déclenche, ses étapes, ses conditions</p>
</header>

<main class="wrap<?= $pret ? ' wrap-large wf-page' : '' ?>">

<?php if (!$ouverte): ?>
  <section class="card"><h2>Pas encore ouvert</h2><p class="lead">Le formateur ouvrira l'atelier dans un instant.</p></section>

<?php elseif (!$moi): ?>
  <section class="card"><h2>D'abord, votre prénom</h2><p class="lead">Indiquez votre prénom sur l'accueil.</p></section>

<?php elseif (!$pret): ?>
  <section class="card"><h2>Bientôt…</h2><p class="lead">L'atelier n'est pas encore prêt.</p></section>

<?php else: ?>
  <section class="card wf-outils">
    <label class="field" for="titre">Le processus</label>
    <input class="field" id="titre" type="text" maxlength="<?= WORKFLOW_MAX_TITRE ?>" value="<?= e($schema['titre']) ?>"
           placeholder="Ex. : traiter une demande de devis">
    <p class="wf-telephone">Sur téléphone, le schéma s'affiche en lecture seule : passez sur un ordinateur ou une tablette pour le dessiner.</p>
  </section>

  <section class="card wf-plateau">
    <div class="wf-palette">
      <span class="wf-aide">Glissez une forme sur le tableau (ou touchez-la) :</span>
      <button type="button" class="wf-forme" data-type="declencheur"><span class="wf-rond"></span>Déclencheur</button>
      <button type="button" class="wf-forme" data-type="etape"><span class="wf-boite"></span>Étape</button>
      <button type="button" class="wf-forme" data-type="condition"><span class="wf-losange"></span>Condition</button>
    </div>
    <div class="wf-selection" id="selection">
      <span class="wf-aide" id="aide">Touchez une forme pour écrire son texte. Pour relier deux formes, tirez la pastille bleue de l'une vers l'autre.</span>
      <input class="field" id="texte" type="text" maxlength="<?= WORKFLOW_MAX_TEXTE ?>" placeholder="Texte de la forme" hidden>
      <button type="button" class="btn btn-ghost" id="inverser" hidden>Inverser Oui / Non</button>
      <button type="button" class="btn btn-ghost" id="supprimer" hidden>Supprimer</button>
    </div>
    <svg id="tableau" class="wf-tableau" role="img" aria-label="Schéma du processus"></svg>
    <p class="wf-message" id="message" role="status"></p>
    <div class="wf-bas">
      <span class="sauvegarde" id="sauvegarde"><?= $schema['noeuds'] ? 'Schéma enregistré.' : 'Rien d\'enregistré pour l\'instant.' ?></span>
      <button type="button" class="btn btn-gold" id="exporter">Télécharger en image</button>
      <button type="button" class="btn btn-ghost" id="vider">Tout effacer</button>
    </div>
  </section>

  <section class="card">
    <h2>Légende</h2>
    <p class="lead"><strong>Rond</strong> : déclencheur, ce qui lance le processus (un mail reçu, chaque lundi à 8 h…).
      <strong>Boîte</strong> : une étape. <strong>Losange</strong> : une condition, avec deux sorties, Oui et Non.
      Deux déclencheurs ne se relient pas entre eux.</p>
  </section>
<?php endif; ?>

  <section class="card">
    <a class="btn btn-ghost" href="<?= e(avec_f('index.php')) ?>">Retour à l'accueil</a>
  </section>

</main>

<?php if ($pret): ?>
<?= workflow_script() ?>
<script>
(function () {
  var URL_SAUVER = <?= json_encode(avec_f('workflow.php?json=sauver')) ?>;
  var schema = <?= json_encode($schema, JSON_UNESCAPED_UNICODE) ?>;
  var svg = document.getElementById('tableau');
  var titre = document.getElementById('titre'), texte = document.getElementById('texte');
  var aide = document.getElementById('aide'), message = document.getElementById('message');
  var note = document.getElementById('sauvegarde');
  var inverser = document.getElementById('inverser'), supprimer = document.getElementById('supprimer');

  // Téléphone : lecture seule
  if (matchMedia('(max-width: 699px)').matches) {
    Schema.dessiner(svg, schema, {});
    document.querySelector('.wf-palette').hidden = true;
    document.getElementById('selection').hidden = true;
    titre.readOnly = true;
    return;
  }

  var attente = null;
  function sauver() {
    clearTimeout(attente);
    attente = null;
    return fetch(URL_SAUVER, {
      method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(schema)
    }).then(function (r) { return r.json(); }).then(function (d) {
      note.textContent = d.ok ? 'Schéma enregistré à ' + d.heure + '.' : 'Enregistrement impossible : l\'atelier est fermé.';
    }).catch(function () { note.textContent = 'Connexion perdue : nouvel essai à la prochaine modification.'; });
  }
  function plusTard() {
    note.textContent = 'Modification en cours…';
    clearTimeout(attente);
    attente = setTimeout(sauver, 800);
  }

  var choisi = null;   // forme sélectionnée, pour ne pas réécrire le champ pendant la frappe
  var tableau = Schema.editeur({
    svg: svg,
    schema: schema,
    palette: Array.prototype.slice.call(document.querySelectorAll('.wf-forme')),
    surChangement: plusTard,
    surSelection: function (n, l) {
      texte.hidden = !n;
      inverser.hidden = !(l && l.etiquette);
      supprimer.hidden = !(n || l);
      aide.hidden = !!(n || l);
      if (n && n !== choisi) { texte.value = n.texte; }
      choisi = n;
    },
    editerTexte: function () { texte.focus(); },
    message: function (m) {
      message.textContent = m;
      clearTimeout(message.t);
      message.t = setTimeout(function () { message.textContent = ''; }, 3500);
    }
  });

  texte.addEventListener('input', function () { tableau.texte(texte.value); });
  texte.addEventListener('keydown', function (ev) { if (ev.key === 'Enter') { texte.blur(); } });
  titre.addEventListener('input', function () { schema.titre = titre.value; plusTard(); });
  inverser.addEventListener('click', tableau.inverser);
  supprimer.addEventListener('click', tableau.supprimer);
  document.getElementById('vider').addEventListener('click', function () {
    if (confirm('Effacer tout le schéma ?')) { tableau.vider(); }
  });
  document.getElementById('exporter').addEventListener('click', function () {
    Schema.exporter(svg, (schema.titre || 'workflow').replace(/[^\w\- àâäéèêëîïôöùûüç]/gi, '').trim().replace(/\s+/g, '-') + '.png', schema);
  });
  window.addEventListener('pagehide', function () {
    if (attente) { navigator.sendBeacon(URL_SAUVER, new Blob([JSON.stringify(schema)], { type: 'application/json' })); }
  });
})();
</script>
<?php endif; ?>

</body>
</html>
