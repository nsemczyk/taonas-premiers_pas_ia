<?php
// Prompt Battle, côté formateur. Deux vues :
//   - pilotage (par défaut, téléphone) : nouvelle manche, tirage de la tâche,
//     duel et volontaires, top départ, chrono, clôture du vote, points ;
//   - ?vue=projection : l'écran du vidéoprojecteur (roulette du tirage, chrono,
//     copies anonymes, révélation), qui suit la manche en direct.
require __DIR__ . '/core/battle.php';

// Accès réservé à l'animateur
$cle = (string)($_REQUEST['cle'] ?? '');
if (!hash_equals(CLE_ANIMATEUR, $cle)) {
    http_response_code(403);
    exit('Accès réservé. Ajoutez ?cle=... à l\'adresse.');
}
$base       = 'battle-tableau.php?cle=' . rawurlencode(CLE_ANIMATEUR);
$moi        = avec_f($base);
$projection = ($_GET['vue'] ?? '') === 'projection';
$table_ok   = battle_table_ok();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $table_ok) {
    $id = (int)($_POST['manche'] ?? 0);
    switch ($_POST['action'] ?? '') {
        case 'nouvelle':  battle_nouvelle(); break;
        case 'tirer':     battle_tirer($id); break;
        case 'duel':
            $pa = battle_participant((int)($_POST['joueur_a'] ?? 0));
            $pb = battle_participant((int)($_POST['joueur_b'] ?? 0));
            battle_regler($id, $pa['equipe'] ?? null, $pb['equipe'] ?? null, (int)($pa['id'] ?? 0), (int)($pb['id'] ?? 0));
            break;
        case 'demarrer':  battle_demarrer($id); break;
        case 'prolonger': battle_chrono($id, 60); break;
        case 'terminer':  battle_chrono($id, null); break;
        case 'decompte':
            $st = db()->prepare('UPDATE battle_manches SET decompte = 1 - decompte WHERE id = ?');
            $st->execute([$id]);
            break;
        case 'reveler':   battle_reveler($id); break;
        case 'points':
            $eq = (string)($_POST['equipe'] ?? '');
            if (equipe_existe($eq) && points_actifs() && points_table_ok()) {
                points_source_basculer(battle_source($id), $eq, BATTLE_POINTS, 'Prompt Battle');
            }
            break;
        case 'abandonner': battle_abandonner($id); break;
    }
    header('Location: ' . $moi, true, 303);
    exit;
}

$m       = $table_ok ? battle_courante() : null;
$copies  = $m ? battle_copies((int)$m['id']) : [];
$enLice  = $m ? battle_copies_en_lice((int)$m['id']) : [];
$voix    = $m && in_array($m['etat'], ['vote', 'revele'], true) ? battle_decompte((int)$m['id']) : [];
$gagnants = $m && $m['etat'] === 'revele' ? battle_gagnants((int)$m['id']) : [];
$points  = points_actifs() && points_table_ok();
$votants = $m ? count(participants_seance()) - 2 : 0;   // tous sauf les deux volontaires
$nbVotes = array_sum($voix);

// Empreinte : la page se recharge quand la manche avance
$empreinte = md5(json_encode([
    $m['id'] ?? 0, $m['etat'] ?? '', $m['tache'] ?? '', $m['tirage_at'] ?? 0, $m['joueur_a'] ?? 0, $m['joueur_b'] ?? 0,
    $m['decompte'] ?? 0, array_map(fn($c) => [$c['id'], $c['updated_at']], $copies),
    $voix, $m && $points ? array_map(fn($eq) => points_source_donnee(battle_source((int)$m['id']), $eq), array_filter([$m['equipe_a'], $m['equipe_b']])) : [],
]));
if (($_GET['json'] ?? '') === 'etat') {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['empreinte' => $empreinte, 'reste' => $m ? battle_reste($m) : 0]);
    exit;
}

$nomEquipe = fn(?string $eq) => $eq ? (equipes()[$eq]['nom'] ?? $eq) : '?';
$couleur   = fn(?string $eq) => $eq ? (equipes()[$eq]['couleur'] ?? '#1B3E90') : '#1B3E90';
$prenom    = fn($id) => battle_participant($id)['prenom'] ?? '?';

