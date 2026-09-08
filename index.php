<?php
require __DIR__ . '/etapes.php';

$ouvertes = etapes_ouvertes();

// Suivi léger de l'ouverture des étapes, interrogé toutes les 10 s par la page
if (($_GET['json'] ?? '') === 'etat') {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ouvertes' => $ouvertes]);
    exit;
}

$quizzes = db()->query('SELECT slug, titre FROM quizzes WHERE actif = 1 ORDER BY id')->fetchAll();

// Documents à copier-coller (texte brut, prêt pour ChatGPT)
$cv_sam = <<<TXT
Sam Gamegie
34 ans — Vélizy — 06 12 34 56 78 — sam.gamegie@exemple.fr

EXPÉRIENCES
2021 – 2024 : Employé polyvalent — Jardinerie Verdemax, Versailles
(je m'occupais des plantes et un peu de la caisse)
2019 : Ouvrier espaces verts (intérim) — plusieurs missions
(tonte et taille)
2016 – 2018 : Agent d'entretien — Société Propre+ Services
(ménage dans des bureaux le soir)
Avant : différentes missions d'intérim (manutention)

FORMATION
2008 : Brevet des collèges
CAP Jardinier paysagiste (non terminé)

LOISIRS
Télé, jeux vidéo, potager
TXT;

$offre = <<<TXT
Jardinier / Agent d'entretien des espaces verts H/F
Les Jardins de la Rize — Villacoublay · CDD 6 mois évolutif · 35 h/semaine · Réf. JR-2026-072

Description du poste
Rattaché(e) au chef d'équipe, vous contribuez à l'entretien et à la valorisation du patrimoine végétal de nos clients (copropriétés, entreprises, collectivités). Dans le cadre de vos missions, vous assurez :
- la tonte, la taille de haies et d'arbustes, le débroussaillage,
- les plantations saisonnières et l'arrosage,
- l'entretien courant du matériel,
- de petits travaux de maçonnerie paysagère.

Profil recherché
- Autonomie, ponctualité, goût du travail en extérieur
- Sens du service et bon relationnel client
- Permis B exigé
- Une première expérience en espaces verts ou en jardinerie est un plus

Conditions
Travail en extérieur toutes saisons, port de charges, horaires aménagés en période estivale.
Rémunération : SMIC + panier repas + indemnité de déplacement.
TXT;

$mission1 = <<<TXT
Voici la situation de Sam. Propose un planning simple de sa semaine, jour par jour.

- Sam commence lundi aux Jardins de la Rize. Horaires : lundi, mardi, jeudi et vendredi de 8 h à 16 h ; mercredi de 6 h à 14 h (tournée d'arrosage avant les fortes chaleurs).
- Rendez-vous France Travail mercredi à 15 h 30.
- Sam doit aussi dans la semaine : préparer ses déjeuners à emporter, faire une lessive, faire les courses, et appeler sa banque (ouverte du lundi au vendredi, de 9 h à 17 h).
- Samedi matin, Sam garde sa nièce. Et Sam aimerait garder une soirée foot avec ses amis.
TXT;

$mission2 = <<<TXT
Résume cette note de service en 3 phrases simples : qu'est-ce qui change ? qui est concerné ? que faut-il faire ?

« NOTE DE SERVICE — À l'attention de l'ensemble des équipes d'entretien. Dans le cadre du plan de prévention des risques liés aux fortes chaleurs et afin de garantir la sécurité des collaborateurs sur les chantiers, il est porté à la connaissance du personnel que, à compter du lundi 15 du mois courant, les horaires d'intervention passent en régime estival : prise de poste à 6 h au dépôt et fin de journée à 13 h 30. La vérification du matériel de coupe ainsi que le contrôle des niveaux (carburant, huile) devront être effectués avant chaque départ et consignés dans le carnet prévu à cet effet, disponible au bureau du chef d'équipe. Le port des équipements de protection individuelle demeure obligatoire sur l'ensemble des chantiers, de même que l'hydratation régulière. Toute anomalie devra être signalée sans délai au chef d'équipe. La direction remercie l'ensemble des équipes pour leur implication. »
TXT;

$mission3 = <<<TXT
Voici la situation de Sam. Propose une liste de courses pour sa semaine, avec des idées de repas simples.

- Sam vit seul. Budget : 40 € pour la semaine.
- Pas de cantine au travail : il faut prévoir 5 déjeuners à emporter, plus les petits-déjeuners et des dîners simples.
- Sam a déjà chez lui : riz, pâtes, huile, sel et épices.
TXT;
?><!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="icon" href="assets/favicon-couleur.png">
<meta name="robots" content="noindex">
<title>Premiers pas avec l'IA générative</title>
<link rel="stylesheet" href="style.css">
</head>
<body>

<header class="site-head">
  <h1>Premiers pas avec l'IA générative</h1>
  <p>Les documents et les quiz de la journée, à portée de pouce</p>
</header>

<main class="wrap">

  <?php if (etape_ouverte('cv')): ?>
  <section class="card">
    <h2>Le CV de Sam</h2>
    <p class="lead">Copiez-le, puis collez-le dans votre conversation avec l'outil.</p>
    <button class="btn btn-gold" data-copy="cv">Copier le CV de Sam</button>
    <div class="copied-note" id="note-cv">C'est copié ! Collez-le dans l'outil.</div>
    <details class="doc">
      <summary>Voir le texte</summary>
      <pre id="txt-cv"><?= e($cv_sam) ?></pre>
    </details>
  </section>
  <?php else: echo carte_verrouillee('cv'); endif; ?>

  <?php if (etape_ouverte('offre')): ?>
  <section class="card">
    <h2>L'offre d'emploi</h2>
    <p class="lead">Le poste que vise Sam, aux Jardins de la Rize.</p>
    <button class="btn btn-gold" data-copy="offre">Copier l'offre d'emploi</button>
    <div class="copied-note" id="note-offre">C'est copié ! Collez-la dans l'outil.</div>
    <details class="doc">
      <summary>Voir le texte</summary>
      <pre id="txt-offre"><?= e($offre) ?></pre>
    </details>
  </section>
  <?php else: echo carte_verrouillee('offre'); endif; ?>

  <?php if (etape_ouverte('missions')): ?>
  <section class="card">
    <h2>Défi chrono — les 3 missions</h2>
    <p class="lead">Copiez la mission de votre équipe, puis collez-la dans l'outil. Tout le contexte y est déjà.</p>

    <button class="btn btn-gold" data-copy="m1">Copier la mission 1 — La semaine de Sam</button>
    <div class="copied-note" id="note-m1">C'est copié ! Collez-la dans l'outil.</div>
    <details class="doc">
      <summary>Voir le texte</summary>
      <pre id="txt-m1"><?= e($mission1) ?></pre>
    </details>

    <button class="btn btn-gold" style="margin-top:16px" data-copy="m2">Copier la mission 2 — La note de service</button>
    <div class="copied-note" id="note-m2">C'est copié ! Collez-la dans l'outil.</div>
    <details class="doc">
      <summary>Voir le texte</summary>
      <pre id="txt-m2"><?= e($mission2) ?></pre>
    </details>

    <button class="btn btn-gold" style="margin-top:16px" data-copy="m3">Copier la mission 3 — Les courses de Sam</button>
    <div class="copied-note" id="note-m3">C'est copié ! Collez-la dans l'outil.</div>
    <details class="doc">
      <summary>Voir le texte</summary>
      <pre id="txt-m3"><?= e($mission3) ?></pre>
    </details>
  </section>
  <?php else: echo carte_verrouillee('missions'); endif; ?>

  <?php if (etape_ouverte('quiz')): ?>
  <section class="card">
    <h2>Les quiz</h2>
    <p class="lead">Touchez un quiz pour commencer. Votre prénom suffit.</p>
    <?php foreach ($quizzes as $q): ?>
      <a class="btn btn-primary" href="quiz.php?slug=<?= e($q['slug']) ?>">
        <?= e($q['titre']) ?>
      </a>
    <?php endforeach; ?>
    <?php if (!$quizzes): ?>
      <p class="lead">Aucun quiz ouvert pour le moment.</p>
    <?php endif; ?>
  </section>
  <?php else: echo carte_verrouillee('quiz'); endif; ?>

  <?php if (etape_ouverte('avis')): ?>
  <section class="card">
    <h2>Votre avis</h2>
    <p class="lead">En fin de journée : 2 minutes, anonyme, pour améliorer la prochaine session.</p>
    <a class="btn btn-ghost" href="satisfaction.php">Donner mon avis sur la journée</a>
  </section>
  <?php else: echo carte_verrouillee('avis'); endif; ?>

  <p class="rgpd">
    Seul votre prénom et votre score sont enregistrés, pour le suivi de la formation.
    Aucune autre donnée personnelle n'est collectée. Suppression sur simple demande
    auprès du formateur.
  </p>

</main>

<div class="bandeau" id="bandeau" hidden>
  <span>Une nouvelle étape est disponible.</span>
  <button class="btn btn-gold" id="btn-afficher">Afficher</button>
</div>

<script>
// Suivi des étapes : le formateur les ouvre depuis pilotage.php
(function () {
  var OUVERTES = <?= json_encode($ouvertes) ?>;
  var bandeau = document.getElementById('bandeau');

  document.getElementById('btn-afficher').addEventListener('click', function () {
    location.reload();
  });

  setInterval(function () {
    fetch('index.php?json=etat').then(function (r) { return r.json(); }).then(function (d) {
      var nouvelles = d.ouvertes.filter(function (c) { return OUVERTES.indexOf(c) < 0; });
      if (nouvelles.length) {
        bandeau.hidden = false;          // on laisse le participant choisir son moment
      } else if (d.ouvertes.length < OUVERTES.length) {
        location.reload();               // une étape a été refermée : on applique tout de suite
      }
    }).catch(function () {});
  }, 10000);
})();

// Copie vers le presse-papiers, avec repli pour les anciens téléphones
document.querySelectorAll('[data-copy]').forEach(function (btn) {
  btn.addEventListener('click', function () {
    var id = btn.getAttribute('data-copy');
    var text = document.getElementById('txt-' + id).textContent;
    var note = document.getElementById('note-' + id);
    function done() {
      note.classList.add('show');
      setTimeout(function () { note.classList.remove('show'); }, 3000);
    }
    if (navigator.clipboard && navigator.clipboard.writeText) {
      navigator.clipboard.writeText(text).then(done).catch(function () { fallback(); });
    } else {
      fallback();
    }
    function fallback() {
      var ta = document.createElement('textarea');
      ta.value = text;
      ta.style.position = 'fixed';
      ta.style.opacity = '0';
      document.body.appendChild(ta);
      ta.select();
      try { document.execCommand('copy'); done(); } catch (e) {}
      document.body.removeChild(ta);
    }
  });
});
</script>

</body>
</html>
