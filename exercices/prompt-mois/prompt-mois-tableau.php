<?php
// Un prompt pour dans un mois, côté formateur : ce que chaque participant a
// fait, en direct. Pour chacun, l'avancement (tâche, version 1, analyse,
// version 2) et, dépliables, ses deux versions assemblées, son analyse et
// un bouton pour copier son prompt final. Lecture seule.
require __DIR__ . '/fonctions.php';

// Accès réservé à l'animateur
$cle = (string)($_REQUEST['cle'] ?? '');
if (!hash_equals(CLE_ANIMATEUR, $cle)) {
    http_response_code(403);
    exit('Accès réservé. Ajoutez ?cle=... à l\'adresse.');
}
$base = 'prompt-mois-tableau.php?cle=' . rawurlencode(CLE_ANIMATEUR);
$moi  = avec_f($base);
$pret = prompt_mois_table_ok();

$fiches     = $pret ? prompt_mois_fiches() : [];
$avec_fiche = array_map('intval', array_column($fiches, 'participant_id'));
$sans_fiche = $pret ? array_values(array_filter(participants_seance(), fn($p) => !in_array((int)$p['id'], $avec_fiche, true))) : [];
$nb_blocs   = count(PROMPT_MOIS_BLOCS);

// Rechargement quand une fiche bouge ou qu'un participant arrive
$empreinte = md5(json_encode([array_map(fn($f) => [$f['participant_id'], $f['updated_at']], $fiches), array_column($sans_fiche, 'id')]));
if (($_GET['json'] ?? '') === 'etat') {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['empreinte' => $empreinte]);
    exit;
}

// Les 4 étapes de la fiche, faites ou non, pour le résumé
function avancement(array $f, int $nb_blocs): array
{
    $v1 = prompt_mois_remplis($f['v1']);
    $v2 = prompt_mois_remplis($f['v2']);
    return [
        ['Tâche', $f['tache'] !== ''],
        ['Version 1 : ' . $v1 . ' / ' . $nb_blocs, $v1 === $nb_blocs, $v1 > 0],
        ['Analyse', $f['bon'] !== '' && $f['manque'] !== '', $f['bon'] !== '' || $f['manque'] !== ''],
        ['Version 2' . ($v2 ? ' : ' . $v2 . ' / ' . $nb_blocs : ''), $v2 === $nb_blocs && $f['v2'] != $f['v1'], $v2 > 0],
    ];
}
?><!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="icon" href="assets/favicon-couleur.png">
<meta name="robots" content="noindex">
<title>Un prompt pour dans un mois — les fiches</title>
<link rel="stylesheet" href="style.css">
</head>
<body>

<header class="site-head">
  <h1>Un prompt pour dans un mois</h1>
  <p><?= e(formation()['titre']) ?> · <?= count($fiches) ?> fiche<?= count($fiches) > 1 ? 's' : '' ?> commencée<?= count($fiches) > 1 ? 's' : '' ?></p>
</header>

<main class="wrap wrap-large">
  <?= selecteur_formation($base) ?>

<?php if (!$pret): ?>
  <section class="card">
    <h2>Migration à jouer</h2>
    <pre>mysql --default-character-set=utf8mb4 -u root -p formation_ia &lt; exercices/prompt-mois/migration.sql</pre>
  </section>
