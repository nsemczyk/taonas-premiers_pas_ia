<?php
// Niveau 1 — Premiers pas avec l'IA générative.
// Réglages lus par core/formation.php ; le contenu de l'accueil est dans accueil.php.
return [
    'titre'       => 'Premiers pas avec l\'IA générative',
    'titre_court' => 'Niveau 1',
    'accroche'    => 'Les documents et les quiz de la journée, à portée de pouce',
    // Quiz proposé en fin de quiz principal (slug), null pour aucun
    'quiz_bonus'  => 'quiz-bonus',
    // Étapes de l'accueil, ouvertes une à une depuis la télécommande
    // (clé utilisée dans accueil.php => titre affiché), dans l'ordre de la journée
    'etapes'      => [
        'cv'       => 'Le CV de Sam',
        'offre'    => 'L\'offre d\'emploi',
        'missions' => 'Défi chrono — les 3 missions',
        'quiz'     => 'Les quiz',
        'avis'     => 'Votre avis',
    ],
];
