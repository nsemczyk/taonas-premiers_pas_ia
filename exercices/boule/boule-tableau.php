<?php
// Le prompt boule de neige, côté formateur : une colonne par équipe, une ligne
// par étape, rempli en direct. Pensé pour le vidéoprojecteur : « Masquer le
// contenu » ne montre que l'avancement pendant le jeu, puis on dévoile tout
// au débrief. Chaque équipe peut revenir d'une étape (faute, validation par erreur).
require __DIR__ . '/fonctions.php';
require __DIR__ . '/../../core/points.php';

// Accès réservé à l'animateur
$cle = (string)($_REQUEST['cle'] ?? '');
if (!hash_equals(CLE_ANIMATEUR, $cle)) {
    http_response_code(403);
    exit('Accès réservé. Ajoutez ?cle=... à l\'adresse.');
}
$moi = avec_f('boule-tableau.php?cle=' . rawurlencode(CLE_ANIMATEUR));

// Équipes qui jouent : celles qui ont au moins un membre
$jouent = array_values(array_filter(array_keys(equipes()), fn($eq) => boule_membres($eq)));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $equipe = (string)($_POST['equipe'] ?? '');
    if (equipe_existe($equipe)) {
        if (($_POST['action'] ?? '') === 'annuler') {
            boule_annuler($equipe);
        } elseif (($_POST['action'] ?? '') === 'points' && points_actifs()) {
            points_source_basculer('boule', $equipe, POINTS_BOULE, 'Prompt boule de neige');
        } elseif (($_POST['action'] ?? '') === 'tache') {
            boule_changer_tache($equipe, (string)($_POST['tache'] ?? ''));
        }
    }
    header('Location: ' . $moi, true, 303);
    exit;
}

$table_ok = true;
$etats    = [];
$points   = points_actifs() && points_table_ok();
$donnes   = [];   // équipes qui ont déjà reçu les points de la partie
try {
    foreach ($jouent as $eq) {
        $etats[$eq] = boule_etat($eq);
        $donnes[$eq] = $points && points_source_donnee('boule', $eq);
    }
} catch (PDOException $e) {
    $table_ok = false;
}

// Empreinte de toutes les parties : la page se recharge quand l'une d'elles avance
$empreinte = md5(json_encode([array_map(fn($e) => [
    $e['etape'], $e['tache'], array_column($e['membres'], 'id'), array_keys($e['briques']),
], $etats), $donnes]));

