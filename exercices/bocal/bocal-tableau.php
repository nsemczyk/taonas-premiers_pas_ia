<?php
// Le bocal à secrets, côté formateur : les copies de la séance, une par
// stagiaire. Pour chacune, la correction automatique des surlignages (données
// trouvées, oubliées, faux positifs) et le prompt réécrit, à copier pour le
// tester dans son IA ; puis les points, individuels : surlignage (0, 10 ou 20,
// suggestion automatique) et prompt (0 ou 10).
require __DIR__ . '/fonctions.php';

// Accès réservé à l'animateur
$cle = (string)($_REQUEST['cle'] ?? '');
if (!hash_equals(CLE_ANIMATEUR, $cle)) {
    http_response_code(403);
    exit('Accès réservé. Ajoutez ?cle=... à l\'adresse.');
}
$base = 'bocal-tableau.php?cle=' . rawurlencode(CLE_ANIMATEUR);
$moi  = avec_f($base);

$table_ok = bocal_config() && bocal_table_ok() && points_table_ok();
$criteres = [
    'surlignage' => ['source' => 'bocal-surlignage', 'motif' => 'Bocal à secrets : surlignage',
                     'valeurs' => [0, BOCAL_POINTS_MOITIE, BOCAL_POINTS_TOUT]],
    'prompt'     => ['source' => 'bocal-prompt', 'motif' => 'Bocal à secrets : prompt anonymisé',
                     'valeurs' => [0, BOCAL_POINTS_PROMPT]],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $table_ok) {
    $action = $_POST['action'] ?? '';
    if ($action === 'noter') {
        // Un clic sur la note déjà donnée l'efface
        $pid = (int)($_POST['participant'] ?? 0);
        $c   = $criteres[$_POST['critere'] ?? ''] ?? null;
        $v   = (int)($_POST['valeur'] ?? -1);
        if ($c && in_array($v, $c['valeurs'], true)) {
            $actuelle = points_joueur_source($pid, $c['source']);
            points_joueur_fixer($pid, $c['source'], $actuelle === $v ? null : $v, $c['motif']);
        }
    } elseif ($action === 'suggestions') {
        // Surlignage des copies rendues pas encore notées : la suggestion automatique
        foreach (bocal_copies() as $copie) {
            $pid = (int)$copie['participant_id'];
            if ($copie['rendu'] && points_joueur_source($pid, 'bocal-surlignage') === null) {
                points_joueur_fixer($pid, 'bocal-surlignage', bocal_analyse($copie['surlignes'])['suggestion'],
                                    $criteres['surlignage']['motif']);
            }
        }
    }
    header('Location: ' . $moi, true, 303);
    exit;
}

$copies = $table_ok ? bocal_copies() : [];
$notes  = [];
foreach ($copies as $c) {
    $notes[(int)$c['participant_id']] = bocal_points((int)$c['participant_id']);
}
$sans_copie = $table_ok ? array_filter(participants_seance(),
    fn($p) => !in_array((int)$p['id'], array_map('intval', array_column($copies, 'participant_id')), true)) : [];
$rendues = count(array_filter($copies, fn($c) => $c['rendu']));

// Rechargement quand une copie arrive, est rendue ou notée — pas à chaque frappe
$empreinte = md5(json_encode([array_map(fn($c) => [$c['id'], $c['rendu']], $copies), $notes, array_column($sans_copie, 'id')]));
if (($_GET['json'] ?? '') === 'etat') {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['empreinte' => $empreinte]);
    exit;
}

// Boutons de note d'un critère pour un participant
function boutons_note(string $moi, int $pid, string $critere, array $valeurs, ?int $actuelle, ?int $suggestion = null): string
{
    $h = '';
    foreach ($valeurs as $v) {
        $classe = $actuelle === $v ? 'btn-gold' : 'btn-ghost';
        $h .= '<form method="post" action="' . e($moi) . '">'
            . '<input type="hidden" name="action" value="noter">'
            . '<input type="hidden" name="participant" value="' . $pid . '">'
            . '<input type="hidden" name="critere" value="' . e($critere) . '">'
            . '<input type="hidden" name="valeur" value="' . $v . '">'
            . '<button class="btn ' . $classe . ($suggestion === $v ? ' suggere' : '') . '"'
            . ($actuelle === $v ? ' title="Cliquer pour effacer la note"' : '') . '>' . $v . ' pts</button></form>';
    }
    return $h;
}
?><!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="icon" href="assets/favicon-couleur.png">
<meta name="robots" content="noindex">
<title>Le bocal à secrets — copies</title>
<link rel="stylesheet" href="style.css">
</head>
<body>

