<?php
require __DIR__ . '/core/participants.php';

// Accès réservé à l'animateur
$cle = $_REQUEST['cle'] ?? '';
if (!hash_equals(CLE_ANIMATEUR, (string)$cle)) {
    http_response_code(403);
    exit('Accès réservé. Ajoutez ?cle=... à l\'adresse.');
}

$moi     = avec_f('equipes.php?cle=' . rawurlencode(CLE_ANIMATEUR));
$equipes = equipes();
$max     = equipe_max();
$seance  = [formation_slug(), aujourdhui_local()];

// Empreinte de la séance, sondée par la page : elle se recharge dès qu'un
// participant arrive ou qu'une affectation change (depuis un autre appareil aussi)
function empreinte(array $participants): string
{
    return md5(json_encode(array_map(fn($p) => [$p['id'], $p['equipe']], $participants)));
}

if (($_GET['json'] ?? '') === 'etat') {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['empreinte' => empreinte(participants_seance())]);
    exit;
}

// POST puis redirection : pas de rejeu au rafraîchissement
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $equipes) {
    $action = $_POST['action'] ?? '';
    $id     = (int)($_POST['id'] ?? 0);

    if ($action === 'affecter') {
        $equipe = (string)($_POST['equipe'] ?? '');
        if ($equipe === '') {
            $st = db()->prepare('UPDATE participants SET equipe = NULL WHERE id = ? AND formation = ? AND seance = ?');
            $st->execute([$id, ...$seance]);
        } elseif (equipe_existe($equipe)) {
            // Une équipe complète refuse un membre de plus
            $st = db()->prepare('SELECT COUNT(*) FROM participants
                                 WHERE formation = ? AND seance = ? AND equipe = ? AND id <> ?');
            $st->execute([...$seance, $equipe, $id]);
            if ((int)$st->fetchColumn() < $max) {
                $st = db()->prepare('UPDATE participants SET equipe = ? WHERE id = ? AND formation = ? AND seance = ?');
                $st->execute([$equipe, $id, ...$seance]);
            }
        }

    } elseif ($action === 'repartir') {
        // Tirage au sort équilibré sur les n premières équipes ; au-delà de
        // n × max participants, les derniers tirés restent à placer à la main.
        $n   = max(1, min(count($equipes), (int)($_POST['n'] ?? 0)));
        $cles = array_slice(array_keys($equipes), 0, $n);
        $ids  = array_column(participants_seance(), 'id');
        shuffle($ids);
        $st = db()->prepare('UPDATE participants SET equipe = ? WHERE id = ? AND formation = ? AND seance = ?');
        foreach ($ids as $i => $pid) {
            $st->execute([$i < $n * $max ? $cles[$i % $n] : null, $pid, ...$seance]);
        }

    } elseif ($action === 'vider') {
        $st = db()->prepare('UPDATE participants SET equipe = NULL WHERE formation = ? AND seance = ?');
        $st->execute($seance);

    } elseif ($action === 'supprimer') {
        $st = db()->prepare('DELETE FROM participants WHERE id = ? AND formation = ? AND seance = ?');
        $st->execute([$id, ...$seance]);
    }

    header('Location: ' . $moi, true, 303);
    exit;
}

$table_ok     = participants_table_ok();
$participants = participants_seance();
$parEquipe    = array_fill_keys(array_keys($equipes), []);
$aPlacer      = [];
foreach ($participants as $p) {
    if (isset($parEquipe[$p['equipe']])) {
        $parEquipe[$p['equipe']][] = $p;
    } else {
        $aPlacer[] = $p;
    }
}
?><!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="icon" href="assets/favicon-couleur.png">
<meta name="robots" content="noindex">
<title>Équipes de la séance</title>
<link rel="stylesheet" href="style.css">
</head>
<body>

<header class="site-head">
  <h1>Équipes de la séance</h1>
  <p><?= e(formation()['titre']) ?> · <?= count($participants) ?> participant<?= count($participants) > 1 ? 's' : '' ?> arrivé<?= count($participants) > 1 ? 's' : '' ?></p>
</header>

<main class="wrap">

  <?= selecteur_formation('equipes.php?cle=' . rawurlencode(CLE_ANIMATEUR)) ?>

<?php if (!$table_ok): ?>
  <section class="card">
    <h2>Migration à jouer</h2>
    <p>La table des participants n'existe pas encore. Jouez une fois, avec un compte administrateur&nbsp;:</p>
    <pre>mysql --default-character-set=utf8mb4 -u root -p formation_ia &lt; migration-equipes.sql</pre>
  </section>

<?php elseif (!$equipes): ?>
  <section class="card">
    <h2>Pas d'équipes pour cette formation</h2>
    <p class="lead">Déclarez-les dans <code>formations/<?= e(formation_slug()) ?>/formation.php</code>
      (clés <code>equipes</code> et <code>equipe_max</code>).</p>
  </section>

<?php else: ?>

  <?php if (!$participants): ?>
  <section class="card">
    <h2>Personne pour l'instant</h2>
    <p class="lead">Les participants apparaissent ici dès qu'ils ont saisi leur prénom sur l'accueil.
      La page se met à jour toute seule.</p>
  </section>
  <?php endif; ?>

  <?php foreach ($participants ? $parEquipe : [] as $cleEq => $membres): ?>
  <section class="card equipe-carte" style="--equipe:<?= e($equipes[$cleEq]['couleur'] ?? '#1B3E90') ?>">
    <h2><?= e($equipes[$cleEq]['nom']) ?> <span class="muted">· <?= count($membres) ?> / <?= $max ?></span></h2>
    <?php if ($membres): ?>
      <ul class="membres">
        <?php foreach ($membres as $m): ?><li><?= e($m['prenom']) ?></li><?php endforeach; ?>
      </ul>
    <?php else: ?>
      <p class="muted">Personne dans cette équipe.</p>
    <?php endif; ?>
  </section>
  <?php endforeach; ?>

  <?php if ($participants): ?>
  <section class="card">
    <h2>Placer les participants</h2>
    <p class="lead">
      <?= $aPlacer ? count($aPlacer) . ' à placer. ' : 'Tout le monde est placé. ' ?>
      Touchez une équipe pour y placer la personne ; une équipe complète est grisée.
    </p>
    <?php foreach ($participants as $p): ?>
    <div class="placement">
      <span class="placement-prenom"><?= e($p['prenom']) ?></span>
      <div class="placement-choix">
        <?php foreach ($equipes as $cleEq => $eq):
            $actuelle = $p['equipe'] === $cleEq;
            $pleine   = !$actuelle && count($parEquipe[$cleEq]) >= $max; ?>
        <form method="post" action="<?= e($moi) ?>">
          <input type="hidden" name="action" value="affecter">
          <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
          <input type="hidden" name="equipe" value="<?= e($actuelle ? '' : $cleEq) ?>">
          <button class="choix-equipe<?= $actuelle ? ' est-choisie' : '' ?>"
                  style="--equipe:<?= e($eq['couleur'] ?? '#1B3E90') ?>"
                  <?= $pleine ? 'disabled' : '' ?>
                  title="<?= e($actuelle ? 'Retirer de l\'équipe' : 'Placer dans ' . $eq['nom']) ?>"><?= e($eq['nom']) ?></button>
        </form>
        <?php endforeach; ?>
        <form method="post" action="<?= e($moi) ?>"
              onsubmit="return confirm('Retirer ce participant de la séance ?');">
          <input type="hidden" name="action" value="supprimer">
          <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
          <button class="choix-suppr" title="Retirer de la séance (doublon, erreur de prénom…)">✕</button>
        </form>
      </div>
    </div>
    <?php endforeach; ?>
  </section>

  <section class="card">
    <h2>Tirage au sort</h2>
    <p class="lead">Répartit tout le monde au hasard, en équipes équilibrées de <?= $max ?> au plus.
      Les affectations en cours sont remplacées.</p>
    <div class="actions-pile">
    <?php for ($n = 2; $n <= count($equipes); $n++): ?>
    <form method="post" action="<?= e($moi) ?>" onsubmit="return confirm('Refaire toutes les équipes au hasard ?');">
      <input type="hidden" name="action" value="repartir">
      <input type="hidden" name="n" value="<?= $n ?>">
      <button class="btn btn-primary">Au hasard en <?= $n ?> équipes</button>
    </form>
    <?php endfor; ?>
    <form method="post" action="<?= e($moi) ?>" onsubmit="return confirm('Vider toutes les équipes ?');">
      <input type="hidden" name="action" value="vider">
      <button class="btn btn-ghost">Vider les équipes</button>
    </form>
    </div>
  </section>
  <?php endif; ?>

<?php endif; ?>

  <section class="card">
    <a class="btn btn-ghost" href="<?= e(avec_f('pilotage.php?cle=' . rawurlencode(CLE_ANIMATEUR))) ?>">Retour au pilotage</a>
    <a class="btn btn-ghost" href="<?= e(avec_f('index.php')) ?>" target="_blank">Ouvrir l'accueil des participants</a>
  </section>

</main>

<?php if ($table_ok && $equipes): ?>
<script>
// Arrivées et affectations en direct, sans perdre la main : on ne recharge
// que si quelque chose a changé
(function () {
  var EMPREINTE = <?= json_encode(empreinte($participants)) ?>;
  setInterval(function () {
    fetch(<?= json_encode($moi . '&json=etat') ?>)
      .then(function (r) { return r.json(); })
      .then(function (d) { if (d.empreinte !== EMPREINTE) { location.reload(); } })
      .catch(function () {});
  }, 5000);
})();
</script>
<?php endif; ?>

</body>
</html>