if (($_GET['json'] ?? '') === 'etat') {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['empreinte' => $empreinte]);
    exit;
}
?><!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="icon" href="assets/favicon-couleur.png">
<meta name="robots" content="noindex">
<title>Prompt boule de neige — tableau</title>
<link rel="stylesheet" href="style.css">
<style>
body.boule-tableau { background: var(--blanc); margin: 0; }
.bt-tete {
  display: flex; flex-wrap: wrap; align-items: center; gap: 12px;
  padding: 14px 24px; background: var(--nuit); color: var(--blanc);
}
.bt-tete h1 { margin: 0; flex: 1; font-family: var(--font-titre); font-weight: 400; font-size: 1.9rem; color: var(--blanc); }
.bt-tete button, .bt-tete a {
  font: inherit; font-size: .9rem; padding: 7px 14px; cursor: pointer; text-decoration: none;
  background: transparent; color: var(--clair); border: 2px solid var(--clair);
}
.bt-tete button[aria-pressed="true"] { background: var(--vif); color: var(--nuit); border-color: var(--vif); }
.bt-grille {
  display: grid; gap: 18px; padding: 18px 24px 28px;
  grid-template-columns: repeat(var(--colonnes, 3), minmax(0, 1fr));
}
@media (max-width: 800px) { .bt-grille { grid-template-columns: 1fr; } }
.bt-equipe { background: var(--paper); border-top: 10px solid var(--equipe); box-shadow: 0 2px 8px rgba(21,22,44,.12); display: flex; flex-direction: column; }
.bt-equipe-tete { padding: 12px 16px; border-bottom: 1px solid var(--gris); }
.bt-equipe-tete h2 { margin: 0 0 4px; font-size: 1.5rem; }
.bt-tache { margin: 0; font-size: 1.15rem; font-weight: 600; color: var(--nuit); }
.bt-tache select { font: inherit; font-size: .95rem; width: 100%; padding: 6px; margin-top: 4px; }
.bt-cellule { padding: 12px 16px; border-bottom: 1px solid var(--glacier); }
.bt-cellule:last-child { border-bottom: none; }
.bt-etiquette { display: flex; justify-content: space-between; gap: 8px; font-size: .9rem; color: var(--indigo); margin: 0 0 4px; }
.bt-etiquette strong { color: var(--marque); }
.bt-texte { margin: 0; font-size: 1.2rem; line-height: 1.35; white-space: pre-wrap; overflow-wrap: anywhere; color: var(--nuit); }
.bt-reponse { max-height: 16em; overflow: auto; font-size: 1.05rem; background: var(--glacier); padding: 10px 12px; }
.bt-attente { color: var(--indigo); font-style: italic; }
.bt-cellule.en-cours { background: #FFF8D6; }
.bt-cellule.a-venir .bt-etiquette { opacity: .55; }
.bt-valide { font-size: 1.1rem; color: var(--olive); font-weight: 600; margin: 0; }
.bt-annuler { margin: 0 16px 14px; }
.bt-points { margin: auto 16px 10px; }
.bt-points button {
  font: inherit; font-size: 1.1rem; font-weight: 700; padding: 8px 16px; cursor: pointer;
  background: var(--paper); color: var(--olive); border: 2px solid var(--olive);
}
.bt-points button.donnes { background: var(--olive); color: var(--blanc); }
.bt-annuler button { font: inherit; font-size: .85rem; padding: 6px 12px; cursor: pointer; background: var(--paper); color: var(--brique); border: 2px solid var(--brique); }
/* Pendant le jeu : l'avancement seulement, jamais le contenu */
.masque .bt-texte, .masque .bt-reponse { display: none; }
.bt-valide { display: none; }
.masque .bt-valide { display: block; }
.bt-vide { padding: 30px 24px; font-size: 1.2rem; }
</style>
</head>
<body class="boule-tableau">

<header class="bt-tete">
  <h1>Le prompt boule de neige</h1>
  <button type="button" id="btn-masquer" aria-pressed="false">Masquer le contenu</button>
  <a href="<?= e(avec_f('pilotage.php?cle=' . rawurlencode(CLE_ANIMATEUR))) ?>">Pilotage</a>
</header>

<?php if (!$table_ok): ?>
  <p class="bt-vide">Tables absentes : jouez <code>exercices/boule/migration.sql</code>.</p>
<?php elseif (!$jouent): ?>
  <p class="bt-vide">Aucune équipe constituée pour l'instant : le tableau se remplira dès que les équipes seront formées.</p>
<?php else: ?>
<main class="bt-grille" id="grille" style="--colonnes:<?= count($jouent) ?>">
  <?php foreach ($etats as $eq => $etat): $def = equipes()[$eq]; ?>
  <section class="bt-equipe" style="--equipe:<?= e($def['couleur'] ?? '#1B3E90') ?>">
    <div class="bt-equipe-tete">
      <h2><?= e($def['nom']) ?></h2>
      <?php if (!$etat['briques'] && count(boule_taches()) > 1): ?>
        <form method="post" action="<?= e($moi) ?>" class="bt-tache">
          <input type="hidden" name="action" value="tache">
          <input type="hidden" name="equipe" value="<?= e($eq) ?>">
          <select name="tache" onchange="this.form.submit()" aria-label="Situation de l'équipe">
            <?php foreach (boule_taches() as $t): ?>
              <option<?= $t === $etat['tache'] ? ' selected' : '' ?>><?= e($t) ?></option>
            <?php endforeach; ?>
          </select>
        </form>
      <?php else: ?>
        <p class="bt-tache"><?= e($etat['tache']) ?></p>
      <?php endif; ?>
    </div>

    <?php foreach (BOULE_ETAPES as $n => $etapeDef):
        $b = $etat['briques'][$n] ?? null;
        $joueur = $b['prenom'] ?? (boule_joueur($etat['membres'], $n)['prenom'] ?? '?');
        $classe = $b ? 'faite' : ($n === $etat['etape'] ? 'en-cours' : 'a-venir'); ?>
    <div class="bt-cellule <?= $classe ?>">
      <p class="bt-etiquette"><span><?= $n ?>. <?= e($etapeDef['titre']) ?></span> <strong><?= e($joueur) ?></strong></p>
      <?php if ($b): ?>
        <p class="bt-valide">✅ Validé</p>
        <p class="bt-texte<?= $n > BOULE_DERNIERE_BRIQUE ? ' bt-reponse' : '' ?>"><?= e($b['texte']) ?></p>
      <?php elseif ($n === $etat['etape']): ?>
        <p class="bt-attente">En train d'écrire…</p>
      <?php endif; ?>
    </div>
    <?php endforeach; ?>

    <?php if ($points): ?>
    <form method="post" action="<?= e($moi) ?>" class="bt-points">
      <input type="hidden" name="action" value="points">
      <input type="hidden" name="equipe" value="<?= e($eq) ?>">
      <button class="<?= $donnes[$eq] ? 'donnes' : '' ?>"
              title="<?= $donnes[$eq] ? 'Reprendre les points' : 'Donner les points de la partie à cette équipe' ?>">
        <?= $donnes[$eq] ? '✓ ' . POINTS_BOULE . ' pts donnés' : '+' . POINTS_BOULE . ' pts' ?>
      </button>
    </form>
    <?php endif; ?>

    <?php if ($etat['briques']): ?>
    <form method="post" action="<?= e($moi) ?>" class="bt-annuler"
          onsubmit="return confirm('Annuler la dernière étape de l\'équipe <?= e(addslashes($def['nom'])) ?> ? Elle reviendra au même joueur.');">
      <input type="hidden" name="action" value="annuler">
      <input type="hidden" name="equipe" value="<?= e($eq) ?>">
      <button>Annuler la dernière étape</button>
    </form>
    <?php endif; ?>
  </section>
  <?php endforeach; ?>
</main>
<?php endif; ?>

<script>
(function () {
  var grille = document.getElementById('grille');
  var bouton = document.getElementById('btn-masquer');
  var CLE = 'boule-masque-' + <?= json_encode(formation_slug()) ?>;
  var masque = false;
  try { masque = localStorage.getItem(CLE) === '1'; } catch (e) {}
  function appliquer() {
    if (grille) { grille.classList.toggle('masque', masque); }
    bouton.textContent = masque ? 'Afficher le contenu' : 'Masquer le contenu';
    bouton.setAttribute('aria-pressed', masque ? 'true' : 'false');
  }
  bouton.addEventListener('click', function () {
    masque = !masque;
    try { localStorage.setItem(CLE, masque ? '1' : '0'); } catch (e) {}
    appliquer();
  });
  appliquer();

  // Suivi en direct : rechargement seulement quand une partie avance, à la même position
  var EMPREINTE = <?= json_encode($empreinte) ?>;
  try {
    var y = sessionStorage.getItem('boule-defilement');
    if (y) { window.scrollTo(0, +y); sessionStorage.removeItem('boule-defilement'); }
  } catch (e) {}
  setInterval(function () {
    fetch(<?= json_encode($moi . '&json=etat') ?>)
      .then(function (r) { return r.json(); })
      .then(function (d) {
        if (d.empreinte !== EMPREINTE) {
          try { sessionStorage.setItem('boule-defilement', String(window.scrollY)); } catch (e) {}
          location.reload();
        }
      })
      .catch(function () {});
  }, 3000);
})();
</script>

</body>
</html>
