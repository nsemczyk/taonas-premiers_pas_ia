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
        'battle'    => 'Prompt Battle',
        'bocal'     => 'Le bocal à secrets',
    ],
    // Équipes de la séance : clé stable (stockée en base) => nom affiché et couleur.
    // Renommer une équipe ne casse rien ; changer sa clé détache ses membres.
    'equipes'     => [
        'glacier' => ['nom' => 'Glacier', 'couleur' => '#47B4E8'],
        'indigo'  => ['nom' => 'Indigo',  'couleur' => '#292F6C'],
        'olive'   => ['nom' => 'Olive',   'couleur' => '#636E24'],
    ],
    'equipe_max'  => 5,
    // Prompt Battle : les tâches surprises, tirées au sort une par manche
    // (celles déjà jouées dans la séance restent de côté)
    'battle_taches' => [
        'Expliquer à un enfant de 8 ans pourquoi il faut relire ce que l\'IA écrit.',
        'Rédiger le message d\'accueil d\'une boîte vocale professionnelle qui donne envie de laisser un message.',
        'Convaincre un collègue réticent d\'essayer l\'IA, en 5 lignes, sans le prendre de haut.',
        'Résumer le principe d\'une pause déjeuner obligatoire pour un stagiaire qui saute toujours la sienne.',
        'Écrire l\'annonce interne d\'un changement d\'horaires d\'ouverture, sans que personne ne râle.',
        'Rédiger la description d\'un objet perdu (un parapluie rouge) pour le panneau d\'affichage de l\'entreprise, avec humour.',
    ],
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
    // Le bocal à secrets : le faux mail, chaque donnée à surligner balisée
    // [[texte|catégorie]] (toutes les occurrences comptent), et le corrigé.
    // Personnes et entreprises fictives.
    'bocal' => [
        'consigne'   => 'Ce mail doit être résumé par une IA. Surlignez tout ce qui ne doit pas y aller, puis proposez une version anonymisée qui garde le sens.',
        'texte'      => <<<'MAIL'
De : [[Isabelle Marchetti|noms]] <[[i.marchetti@durand-menuiserie.fr|contact]]>
À : Direction
Objet : Point RH – [[Kevin Boulanger|noms]], [[atelier de Montreuil|contact]]

Bonjour [[Philippe|noms]],

Suite à notre échange de vendredi, voici le point sur la situation de [[Kevin Boulanger|noms]] (né le [[14/03/1989|rh]], embauché en CDI le [[2 septembre 2019|rh]], salaire actuel [[2 340 € brut|rh]]). Il a de nouveau été absent lundi et mardi ; son [[arrêt maladie|sante]] mentionne une [[dépression|sante]], ce qui explique probablement les tensions avec [[Sami Reddad|noms]] ces dernières semaines. Sa situation personnelle est compliquée ([[séparation en cours|privee]], [[garde alternée|privee]]).

Pour rappel, il est le seul à connaître le dossier [[Leclerc-Habitat|affaires]] (contrat de [[186 000 €|affaires]] signé en janvier, livraison prévue le [[30 novembre|affaires]]). Le client, M. [[Franck Leclerc|noms]] ([[06 12 34 56 78|telephone]]), commence à s'impatienter.

Je te propose un entretien jeudi 14h. Ses identifiants sur le planning atelier sont [[kboulanger|acces]] / [[Atelier2019!|acces]] si tu veux consulter ses heures.

[[Merci de ne pas faire suivre ce mail.|indice]]

[[Isabelle|noms]]
MAIL,
        // Catégorie => libellé, type, pourquoi (corrigé affiché au formateur)
        'categories' => [
            'noms'      => ['Noms complets', 'Personnelle', 'Identifiants directs'],
            'contact'   => ['Adresse mail, entreprise, atelier de Montreuil', 'Personnelle / pro', 'Identifiant, localisation'],
            'rh'        => ['Date de naissance, date d\'embauche, salaire', 'Personnelle', 'Identifiant combiné + donnée RH confidentielle'],
            'sante'     => ['Arrêt maladie, dépression', 'Sensible (santé)', 'Catégorie protégée, jamais transmise'],
            'privee'    => ['Séparation, garde alternée', 'Sensible (vie privée)', 'Aucune nécessité pour la tâche'],
            'affaires'  => ['Dossier Leclerc-Habitat, 186 000 €, date de livraison', 'Confidentielle (client)', 'Secret des affaires'],
            'telephone' => ['Numéro de téléphone du client', 'Personnelle', 'Identifiant direct'],
            'acces'     => ['Identifiants et mot de passe', 'Critique', 'Ne doit JAMAIS sortir, IA ou pas'],
            'indice'    => ['« Merci de ne pas faire suivre »', 'Indice', 'Le rédacteur lui-même a marqué la confidentialité'],
        ],
        'reference'  => 'Point RH sur un technicien d\'atelier en CDI depuis environ 5 ans, absent 2 jours cette semaine, avec des tensions récentes avec un collègue et une situation personnelle difficile. Il est le seul à connaître un dossier client important, livraison prévue dans deux mois, et le client s\'impatiente. La RH propose un entretien cette semaine. Résume les enjeux et propose un ordre du jour pour cet entretien.',
    ],
    // Pages de l'animateur proposées sur la télécommande (page => libellé du bouton)
    'outils'      => [
        'equipes.php' => 'Constituer les équipes',
        'mur.php'     => 'Afficher le mur des objectifs',
        'boule-tableau.php' => 'Tableau du prompt boule de neige',
        'battle-tableau.php' => 'Prompt Battle',
        'bocal-tableau.php' => 'Le bocal à secrets : les copies',
        'scores.php'  => 'Points et classement',
    ],
];
