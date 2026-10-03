<?php
require __DIR__ . '/core/etapes.php';
require __DIR__ . '/exercices/mur/fonctions.php';

// Accès réservé à l'animateur
$cle = $_REQUEST['cle'] ?? '';
if ($cle !== CLE_ANIMATEUR) {
    http_response_code(403);
    exit('Accès réservé. Ajoutez ?cle=... à l\'adresse.');
}

// POST puis redirection : évite de rejouer l'action en rafraîchissant la page
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // Chaque action ne touche que la formation pilotée
    $f = formation_slug();

    if ($action === 'basculer') {
        $st = db()->prepare('UPDATE etapes SET ouverte = 1 - ouverte WHERE formation = ? AND cle = ?');
        $st->execute([$f, (string)($_POST['etape'] ?? '')]);

    } elseif ($action === 'suivante') {
        // Ouvre la première étape encore fermée, dans l'ordre du déroulé
        $st = db()->prepare('SELECT cle FROM etapes WHERE formation = ? AND ouverte = 0 ORDER BY ordre LIMIT 1');
        $st->execute([$f]);
        if ($suivante = $st->fetchColumn()) {
            $st = db()->prepare('UPDATE etapes SET ouverte = 1 WHERE formation = ? AND cle = ?');
            $st->execute([$f, $suivante]);
        }

    } elseif ($action === 'tout_fermer') {
        $st = db()->prepare('UPDATE etapes SET ouverte = 0 WHERE formation = ?');
        $st->execute([$f]);

    } elseif ($action === 'quiz') {
        $st = db()->prepare('UPDATE quizzes SET actif = 1 - actif WHERE formation = ? AND slug = ?');
        $st->execute([$f, (string)($_POST['slug'] ?? '')]);

    } elseif ($action === 'masquer_objectif') {
        objectif_masquer((int)($_POST['id'] ?? 0), !empty($_POST['anonyme']));
    }

    header('Location: ' . avec_f('pilotage.php?cle=' . rawurlencode(CLE_ANIMATEUR)), true, 303);
    exit;
}

// Tables attendues par les outils de la formation, et le script qui les crée
$manquantes = [];
if (formation()['outils'] ?? []) {
    foreach (['participants' => 'exercices/equipes/migration.sql', 'objectifs.anonyme' => 'exercices/mur/migration.sql',
              'boule_briques' => 'exercices/boule/migration.sql', 'points' => 'exercices/scores/migration.sql',
              'battle_votes' => 'exercices/battle/migration.sql',
              'bocal_copies' => 'exercices/bocal/migration.sql'] as $cible => $script) {
        [$table, $colonne] = explode('.', $cible . '.1');   // table ou table.colonne attendue
        try {
            db()->query("SELECT $colonne FROM `$table` LIMIT 1");
        } catch (PDOException $e) {
            $manquantes[$cible] = $script;
        }
    }
}
try {
    db()->query('SELECT formation FROM etapes LIMIT 1');
} catch (PDOException $e) {
    $manquantes['etapes.formation'] = 'migration-formations.sql';
}

etapes_synchroniser();
$etapes  = etapes_toutes() ?? [];
$quizzes = [];
if (!isset($manquantes['etapes.formation'])) {   // sinon la page n'affiche que la migration à jouer
    $st = db()->prepare('SELECT slug, titre, actif FROM quizzes WHERE formation = ? ORDER BY id');
    $st->execute([formation_slug()]);
    $quizzes = $st->fetchAll();
}
$reste   = count(array_filter($etapes, fn($e) => !$e['ouverte']));
?><!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="icon" href="assets/favicon-couleur.png">
<meta name="robots" content="noindex">
<title>Pilotage de la journée</title>
<link rel="stylesheet" href="style.css">
</head>
<body>

<header class="site-head">
  <h1>Pilotage de la journée</h1>
  <p>Ouvrez les étapes au rythme du groupe</p>
</header>

<main class="wrap">

  <?= selecteur_formation('pilotage.php?cle=' . rawurlencode(CLE_ANIMATEUR)) ?>

  <?php if ($manquantes): ?>
  <section class="card err">
    <h2>Migration à jouer</h2>
    <p class="lead">Il manque en base de quoi faire fonctionner cette formation. Jouez, dans l'ordre,
      avec un compte administrateur :</p>
    <?php foreach (array_unique($manquantes) as $script): ?>
    <pre>mysql --default-character-set=utf8mb4 -u root -p formation_ia &lt; <?= e($script) ?></pre>
    <?php endforeach; ?>
  </section>
  <?php endif; ?>

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

  <?php if ($outils = formation()['outils'] ?? []): ?>
  <section class="card">
    <h2>Outils de la journée</h2>
    <p class="lead">Déclarés dans la configuration de la formation ; chacun s'ouvre dans un nouvel onglet.</p>
    <?php foreach ($outils as $page => $libelle): ?>
    <a class="btn btn-primary" target="_blank"
       href="<?= e(avec_f($page . '?cle=' . rawurlencode(CLE_ANIMATEUR))) ?>"><?= e($libelle) ?></a>
    <?php endforeach; ?>
  </section>
  <?php endif; ?>

  <?php if (isset($outils['mur.php']) && !$manquantes): $objectifs = objectifs_seance();
        usort($objectifs, fn($a, $b) => strcasecmp($a['prenom'], $b['prenom'])); ?>
  <section class="card">
    <h2>Objectifs du jour <span class="muted">· <?= count($objectifs) ?></span></h2>
    <p class="lead">Pour vous seul : qui a écrit quoi, même quand le prénom est masqué au tableau.</p>
    <?php foreach ($objectifs as $o): ?>
    <form method="post" class="pilot-row objectif-ligne">
      <input type="hidden" name="cle" value="<?= e(CLE_ANIMATEUR) ?>">
      <input type="hidden" name="action" value="masquer_objectif">
      <input type="hidden" name="id" value="<?= (int)$o['id'] ?>">
      <input type="hidden" name="anonyme" value="<?= $o['anonyme'] ? '' : '1' ?>">
      <span class="pilot-titre"><strong><?= e($o['prenom']) ?></strong> — <?= e($o['texte']) ?></span>
      <button class="btn btn-etat <?= $o['anonyme'] ? 'est-fermee' : 'est-ouverte' ?>"
              title="<?= $o['anonyme'] ? 'Réafficher le prénom au tableau' : 'Masquer le prénom au tableau' ?>">
        <?= $o['anonyme'] ? 'Prénom masqué' : 'Prénom affiché' ?>
      </button>
    </form>
    <?php endforeach; ?>
    <?php if (!$objectifs): ?><p class="muted">Aucun objectif pour l'instant.</p><?php endif; ?>
  </section>
  <?php endif; ?>

  <section class="card">
    <h2>Voir la journée</h2>
    <a class="btn btn-ghost" href="<?= e(avec_f('index.php')) ?>" target="_blank">Ouvrir l'accueil tel que le voient les participants</a>
    <a class="btn btn-ghost" href="<?= e(avec_f('resultats.php?cle=' . rawurlencode(CLE_ANIMATEUR))) ?>">Voir les résultats</a>
  </section>

</main>
</body>
</html>