<header class="site-head">
  <h1>Le bocal à secrets</h1>
  <p><?= e(formation()['titre']) ?> · les copies de la séance</p>
</header>

<main class="wrap wrap-large">

  <?= selecteur_formation($base) ?>

<?php if (!bocal_config()): ?>
  <section class="card"><h2>Pas d'exercice</h2><p>Cette formation ne déclare pas de clé <code>bocal</code> dans son <code>formation.php</code>.</p></section>

<?php elseif (!$table_ok): ?>
  <section class="card">
    <h2>Migration à jouer</h2>
    <p>Les tables de l'exercice ou des points n'existent pas encore. Jouez une fois, avec un compte administrateur&nbsp;:</p>
    <pre>mysql --default-character-set=utf8mb4 -u root -p formation_ia &lt; exercices/scores/migration.sql
mysql --default-character-set=utf8mb4 -u root -p formation_ia &lt; exercices/bocal/migration.sql</pre>
  </section>

<?php else: ?>

  <section class="card">
    <h2><?= $rendues ?> copie<?= $rendues > 1 ? 's' : '' ?> rendue<?= $rendues > 1 ? 's' : '' ?>
      <span class="muted">· <?= count($copies) - $rendues ?> en cours · <?= count($sans_copie) ?> pas commencée<?= count($sans_copie) > 1 ? 's' : '' ?></span></h2>
    <?php if ($sans_copie): ?>
      <p class="muted">Pas encore commencé : <?= e(implode(', ', array_column($sans_copie, 'prenom'))) ?>.</p>
    <?php endif; ?>
    <p>Surlignage : <?= BOCAL_POINTS_TOUT ?> pts si toutes les données sont trouvées
      (<?= count(bocal_decoupe()['donnees']) ?>, chaque occurrence compte), <?= BOCAL_POINTS_MOITIE ?> pts pour plus de la moitié, 0 sinon.
      Une donnée est trouvée quand la moitié de ses mots au moins est surlignée. Prompt : <?= BOCAL_POINTS_PROMPT ?> pts s'il est anonymisé et garde le sens.</p>
    <form method="post" action="<?= e($moi) ?>" onsubmit="return confirm('Donner la note de surlignage suggérée à toutes les copies rendues pas encore notées ?')">
      <input type="hidden" name="action" value="suggestions">
      <button class="btn btn-primary">Appliquer les notes de surlignage suggérées</button>
    </form>
    <details class="doc">
      <summary>Le corrigé</summary>
      <table class="bocal-corrige">
        <tr><th>Donnée</th><th>Type</th><th>Pourquoi</th></tr>
        <?php foreach (bocal_config()['categories'] ?? [] as [$libelle, $type, $pourquoi]): ?>
        <tr><td><?= e($libelle) ?></td><td><?= e($type) ?></td><td><?= e($pourquoi) ?></td></tr>
        <?php endforeach; ?>
      </table>
      <div class="bocal-mail"><?= bocal_html(array_merge(...array_column(bocal_decoupe()['donnees'], 'mots')), true) ?></div>
      <p class="brique-titre">Version anonymisée acceptable</p>
      <div class="brique-texte deja"><?= e(bocal_config()['reference'] ?? '') ?></div>
    </details>
  </section>

  <?php foreach ($copies as $c):
      $pid = (int)$c['participant_id'];
      $a   = bocal_analyse($c['surlignes']);
      $n   = $notes[$pid]; ?>
  <section class="card bocal-copie<?= $c['rendu'] ? '' : ' brouillon' ?>" id="copie-<?= $pid ?>">
    <h2><?= e($c['prenom']) ?> <?= $c['equipe'] ? badge_equipe($c['equipe']) : '' ?>
      <span class="muted">· <?= $c['rendu'] ? 'rendue' : 'brouillon en cours' ?></span></h2>

    <p class="lead">Surlignage : <strong><?= $a['trouves'] ?> / <?= $a['total'] ?></strong> données trouvées
      <?= $a['faux'] ? '· ' . count($a['faux']) . ' mot' . (count($a['faux']) > 1 ? 's' : '') . ' surligné' . (count($a['faux']) > 1 ? 's' : '') . ' à tort' : '' ?>
      · suggestion : <?= $a['suggestion'] ?> pts</p>
    <?php if ($a['oublies']): ?>
      <p>Oublié : <?= implode(', ', array_map(fn($d) => '<span class="bocal-oubli">' . e($d['texte']) . '</span>', $a['oublies'])) ?></p>
    <?php endif; ?>
    <?php if ($a['faux']): ?>
      <p>À tort : <?= e(implode(', ', $a['faux'])) ?></p>
    <?php endif; ?>
    <details class="doc" data-memo="mail-<?= $pid ?>">
      <summary>Voir le mail surligné</summary>
      <div class="bocal-mail"><?= bocal_html($c['surlignes'], true) ?></div>
      <p class="bocal-legende"><span class="mot surligne trouve">trouvé</span> <span class="mot oublie">oublié</span> <span class="mot surligne faux">à tort</span></p>
    </details>

    <p class="brique-titre">Son prompt anonymisé</p>
    <?php if ($c['prompt'] !== ''): ?>
      <div class="brique-texte deja" id="txt-p<?= $pid ?>"><?= e($c['prompt']) ?></div>
      <button class="btn btn-ghost" data-copier="txt-p<?= $pid ?>">Copier le prompt pour le tester</button>
    <?php else: ?>
      <p class="muted">Pas encore de prompt.</p>
    <?php endif; ?>

    <div class="bocal-notes">
      <span>Surlignage</span>
      <?= boutons_note($moi, $pid, 'surlignage', $criteres['surlignage']['valeurs'], $n['surlignage'], $a['suggestion']) ?>
      <span>Prompt</span>
      <?= boutons_note($moi, $pid, 'prompt', $criteres['prompt']['valeurs'], $n['prompt']) ?>
    </div>
  </section>
  <?php endforeach; ?>

