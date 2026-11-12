<?php
// Le candidat qui répond à côté, côté formateur. Deux vues :
//   - pilotage (par défaut) : les copies des équipes en direct, ouverture du
//     vote, révélation, points, recommencer ;
//   - ?vue=projection : le vidéoprojecteur — le cas pendant la rédaction, les
//     versions anonymes pendant le vote, le résultat à la révélation.
require __DIR__ . '/fonctions.php';

// Accès réservé à l'animateur
$cle = (string)($_REQUEST['cle'] ?? '');
if (!hash_equals(CLE_ANIMATEUR, $cle)) {
    http_response_code(403);
    exit('Accès réservé. Ajoutez ?cle=... à l\'adresse.');
}
$base       = 'candidat-tableau.php?cle=' . rawurlencode(CLE_ANIMATEUR);
$moi        = avec_f($base);
$projection = ($_GET['vue'] ?? '') === 'projection';
$pret       = candidat_config() && candidat_table_ok();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $pret) {
    switch ($_POST['action'] ?? '') {
        case 'vote':        candidat_ouvrir_vote(); break;
        case 'reveler':     candidat_reveler(); break;
        case 'redaction':   candidat_etat('redaction'); break;
        case 'recommencer': candidat_recommencer(); break;
        case 'points':
            $eq = (string)($_POST['equipe'] ?? '');
            if (equipe_existe($eq) && points_actifs() && points_table_ok()) {
                points_source_basculer(CANDIDAT_SOURCE, $eq, CANDIDAT_POINTS, 'Le candidat qui répond à côté');
            }
            break;
    }
    header('Location: ' . $moi, true, 303);
    exit;
}

$partie   = $pret ? candidat_partie() : null;
$copies   = $pret ? candidat_copies() : [];
$enLice   = $partie && $partie['etat'] !== 'redaction' ? candidat_en_lice() : [];
$voix     = $partie && $partie['etat'] !== 'redaction' ? candidat_decompte() : [];
$gagnants = $partie && $partie['etat'] === 'revele' ? candidat_gagnants() : [];
$points   = points_actifs() && points_table_ok();
$votants  = $pret ? candidat_nb_votants() : 0;

$empreinte = md5(json_encode([$partie, array_map(fn($c) => [$c['id'], $c['updated_at'], $c['secretaire_id']], $copies), $voix,
    $points ? array_map(fn($eq) => points_source_donnee(CANDIDAT_SOURCE, $eq), array_keys($copies)) : []]));
if (($_GET['json'] ?? '') === 'etat') {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['empreinte' => $empreinte]);
    exit;
}

function bouton(string $moi, string $action, string $libelle, string $classe, array $champs = [], string $confirmer = ''): string
{
    $h = '<form method="post" action="' . e($moi) . '"' . ($confirmer !== '' ? ' onsubmit="return confirm(\'' . e($confirmer) . '\')"' : '') . '>'
       . '<input type="hidden" name="action" value="' . e($action) . '">';
    foreach ($champs as $k => $v) {
        $h .= '<input type="hidden" name="' . e($k) . '" value="' . e($v) . '">';
    }
    return $h . '<button class="' . e($classe) . '">' . e($libelle) . '</button></form>';
}
?><!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="icon" href="assets/favicon-couleur.png">
<meta name="robots" content="noindex">
<title><?= $projection ? 'Le candidat qui répond à côté' : 'Le candidat qui répond à côté — pilotage' ?></title>
<link rel="stylesheet" href="style.css">
</head>
<body class="<?= $projection ? 'battle-projete' : '' ?>">

<?php if ($projection): ?>
<!-- ============================================================ PROJECTION -->
<header class="bp-tete">
  <h1>Le candidat qui répond à côté</h1>
  <a href="<?= e($moi) ?>">Pilotage</a>
</header>
<main class="bp-scene">
<?php if (!$pret): ?>
  <p class="bp-attente">Défi non configuré ou tables absentes : jouez exercices/candidat/migration.sql.</p>

