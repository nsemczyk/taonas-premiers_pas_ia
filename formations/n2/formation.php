<?php
// Niveau 2 — titre provisoire, à remplacer.
// Réglages lus par core/formation.php ; le contenu de l'accueil est dans accueil.php.
return [
    'titre'       => 'IA générative — niveau 2',
    'titre_court' => 'Niveau 2',
    'accroche'    => 'Les exercices de la journée, seul ou en équipe',
    'quiz_bonus'  => null,
    // Étapes de l'accueil, ouvertes une à une depuis la télécommande
    // (clé utilisée dans accueil.php => titre affiché), dans l'ordre de la journée.
    // La télécommande les crée en base d'elle-même.
    'etapes'      => [
        'objectifs' => 'Mon objectif du jour',
        'boule'     => 'Le prompt boule de neige',
    ],
    // Équipes de la séance : clé stable (stockée en base) => nom affiché et couleur.
    // Renommer une équipe ne casse rien ; changer sa clé détache ses membres.
    'equipes'     => [
        'glacier' => ['nom' => 'Glacier', 'couleur' => '#47B4E8'],
        'indigo'  => ['nom' => 'Indigo',  'couleur' => '#292F6C'],
        'olive'   => ['nom' => 'Olive',   'couleur' => '#636E24'],
    ],
    'equipe_max'  => 5,
    // Points et classement (scores.php), score affiché sur l'accueil des stagiaires
    'points'      => true,
    // Prompt boule de neige : les situations, attribuées une par équipe dans
    // l'ordre des équipes ci-dessus (modifiable dans le tableau avant le début)
    'boule_taches' => [
        'Annoncer mon pot de départ',
        'Relancer un client pour une facture impayée',
        'Prévenir un client d\'un retard de livraison',
        'Demander une journée de télétravail par semaine',
        'Inviter les voisins à une réunion de copropriété',
        'Répondre à un avis client négatif',
    ],
    // Pages de l'animateur proposées sur la télécommande (page => libellé du bouton)
    'outils'      => [
        'equipes.php' => 'Constituer les équipes',
        'mur.php'     => 'Afficher le mur des objectifs',
        'boule-tableau.php' => 'Tableau du prompt boule de neige',
        'scores.php'  => 'Points et classement',
    ],
];
