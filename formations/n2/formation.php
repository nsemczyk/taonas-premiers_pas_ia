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
    ],
    // Équipes de la séance : clé stable (stockée en base) => nom affiché et couleur.
    // Renommer une équipe ne casse rien ; changer sa clé détache ses membres.
    'equipes'     => [
        'glacier' => ['nom' => 'Glacier', 'couleur' => '#47B4E8'],
        'indigo'  => ['nom' => 'Indigo',  'couleur' => '#292F6C'],
        'olive'   => ['nom' => 'Olive',   'couleur' => '#636E24'],
    ],
    'equipe_max'  => 5,
    // Pages de l'animateur proposées sur la télécommande (page => libellé du bouton)
    'outils'      => [
        'equipes.php' => 'Constituer les équipes',
        'mur.php'     => 'Afficher le mur des objectifs',
    ],
];
