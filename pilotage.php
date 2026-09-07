<?php
require __DIR__ . '/etapes.php';

// Accès réservé à l'animateur
$cle = $_REQUEST['cle'] ?? '';
if ($cle !== CLE_ANIMATEUR) {
    http_response_code(403);
    exit('Accès réservé. Ajoutez ?cle=... à l\'adresse.');
}

// POST puis redirection : évite de rejouer l'action en rafraîchissant la page
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'basculer') {
        $st = db()->prepare('UPDATE etapes SET ouverte = 1 - ouverte WHERE cle = ?');
        $st->execute([(string)($_POST['etape'] ?? '')]);

    } elseif ($action === 'suivante') {
        // Ouvre la première étape encore fermée, dans l'ordre du déroulé
        $st = db()->query('SELECT cle FROM etapes WHERE ouverte = 0 ORDER BY ordre LIMIT 1');
        if ($suivante = $st->fetchColumn()) {
            $st = db()->prepare('UPDATE etapes SET ouverte = 1 WHERE cle = ?');
            $st->execute([$suivante]);
        }

    } elseif ($action === 'tout_fermer') {
        db()->exec('UPDATE etapes SET ouverte = 0');

    } elseif ($action === 'quiz') {
        $st = db()->prepare('UPDATE quizzes SET actif = 1 - actif WHERE slug = ?');
        $st->execute([(string)($_POST['slug'] ?? '')]);
    }

    header('Location: pilotage.php?cle=' . rawurlencode(CLE_ANIMATEUR), true, 303);
    exit;
}

$etapes  = etapes_toutes() ?? [];
$quizzes = db()->query('SELECT slug, titre, actif FROM quizzes ORDER BY id')->fetchAll();
$reste   = count(array_filter($etapes, fn($e) => !$e['ouverte']));
?><!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title>Pilotage de la journée</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:wght@600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="style.css">
</head>
<body>

<header class="site-head">
  <h1>Pilotage de la journée</h1>
  <p>Ouvrez les étapes au rythme du groupe</p>
</header>

<main class="wrap">

  <?php if (!etapes_disponibles()): ?>
  <section class="card">
    <h2>Ouverture progressive inactive</h2>
    <p class="lead">
      La table <code>etapes</code> est absente : jouez <code>migration-etapes.sql</code> sur la base.
      En attendant, l'accueil affiche toutes les sections, comme avant.
    </p>
  </section>
  <?php endif; ?>

  <section class="card">
    <h2>Les étapes de l'accueil</h2>
    <p class="lead">Une étape fermée n'est pas envoyée aux participants : ils n'en voient que le titre.</p>

    <?php foreach ($etapes as $e): ?>
    <form method="post" class="pilot-row">
      <input type="hidden" name="cle" value="<?= e(CLE_ANIMATEUR) ?>">
      <input type="hidden" name="action" value="basculer">
      <input type="hidden" name="etape" value="<?= e($e['cle']) ?>">
      <span class="pilot-titre"><?= e($e['titre']) ?></span>
      <button class="btn btn-etat <?= $e['ouverte'] ? 'est-ouverte' : 'est-fermee' ?>">
        <?= $e['ouverte'] ? 'Ouverte' : 'Fermée' ?>
      </button>
    </form>
    <?php endforeach; ?>
  </section>

  <section class="card">
    <h2>Raccourcis</h2>
    <form method="post">
      <input type="hidden" name="cle" value="<?= e(CLE_ANIMATEUR) ?>">
      <input type="hidden" name="action" value="suivante">
      <button class="btn btn-primary" <?= $reste ? '' : 'disabled' ?>>
        <?= $reste ? 'Ouvrir l\'étape suivante' : 'Toutes les étapes sont ouvertes' ?>
      </button>
    </form>
    <form method="post">
      <input type="hidden" name="cle" value="<?= e(CLE_ANIMATEUR) ?>">
      <input type="hidden" name="action" value="tout_fermer">
      <button class="btn btn-ghost">Tout refermer (début de journée)</button>
    </form>
  </section>

  <section class="card">
    <h2>Les quiz</h2>
    <p class="lead">Un quiz fermé disparaît de l'accueil et n'accepte plus de réponse.</p>

    <?php foreach ($quizzes as $q): ?>
    <form method="post" class="pilot-row">
      <input type="hidden" name="cle" value="<?= e(CLE_ANIMATEUR) ?>">
      <input type="hidden" name="action" value="quiz">
      <input type="hidden" name="slug" value="<?= e($q['slug']) ?>">
      <span class="pilot-titre"><?= e($q['titre']) ?></span>
      <button class="btn btn-etat <?= $q['actif'] ? 'est-ouverte' : 'est-fermee' ?>">
        <?= $q['actif'] ? 'Ouvert' : 'Fermé' ?>
      </button>
    </form>
    <?php endforeach; ?>

    <p class="lead" style="margin-top:14px">
      L'étape « Les quiz » ci-dessus commande l'affichage du bloc sur l'accueil ;
      ces interrupteurs commandent chaque quiz individuellement.
    </p>
  </section>

  <section class="card">
    <h2>Voir la journée</h2>
    <a class="btn btn-ghost" href="index.php" target="_blank">Ouvrir l'accueil tel que le voient les participants</a>
    <a class="btn btn-ghost" href="resultats.php?cle=<?= rawurlencode(CLE_ANIMATEUR) ?>">Voir les résultats</a>
  </section>

</main>
</body>
</html>
