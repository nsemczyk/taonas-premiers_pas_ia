<?php
// Accueil du niveau 1 : les cartes de la journée, dans l'ordre du déroulé.
// Inclus par index.php, dans <main> ; les clés d'étape (cv, offre…) sont
// celles de la table `etapes` pour cette formation.

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
?>
  <?php if (etape_ouverte('cv')): ?>
  <section class="card">
    <h2>Le CV de Sam</h2>
    <p class="lead">Copiez-le, puis collez-le dans votre conversation avec l'outil.</p>
    <?= texte_a_copier('cv', 'Copier le CV de Sam', 'C\'est copié ! Collez-le dans l\'outil.', $cv_sam) ?>
  </section>
  <?php else: echo carte_verrouillee('cv'); endif; ?>

  <?php if (etape_ouverte('offre')): ?>
  <section class="card">
    <h2>L'offre d'emploi</h2>
    <p class="lead">Le poste que vise Sam, aux Jardins de la Rize.</p>
    <?= texte_a_copier('offre', 'Copier l\'offre d\'emploi', 'C\'est copié ! Collez-la dans l\'outil.', $offre) ?>
  </section>
  <?php else: echo carte_verrouillee('offre'); endif; ?>

  <?php if (etape_ouverte('missions')): ?>
  <section class="card">
    <h2>Défi chrono — les 3 missions</h2>
    <p class="lead">Copiez la mission de votre équipe, puis collez-la dans l'outil. Tout le contexte y est déjà.</p>

    <?= texte_a_copier('m1', 'Copier la mission 1 — La semaine de Sam', 'C\'est copié ! Collez-la dans l\'outil.', $mission1) ?>

    <?= texte_a_copier('m2', 'Copier la mission 2 — La note de service', 'C\'est copié ! Collez-la dans l\'outil.', $mission2, 'margin-top:16px') ?>

    <?= texte_a_copier('m3', 'Copier la mission 3 — Les courses de Sam', 'C\'est copié ! Collez-la dans l\'outil.', $mission3, 'margin-top:16px') ?>
  </section>
  <?php else: echo carte_verrouillee('missions'); endif; ?>

  <?= bloc_quiz('quiz') ?>

  <?= bloc_avis('avis') ?>