<?php elseif ($partie['etat'] === 'redaction'): ?>
  <p class="bp-label">Ce candidat IA a mal compris la mission. Qu'est-ce que le recruteur a oublié de lui dire ?</p>
  <?= candidat_cas_html('cd-projete') ?>
  <div class="tp-scores">
    <?php foreach (array_keys(equipes()) as $eq): $c = $copies[$eq] ?? null; ?>
      <?php if ($c): ?><p><?= badge_equipe($eq) ?> <?= trim($c['resultat']) !== '' ? 'réponse de l\'IA collée' : (trim($c['prompt']) !== '' ? 'prompt en cours' : 'au travail') ?></p><?php endif; ?>
    <?php endforeach; ?>
  </div>

<?php else: ?>
  <?php if ($partie['etat'] === 'revele'): ?>
    <p class="bp-victoire"><?= $gagnants ? '🏆 ' . e(implode(' et ', array_map(fn($eq) => equipes()[$eq]['nom'], $gagnants))) : 'Pas de vote, pas de vainqueur' ?></p>
  <?php else: ?>
    <p class="bp-label">Votez sur votre téléphone pour la meilleure version · <?= $votants ?> vote<?= $votants > 1 ? 's' : '' ?></p>
  <?php endif; ?>
  <div class="bp-copies" style="--n:<?= max(1, count($enLice)) ?>">
    <?php foreach ($enLice as $c): $revele = $partie['etat'] === 'revele'; ?>
    <div class="bp-copie<?= in_array($c['equipe'], $gagnants, true) ? ' gagnante' : '' ?>"<?= $revele ? ' style="--equipe:' . e(equipes()[$c['equipe']]['couleur'] ?? '#47B4E8') . '"' : '' ?>>
      <h2>Version <?= e($c['lettre']) ?><?= $revele ? ' <small>' . e(equipes()[$c['equipe']]['nom'] ?? '') . '</small><span class="bp-voix">' . ($voix[(int)$c['id']] ?? 0) . ' voix</span>' : '' ?></h2>
      <?php if ($revele): ?>
        <p class="bp-sous">Ce qui manquait</p>
        <div class="bp-texte bp-prompt"><?= e($c['manques']) ?></div>
      <?php endif; ?>
      <p class="bp-sous">Le prompt</p>
      <div class="bp-texte bp-prompt"><?= e($c['prompt']) ?></div>
      <p class="bp-sous">La réponse de l'IA</p>
      <div class="bp-texte"><?= e($c['resultat']) ?></div>
    </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>
</main>

<?php else: ?>
<!-- ============================================================ PILOTAGE -->
<header class="site-head">
  <h1>Le candidat qui répond à côté</h1>
  <p><?= e(formation()['titre']) ?> · défi équipe, <?= CANDIDAT_POINTS ?> pts</p>
</header>

<main class="wrap">
  <?= selecteur_formation($base) ?>

<?php if (!candidat_config()): ?>
  <section class="card"><h2>Pas de défi</h2><p>Cette formation ne déclare pas de clé <code>candidat</code> dans son <code>formation.php</code>.</p></section>
<?php elseif (!$pret): ?>
  <section class="card">
    <h2>Migration à jouer</h2>
    <pre>mysql --default-character-set=utf8mb4 -u root -p formation_ia &lt; exercices/candidat/migration.sql</pre>
  </section>