<?php else: ?>
  <section class="card">
    <h2>Avancement</h2>
    <?php if ($sans_fiche): ?>
      <p class="muted">Pas encore commencé : <?= e(implode(', ', array_column($sans_fiche, 'prenom'))) ?>.</p>
    <?php endif; ?>
    <?php if (!$fiches): ?>
      <p class="lead">Aucune fiche pour l'instant : elles apparaîtront ici dès que les participants commenceront.</p>
    <?php else: ?>
    <table class="board pm-suivi">
      <thead><tr><th>Participant</th><th>Tâche récurrente</th><th>Avancement</th><th>Modifié</th></tr></thead>
      <tbody>
      <?php foreach ($fiches as $f): ?>
        <tr>
          <td><a href="#fiche-<?= (int)$f['participant_id'] ?>"><strong><?= e($f['prenom']) ?></strong></a> <?= $f['equipe'] ? badge_equipe($f['equipe']) : '' ?></td>
          <td><?= $f['fiche']['tache'] !== '' ? e($f['fiche']['tache']) : '<span class="muted">—</span>' ?></td>
          <td class="pm-etapes">
            <?php foreach (avancement($f['fiche'], $nb_blocs) as $a): ?>
              <span class="pm-etape <?= $a[1] ? 'faite' : (!empty($a[2]) ? 'en-cours' : '') ?>"><?= e($a[0]) ?></span>
            <?php endforeach; ?>
          </td>
          <td><?= e(heure_locale($f['updated_at'])) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    <p class="muted pm-legende"><span class="pm-etape faite">fait</span> <span class="pm-etape en-cours">en cours</span> <span class="pm-etape">pas commencé</span>
      · Version 2 « faite » : 7 blocs remplis et différente de la version 1.</p>
    <?php endif; ?>
  </section>

  <?php foreach ($fiches as $f): $fi = $f['fiche']; $pid = (int)$f['participant_id'];
        $v1 = prompt_mois_assembler($fi['v1']); $v2 = prompt_mois_assembler($fi['v2']); $final = $v2 !== '' ? $v2 : $v1; ?>
  <section class="card" id="fiche-<?= $pid ?>">
    <h2><?= e($f['prenom']) ?> <?= $f['equipe'] ? badge_equipe($f['equipe']) : '' ?></h2>
    <?php if (($objectif = objectif_de($pid))): ?>
      <p class="pm-objectif">Objectif du jour : « <?= e(OBJECTIF_AMORCE . ' ' . $objectif['texte']) ?> »</p>
    <?php endif; ?>
    <p class="lead">Tâche : <?= $fi['tache'] !== '' ? '<strong>' . e($fi['tache']) . '</strong>' : '<span class="muted">pas encore choisie</span>' ?></p>

    <details class="doc" data-memo="v1-<?= $pid ?>">
      <summary>Version 1 · <?= prompt_mois_remplis($fi['v1']) ?> / <?= $nb_blocs ?> blocs</summary>
      <pre><?= $v1 !== '' ? e($v1) : '(vide)' ?></pre>
    </details>
    <details class="doc" data-memo="analyse-<?= $pid ?>">
      <summary>Analyse</summary>
      <p class="brique-titre">Ce qui est bon</p>
      <div class="brique-texte deja"><?= $fi['bon'] !== '' ? e($fi['bon']) : '<em>—</em>' ?></div>
      <p class="brique-titre">Ce qui manque</p>
      <div class="brique-texte deja"><?= $fi['manque'] !== '' ? e($fi['manque']) : '<em>—</em>' ?></div>
    </details>
    <details class="doc" data-memo="v2-<?= $pid ?>">
      <summary>Version 2 · <?= prompt_mois_remplis($fi['v2']) ?> / <?= $nb_blocs ?> blocs<?= $v2 !== '' && $fi['v2'] == $fi['v1'] ? ' (identique à la version 1)' : '' ?></summary>
      <pre><?= $v2 !== '' ? e($v2) : '(vide)' ?></pre>
    </details>
    <?php if ($final !== ''): ?>
      <pre id="final-<?= $pid ?>" hidden><?= e($final) ?></pre>
      <button type="button" class="btn btn-ghost" data-copier="final-<?= $pid ?>">Copier son prompt final</button>
    <?php endif; ?>
  </section>
  <?php endforeach; ?>
<?php endif; ?>

  <section class="card">
    <a class="btn btn-ghost" href="<?= e(avec_f('pilotage.php?cle=' . rawurlencode(CLE_ANIMATEUR))) ?>">Retour au pilotage</a>
  </section>
</main>

<?php if ($pret): ?>
<script>
(function () {
  // Les détails dépliés le restent au rechargement
  var ouverts = [];
  try { ouverts = JSON.parse(sessionStorage.getItem('pm-ouverts') || '[]'); } catch (e) {}
  document.querySelectorAll('details[data-memo]').forEach(function (d) {
    if (ouverts.indexOf(d.dataset.memo) >= 0) { d.open = true; }
    d.addEventListener('toggle', function () {
      ouverts = ouverts.filter(function (m) { return m !== d.dataset.memo; });
      if (d.open) { ouverts.push(d.dataset.memo); }
      try { sessionStorage.setItem('pm-ouverts', JSON.stringify(ouverts)); } catch (e) {}
    });
  });

  document.querySelectorAll('[data-copier]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var texte = document.getElementById(btn.dataset.copier).textContent, libelle = btn.textContent;
      function fait() { btn.textContent = 'Copié !'; setTimeout(function () { btn.textContent = libelle; }, 2000); }
      function secours() {
        var t = document.createElement('textarea');
        t.value = texte; document.body.appendChild(t); t.select();
        try { document.execCommand('copy'); fait(); } catch (e) {}
        document.body.removeChild(t);
      }
      if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(texte).then(fait, secours);
      } else { secours(); }
    });
  });

  // Suivi en direct
  var EMPREINTE = <?= json_encode($empreinte) ?>;
  var URL_ETAT = <?= json_encode($moi . '&json=etat') ?>;
  setInterval(function () {
    fetch(URL_ETAT).then(function (r) { return r.json(); }).then(function (d) {
      if (d.empreinte !== EMPREINTE) { location.reload(); }
    }).catch(function () {});
  }, 5000);
})();
</script>
<?php endif; ?>

</body>
</html>