// Formulaire d'une action sur la manche en cours
function action(string $moi, array $m, string $action, string $libelle, string $classe = 'btn btn-primary', array $champs = [], string $confirmer = ''): string
{
    $h = '<form method="post" action="' . e($moi) . '"' . ($confirmer !== '' ? ' onsubmit="return confirm(\'' . e($confirmer) . '\')"' : '') . '>'
       . '<input type="hidden" name="action" value="' . e($action) . '">'
       . '<input type="hidden" name="manche" value="' . (int)$m['id'] . '">';
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
<title><?= $projection ? 'Prompt Battle' : 'Prompt Battle — pilotage' ?></title>
<link rel="stylesheet" href="style.css">
</head>
<body class="<?= $projection ? 'battle-projete' : '' ?>">

<?php if ($projection): ?>
<!-- ============================================================ PROJECTION -->
<header class="bp-tete">
  <h1>Prompt Battle<?= $m ? ' <span>· manche ' . (int)$m['numero'] . '</span>' : '' ?></h1>
  <a href="<?= e($moi) ?>">Pilotage</a>
</header>
<main class="bp-scene">

<?php if (!$table_ok): ?>
  <p class="bp-attente">Tables absentes : jouez migration-battle.sql.</p>

<?php elseif (!$m): ?>
  <p class="bp-attente">La première manche se prépare…</p>

<?php elseif ($m['etat'] === 'preparation'): ?>
  <p class="bp-label">La tâche surprise</p>
  <p class="bp-tache" id="tache" data-tirage-age="<?= $m['tirage_at'] ? time() - (int)$m['tirage_at'] : 999 ?>"><?= $m['tache'] ? e($m['tache']) : '?' ?></p>
  <?php if ($m['equipe_a'] && $m['equipe_b']): ?>
  <div class="bp-duel">
    <span class="bp-camp" style="--equipe:<?= e($couleur($m['equipe_a'])) ?>"><?= e($nomEquipe($m['equipe_a'])) ?><small><?= e($prenom($m['joueur_a'])) ?></small></span>
    <span class="bp-vs">VS</span>
    <span class="bp-camp" style="--equipe:<?= e($couleur($m['equipe_b'])) ?>"><?= e($nomEquipe($m['equipe_b'])) ?><small><?= e($prenom($m['joueur_b'])) ?></small></span>
  </div>
  <?php endif; ?>

<?php elseif ($m['etat'] === 'jeu'): ?>
  <p class="bp-tache"><?= e($m['tache']) ?></p>
  <p class="bp-chrono" id="chrono" data-reste="<?= battle_reste($m) ?>"></p>
  <div class="bp-duel">
    <span class="bp-camp" style="--equipe:<?= e($couleur($m['equipe_a'])) ?>"><?= e($nomEquipe($m['equipe_a'])) ?><small><?= e($prenom($m['joueur_a'])) ?></small></span>
    <span class="bp-vs">VS</span>
    <span class="bp-camp" style="--equipe:<?= e($couleur($m['equipe_b'])) ?>"><?= e($nomEquipe($m['equipe_b'])) ?><small><?= e($prenom($m['joueur_b'])) ?></small></span>
  </div>

<?php elseif ($m['etat'] === 'vote'): ?>
  <p class="bp-tache bp-tache-petite"><?= e($m['tache']) ?></p>
  <p class="bp-label">À vous de voter, sur votre téléphone<?= $m['decompte'] ? ' · ' . $nbVotes . ' vote' . ($nbVotes > 1 ? 's' : '') : '' ?></p>
  <div class="bp-copies" style="--n:<?= max(1, count($enLice)) ?>">
    <?php foreach ($enLice as $c): ?>
    <section class="bp-copie">
      <h2>Copie <?= e($c['lettre']) ?><?= $m['decompte'] ? ' <span class="bp-voix">' . ($voix[(int)$c['id']] ?? 0) . '</span>' : '' ?></h2>
      <div class="bp-texte"><?= e($c['resultat']) ?></div>
    </section>
    <?php endforeach; ?>
    <?php if (!$enLice): ?><p class="bp-attente">Aucune copie rendue à temps.</p><?php endif; ?>
  </div>

<?php else: ?>
  <p class="bp-tache bp-tache-petite"><?= e($m['tache']) ?></p>
  <?php if ($gagnants): ?>
    <p class="bp-victoire">🏆 <?= e(implode(' et ', array_map($nomEquipe, $gagnants))) ?><?= $points ? ' · +' . BATTLE_POINTS . ' pts' : '' ?></p>
  <?php else: ?>
    <p class="bp-label">Pas de vote, pas de vainqueur.</p>
  <?php endif; ?>
  <div class="bp-copies" style="--n:<?= max(1, count($enLice)) ?>">
    <?php foreach ($enLice as $c): ?>
    <section class="bp-copie<?= in_array($c['equipe'], $gagnants, true) ? ' gagnante' : '' ?>" style="--equipe:<?= e($couleur($c['equipe'])) ?>">
      <h2>Copie <?= e($c['lettre']) ?> · <?= e($nomEquipe($c['equipe'])) ?> <small><?= e($c['prenom']) ?></small>
        <span class="bp-voix"><?= $voix[(int)$c['id']] ?? 0 ?></span></h2>
      <p class="bp-sous">Le prompt</p>
      <div class="bp-texte bp-prompt"><?= e($c['prompt']) ?></div>
      <p class="bp-sous">La réponse de l'IA</p>
      <div class="bp-texte"><?= e($c['resultat']) ?></div>
    </section>
    <?php endforeach; ?>
  </div>
<?php endif; ?>
</main>

<?php else: ?>
<!-- ============================================================== PILOTAGE -->
<header class="site-head">
  <h1>Prompt Battle</h1>
  <p><?= $m ? 'Manche ' . (int)$m['numero'] . ' · ' . ['preparation' => 'en préparation', 'jeu' => 'en jeu', 'vote' => 'vote ouvert', 'revele' => 'terminée'][$m['etat']] : 'Aucune manche' ?></p>
</header>
<main class="wrap">

  <?= selecteur_formation($base) ?>

<?php if (!$table_ok): ?>
  <section class="card">
    <h2>Migration à jouer</h2>
    <pre>mysql --default-character-set=utf8mb4 -u root -p formation_ia &lt; migration-battle.sql</pre>
  </section>
<?php else: ?>

  <section class="card">
    <a class="btn btn-gold" href="<?= e(avec_f($base . '&vue=projection')) ?>" target="_blank">Ouvrir l'écran de projection</a>
  </section>

  <?php if (!$m || $m['etat'] === 'revele'): ?>
  <section class="card">
    <h2><?= $m ? 'Manche suivante' : 'Première manche' ?></h2>
    <p class="lead"><?= count(battle_taches_restantes()) ?> tâche(s) en réserve sur <?= count(battle_taches()) ?>.</p>
    <form method="post" action="<?= e($moi) ?>"><input type="hidden" name="action" value="nouvelle">
      <button class="btn btn-primary">Préparer une nouvelle manche</button></form>
  </section>
  <?php endif; ?>

  <?php if ($m && $m['etat'] === 'preparation'): ?>
  <section class="card">
    <h2>1. La tâche surprise</h2>
    <p class="boule-tache"><?= $m['tache'] ? e($m['tache']) : '<span class="muted">Pas encore tirée</span>' ?></p>
    <?= action($moi, $m, 'tirer', $m['tache'] ? 'Retirer une autre tâche' : 'Tirer une tâche surprise', $m['tache'] ? 'btn btn-ghost' : 'btn btn-primary') ?>
    <p class="muted"><?= count(battle_taches_restantes()) ?> tâche(s) pas encore jouée(s). La roulette tourne au vidéoprojecteur.</p>
  </section>

  <section class="card">
    <h2>2. Le duel</h2>
    <form method="post" action="<?= e($moi) ?>" class="duel">
      <input type="hidden" name="action" value="duel">
      <input type="hidden" name="manche" value="<?= (int)$m['id'] ?>">
      <?php foreach (['a' => 'Volontaire 1', 'b' => 'Volontaire 2'] as $cote => $libelle): ?>
      <label class="field" for="joueur_<?= $cote ?>"><?= $libelle ?></label>
      <select class="field" id="joueur_<?= $cote ?>" name="joueur_<?= $cote ?>" onchange="this.form.submit()">
        <option value="">— choisir —</option>
        <?php foreach (equipes() as $eq => $def):
            $membres = array_filter(participants_seance(), fn($p) => $p['equipe'] === $eq);
            if (!$membres) { continue; } ?>
          <optgroup label="<?= e($def['nom']) ?>">
          <?php foreach ($membres as $p): ?>
            <option value="<?= (int)$p['id'] ?>"<?= (int)$m['joueur_' . $cote] === (int)$p['id'] ? ' selected' : '' ?>><?= e($p['prenom']) ?> (<?= e($def['nom']) ?>)</option>
          <?php endforeach; ?>
          </optgroup>
        <?php endforeach; ?>
      </select>
      <?php endforeach; ?>
    </form>
    <p class="muted">Un volontaire par équipe, deux équipes différentes.</p>
  </section>

  <section class="card">
    <h2>3. Top départ</h2>
    <?php if (battle_prete($m)): ?>
      <p class="lead"><?= badge_equipe($m['equipe_a']) ?> <?= e($prenom($m['joueur_a'])) ?> contre
        <?= badge_equipe($m['equipe_b']) ?> <?= e($prenom($m['joueur_b'])) ?> · <?= BATTLE_DUREE / 60 ?> minutes</p>
      <?= action($moi, $m, 'demarrer', 'Top départ !', 'btn btn-gold') ?>
    <?php else: ?>
      <p class="muted">Il faut une tâche et deux volontaires d'équipes différentes.</p>
    <?php endif; ?>
    <?= action($moi, $m, 'abandonner', 'Abandonner cette manche', 'btn btn-ghost', [], 'Abandonner cette manche ?') ?>
  </section>
  <?php endif; ?>

  <?php if ($m && $m['etat'] === 'jeu'): ?>
  <section class="card">
    <h2>En jeu</h2>
    <p class="boule-tache"><?= e($m['tache']) ?></p>
    <p class="chrono" id="chrono" data-reste="<?= battle_reste($m) ?>"></p>
    <?php foreach (['a', 'b'] as $cote): $eq = $m['equipe_' . $cote];
        $c = current(array_filter($copies, fn($x) => $x['equipe'] === $eq)) ?: null; ?>
    <p><?= badge_equipe($eq) ?> <?= e($prenom($m['joueur_' . $cote])) ?> :
      <?= $c ? 'copie enregistrée (' . mb_strlen($c['resultat']) . ' car. de réponse), ' . e(substr($c['updated_at'], 11, 8)) : '<span class="muted">rien encore</span>' ?></p>
    <?php endforeach; ?>
    <div class="actions-pile">
      <?= action($moi, $m, 'prolonger', '+1 minute', 'btn btn-ghost') ?>
      <?= action($moi, $m, 'terminer', 'Terminer maintenant et ouvrir le vote', 'btn btn-primary', [], 'Arrêter le chrono maintenant ?') ?>
    </div>
  </section>
  <?php endif; ?>

  <?php if ($m && $m['etat'] === 'vote'): ?>
  <section class="card">
    <h2>Vote en cours</h2>
    <p class="lead"><?= $nbVotes ?> vote(s) sur <?= max(0, $votants) ?> votant(s) possible(s).</p>
    <?php foreach ($enLice as $c): ?>
      <p>Copie <?= e($c['lettre']) ?> · <?= badge_equipe($c['equipe']) ?> <?= e($c['prenom']) ?> : <strong><?= $voix[(int)$c['id']] ?? 0 ?></strong></p>
    <?php endforeach; ?>
    <?php if (count($enLice) < count(array_filter([$m['equipe_a'], $m['equipe_b']]))): ?>
      <p class="muted">Copie manquante = forfait.</p>
    <?php endif; ?>
    <div class="actions-pile">
      <?= action($moi, $m, 'decompte', $m['decompte'] ? 'Masquer le décompte au vidéoprojecteur' : 'Afficher le décompte au vidéoprojecteur', 'btn btn-ghost') ?>
      <?= action($moi, $m, 'reveler', 'Clore le vote et révéler', 'btn btn-gold', [], 'Clore le vote et révéler le gagnant ?') ?>
    </div>
  </section>
  <?php endif; ?>

  <?php if ($m && $m['etat'] === 'revele'): ?>
  <section class="card">
    <h2>Résultat de la manche <?= (int)$m['numero'] ?></h2>
    <p class="lead"><?= $gagnants ? 'Gagnant : ' . implode(' et ', array_map('badge_equipe', $gagnants)) : 'Aucun vote : pas de vainqueur.' ?></p>
    <?php foreach ($enLice as $c): ?>
      <p>Copie <?= e($c['lettre']) ?> · <?= badge_equipe($c['equipe']) ?> <?= e($c['prenom']) ?> : <?= $voix[(int)$c['id']] ?? 0 ?> voix</p>
    <?php endforeach; ?>
    <?php if ($points): ?>
    <p class="brique-titre">Points (<?= BATTLE_POINTS ?> par équipe gagnante)</p>
    <div class="actions-pile">
      <?php foreach (array_filter([$m['equipe_a'], $m['equipe_b']]) as $eq):
          $donnes = points_source_donnee(battle_source((int)$m['id']), $eq); ?>
        <?= action($moi, $m, 'points', ($donnes ? '✓ ' . BATTLE_POINTS . ' pts donnés à ' : '+' . BATTLE_POINTS . ' pts à ') . $nomEquipe($eq) . ($donnes ? ' (reprendre)' : ''),
                   $donnes ? 'btn btn-gold' : 'btn btn-ghost', ['equipe' => $eq]) ?>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </section>
  <?php endif; ?>

<?php endif; ?>

  <section class="card">
    <a class="btn btn-ghost" href="<?= e(avec_f('pilotage.php?cle=' . rawurlencode(CLE_ANIMATEUR))) ?>">Retour au pilotage</a>
  </section>
</main>
<?php endif; ?>

<?php if ($table_ok): ?>
<script>
(function () {
  var EMPREINTE = <?= json_encode($empreinte) ?>;
  var URL_ETAT = <?= json_encode($moi . '&json=etat') ?>;

  // Chrono local, recalé à chaque sondage
  var chrono = document.getElementById('chrono');
  var fin = chrono ? Date.now() + 1000 * (+chrono.dataset.reste) : 0;
  function afficher() {
    var s = Math.max(0, Math.round((fin - Date.now()) / 1000));
    chrono.textContent = Math.floor(s / 60) + ':' + String(s % 60).padStart(2, '0');
    chrono.classList.toggle('urgent', s <= 30);
  }
  if (chrono) { afficher(); setInterval(afficher, 250); }

  // Roulette : juste après un tirage, les tâches défilent puis ralentissent jusqu'à la bonne
  var tache = document.getElementById('tache');
  if (tache && +tache.dataset.tirageAge < 5) {
    var finale = tache.textContent, liste = <?= json_encode(battle_taches(), JSON_UNESCAPED_UNICODE) ?>;
    var i = 0, delai = 60;
    tache.classList.add('roulette');
    (function tourner() {
      if (delai > 420) { tache.textContent = finale; tache.classList.remove('roulette'); tache.classList.add('tombe'); return; }
      tache.textContent = liste[i++ % liste.length];
      delai *= 1.13;
      setTimeout(tourner, delai);
    })();
  }

  var enSaisie = function () { var a = document.activeElement; return a && a.tagName === 'SELECT'; };
  setInterval(function () {
    if (enSaisie()) { return; }
    fetch(URL_ETAT).then(function (r) { return r.json(); }).then(function (d) {
      if (chrono && d.reste > 0) { fin = Date.now() + 1000 * d.reste; }
      if (d.empreinte !== EMPREINTE) { location.reload(); }
    }).catch(function () {});
  }, 2000);
})();
</script>
<?php endif; ?>

</body>
</html>