<?php endif; ?>

  <section class="card">
    <a class="btn btn-ghost" href="<?= e(avec_f('pilotage.php?cle=' . rawurlencode(CLE_ANIMATEUR))) ?>">Retour au pilotage</a>
  </section>

</main>

<?php if ($table_ok): ?>
<script>
(function () {
  var EMPREINTE = <?= json_encode($empreinte) ?>;
  var URL_ETAT = <?= json_encode($moi . '&json=etat') ?>;

  // Copie du prompt dans le presse-papiers
  document.querySelectorAll('[data-copier]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var texte = document.getElementById(btn.dataset.copier).textContent;
      function fait() { btn.textContent = 'Copié !'; setTimeout(function () { btn.textContent = 'Copier le prompt pour le tester'; }, 2000); }
      if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(texte).then(fait, secours);
      } else { secours(); }
      function secours() {
        var t = document.createElement('textarea');
        t.value = texte; document.body.appendChild(t); t.select();
        try { document.execCommand('copy'); fait(); } catch (e) {}
        document.body.removeChild(t);
      }
    });
  });

  // Les mails dépliés le restent au rechargement
  var ouverts = [];
  try { ouverts = JSON.parse(sessionStorage.getItem('bocal-ouverts') || '[]'); } catch (e) {}
  document.querySelectorAll('details[data-memo]').forEach(function (d) {
    if (ouverts.indexOf(d.dataset.memo) >= 0) { d.open = true; }
    d.addEventListener('toggle', function () {
      ouverts = ouverts.filter(function (m) { return m !== d.dataset.memo; });
      if (d.open) { ouverts.push(d.dataset.memo); }
      try { sessionStorage.setItem('bocal-ouverts', JSON.stringify(ouverts)); } catch (e) {}
    });
  });

  setInterval(function () {
    fetch(URL_ETAT).then(function (r) { return r.json(); }).then(function (d) {
      if (d.empreinte !== EMPREINTE) { location.reload(); }
    }).catch(function () {});
  }, 4000);
})();
</script>
<?php endif; ?>

</body>
</html>