<?php else: ?>
  <section class="card">
    <a class="btn btn-gold" href="<?= e(avec_f($base . '&vue=projection')) ?>" target="_blank">Ouvrir l'écran de projection</a>
    <?php if ($partie['etat'] === 'redaction'): ?>
      <h2>Rédaction en cours</h2>
      <p class="lead">Les équipes rédigent. À la clôture, les copies qui ont un prompt passent au vote, anonymes (A, B…) ; les autres déclarent forfait.</p>
      <?= bouton($moi, 'vote', 'Clore la rédaction et ouvrir le vote', 'btn btn-primary', [], 'Plus aucune modification ne sera possible. Ouvrir le vote ?') ?>
    <?php elseif ($partie['etat'] === 'vote'): ?>
      <h2>Vote en cours · <?= $votants ?> vote<?= $votants > 1 ? 's' : '' ?></h2>
      <?php foreach ($enLice as $c): ?>
        <p>Version <?= e($c['lettre']) ?> · <?= badge_equipe($c['equipe']) ?> · <?= $voix[(int)$c['id']] ?? 0 ?> voix</p>
      <?php endforeach; ?>
      <?= bouton($moi, 'reveler', 'Clore le vote et révéler', 'btn btn-gold', [], 'Clore le vote et révéler le gagnant ?') ?>
      <?= bouton($moi, 'redaction', 'Rouvrir la rédaction', 'btn btn-ghost', [], 'Rouvrir la rédaction ? Les votes déjà donnés restent.') ?>
    <?php else: ?>
      <h2>Résultat</h2>
      <p class="lead"><?= $gagnants ? 'Victoire de ' . implode(' et ', array_map('badge_equipe', $gagnants)) : 'Pas de vote, pas de vainqueur.' ?></p>
      <?php if ($points): foreach ($enLice as $c): $donnes = points_source_donnee(CANDIDAT_SOURCE, $c['equipe']); ?>
        <?= bouton($moi, 'points', ($donnes ? 'Reprendre les ' : 'Donner les ') . CANDIDAT_POINTS . ' pts · ' . (equipes()[$c['equipe']]['nom'] ?? $c['equipe']),
                   $donnes ? 'btn btn-gold' : 'btn btn-ghost', ['equipe' => $c['equipe']]) ?>
      <?php endforeach; endif; ?>
    <?php endif; ?>
  </section>

  <?php if ($corrige = candidat_config()['corrige'] ?? null): ?>
  <section class="card">
    <details class="doc">
      <summary>Le corrigé</summary>
      <p class="brique-titre">1. Ce qui manquait au recruteur</p>
      <table class="bocal-corrige">
        <?php foreach ($corrige['manques'] as [$manque, $pourquoi]): ?>
        <tr><td><strong><?= e($manque) ?></strong></td><td><?= e($pourquoi) ?></td></tr>
        <?php endforeach; ?>
      </table>
      <p class="brique-titre">2. Un prompt complet</p>
      <div class="brique-texte deja"><?= e($corrige['prompt']) ?></div>
      <p class="brique-titre">3. Ce qu'on peut en attendre</p>
      <div class="brique-texte reponse-ia"><?= e($corrige['exemple']) ?></div>
    </details>
  </section>
  <?php endif; ?>

  <?php foreach (equipes() as $eq => $def): $c = $copies[$eq] ?? null; ?>
  <section class="card copie">
    <h2><?= badge_equipe($eq) ?> <?= $c && $c['lettre'] ? '<span class="muted">· version ' . e($c['lettre']) . '</span>' : '' ?></h2>
    <?php if (!$c): ?>
      <p class="muted">Pas encore commencé.</p>
    <?php else: ?>
      <p class="muted">Plume : <?= e($c['secretaire'] ?? '—') ?></p>
      <p class="brique-titre">Ce qui manquait</p>
      <div class="brique-texte deja"><?= $c['manques'] !== '' ? e($c['manques']) : '<em>…</em>' ?></div>
      <p class="brique-titre">Le prompt réécrit</p>
      <div class="brique-texte deja"><?= $c['prompt'] !== '' ? e($c['prompt']) : '<em>…</em>' ?></div>
      <p class="brique-titre">La réponse de l'IA</p>
      <div class="brique-texte reponse-ia"><?= $c['resultat'] !== '' ? e($c['resultat']) : '<em>…</em>' ?></div>
    <?php endif; ?>
  </section>
  <?php endforeach; ?>

  <section class="card">
    <?= bouton($moi, 'recommencer', 'Recommencer le défi', 'btn btn-ghost', [], 'Effacer les copies, les votes et les points du défi ?') ?>
    <a class="btn btn-ghost" href="<?= e(avec_f('pilotage.php?cle=' . rawurlencode(CLE_ANIMATEUR))) ?>">Retour au pilotage</a>
  </section>
<?php endif; ?>
</main>
<?php endif; ?>

<?php if ($pret): ?>
<script>
(function () {
  var EMPREINTE = <?= json_encode($empreinte) ?>;
  var URL_ETAT = <?= json_encode($moi . '&json=etat') ?>;
  setInterval(function () {
    fetch(URL_ETAT).then(function (r) { return r.json(); }).then(function (d) {
      if (d.empreinte !== EMPREINTE) { location.reload(); }
    }).catch(function () {});
  }, 3000);
})();
</script>
<?php endif; ?>

</body>
</html>
