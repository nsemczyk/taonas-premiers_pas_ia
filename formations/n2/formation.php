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
        'assistant' => 'La fiche de poste de votre assistant',
        'speeddating' => 'Speed-dating d\'assistants',
        'bingo'     => 'Bingo de l\'hallucination',
        'tri'       => 'Jeu de tri : qui est quoi ?',
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
            'sante'     => ['Arrêt maladie, dépression', 'Sensible (santé)', 'Catégorie protégée : jamais rattachée à une personne. Anonymisé, le motif d\'absence peut rester, le diagnostic jamais'],
            'privee'    => ['Séparation, garde alternée', 'Sensible (vie privée)', 'Aucune nécessité pour la tâche'],
            'affaires'  => ['Dossier Leclerc-Habitat, 186 000 €, date de livraison', 'Confidentielle (client)', 'Secret des affaires'],
            'telephone' => ['Numéro de téléphone du client', 'Personnelle', 'Identifiant direct'],
            'acces'     => ['Identifiants et mot de passe', 'Critique', 'Ne doit JAMAIS sortir, IA ou pas'],
            'indice'    => ['« Merci de ne pas faire suivre »', 'Indice', 'Le rédacteur lui-même a marqué la confidentialité'],
        ],
        'reference'  => 'Point RH sur un technicien d\'atelier en CDI depuis environ 5 ans, absent deux jours cette semaine (arrêt maladie), avec des tensions récentes avec un collègue et une situation personnelle difficile. Il est le seul à connaître un dossier client important, livraison prévue dans deux mois, et le client s\'impatiente. La RH propose un entretien cette semaine. Résume les enjeux et propose un ordre du jour pour cet entretien.',
    ],
    // Bingo de l'hallucination : les 9 cases (mélangées sur chaque grille), le
    // texte projeté, et le corrigé : [passage(s) du texte surligné(s), erreur, case(s)].
    // Toutes les références du texte sont fausses par construction.
    'bingo' => [
        'cases' => [
            'Une date fausse', 'Une source inventée', 'Une statistique sans origine',
            'Un nom de personne inventé', 'Un « certainement » sans preuve', 'Une citation attribuée au hasard',
            'Un chiffre trop précis', 'Une loi ou norme qui n\'existe pas', 'Une contradiction interne',
        ],
        'texte' => <<<'TEXTE'
Le télétravail en France : ce qu'il faut savoir

Le télétravail a été introduit en France par la loi Delcourt du 12 mars 2004, qui imposait déjà deux jours de présence minimum au bureau. Selon une étude de l'Institut National du Travail à Distance, 73,4 % des salariés français télétravaillent aujourd'hui au moins trois jours par semaine. Comme l'a déclaré le sociologue Jean-Marc Lévrier, « le bureau est devenu un lieu de passage, pas un lieu de travail ». Il est certainement établi que le télétravail augmente la productivité de 27,8 % en moyenne. La norme AFNOR NF-T 2021 fixe d'ailleurs les conditions d'ergonomie du poste à domicile. Notons enfin que la majorité des salariés préfèrent revenir au bureau à temps plein, ce qui explique le succès durable du télétravail.
TEXTE,
        'corrige' => [
            ['loi Delcourt du 12 mars 2004', '« Loi Delcourt du 12 mars 2004 » : loi et date inventées', 'Loi ou norme qui n\'existe pas · Date fausse'],
            ['Institut National du Travail à Distance', '« Institut National du Travail à Distance » : organisme inventé', 'Source inventée'],
            [['73,4 %', '27,8 %'], '« 73,4 % » et « 27,8 % » : chiffres précis sans origine vérifiable', 'Statistique sans origine · Chiffre trop précis'],
            [['Jean-Marc Lévrier', '« le bureau est devenu un lieu de passage, pas un lieu de travail »'], '« Jean-Marc Lévrier » : personne inventée, citation attribuée', 'Nom inventé · Citation attribuée au hasard'],
            ['Il est certainement établi que', '« Il est certainement établi que » : affirmation sans preuve', 'Un « certainement » sans preuve'],
            ['La norme AFNOR NF-T 2021', '« Norme AFNOR NF-T 2021 » : référence inventée', 'Loi ou norme qui n\'existe pas'],
            ['la majorité des salariés préfèrent revenir au bureau à temps plein, ce qui explique le succès durable du télétravail', '« La majorité préfère revenir… ce qui explique le succès du télétravail » : contradiction', 'Contradiction interne'],
        ],
        'avertissement' => 'Toutes les références de ce texte sont fausses par construction. Ne laissez personne repartir en croyant qu\'une de ces sources existe.',
    ],
    // Jeu de tri : les catégories (clé stable => libellé) et les cartes, avec la
    // ou les réponses acceptées ; 'debat' est montré à la correction.
    'tri' => [
        'categories' => [
            'prompt'         => 'Prompt',
            'assistant'      => 'Assistant',
            'workflow'       => 'Workflow',
            'automatisation' => 'Automatisation',
            'agent'          => 'Agent',
        ],
        'cartes' => [
            ['texte' => 'Je demande à l\'IA de corriger l\'orthographe de mon mail.', 'reponses' => ['prompt']],
            ['texte' => 'J\'ai configuré un espace où l\'IA connaît mon métier et mes règles, et je l\'ouvre chaque fois que je prépare un rendez-vous.', 'reponses' => ['assistant']],
            ['texte' => 'Après chaque réunion, je colle mes notes, je fais faire la synthèse, puis la liste des actions, puis le mail de suivi, que je relis avant envoi.', 'reponses' => ['workflow']],
            ['texte' => 'Chaque lundi à 8h, un résumé des mails de la semaine passée arrive dans ma boîte sans que je fasse rien.', 'reponses' => ['automatisation']],
            ['texte' => 'Je confie à l\'IA l\'objectif de me trouver 5 fournisseurs de mobilier de bureau ; elle cherche, compare, et me propose une sélection.', 'reponses' => ['agent']],
            ['texte' => '« Réécris ce paragraphe dans un ton plus formel. »', 'reponses' => ['prompt']],
            ['texte' => 'Dès qu\'un formulaire de contact est rempli sur le site, une fiche est créée dans le tableau de suivi.', 'reponses' => ['automatisation']],
            ['texte' => 'J\'ai un GPT / Gem / projet « Relecteur de CV » avec la consigne de ne jamais inventer d\'expérience.', 'reponses' => ['assistant']],
            ['texte' => 'Pour chaque offre d\'emploi : analyse, comparaison avec mon CV, écarts, arguments, questions d\'entretien. Toujours dans cet ordre.', 'reponses' => ['workflow']],
            ['texte' => 'Je donne à un système l\'objectif « organise mon déplacement à Lyon » et il enchaîne recherche de train, d\'hôtel et proposition d\'agenda.', 'reponses' => ['agent']],
            ['texte' => 'Quand une facture arrive par mail, elle est résumée par l\'IA et une ligne s\'ajoute dans mon tableau ; je valide chaque ligne le vendredi.', 'reponses' => ['automatisation', 'workflow'],
             'debat' => 'Automatisation (avec contrôle humain), mais c\'est aussi un workflow automatisé.'],
            ['texte' => '« Tu es un recruteur exigeant. Pose-moi 5 questions sur ce poste. »', 'reponses' => ['prompt', 'assistant'],
             'debat' => 'Prompt (avec rôle) : ça ressemble à une mini-fiche de poste, mais c\'est ponctuel.'],
        ],
    ],
    // Pages de l'animateur proposées sur la télécommande (page => libellé du bouton)
    'outils'      => [
        'equipes.php' => 'Constituer les équipes',
        'mur.php'     => 'Afficher le mur des objectifs',
        'boule-tableau.php' => 'Tableau du prompt boule de neige',
        'battle-tableau.php' => 'Prompt Battle',
        'bocal-tableau.php' => 'Le bocal à secrets : les copies',
        'bingo-tableau.php' => 'Bingo de l\'hallucination',
        'tri-tableau.php' => 'Jeu de tri',
        'scores.php'  => 'Points et classement',
    ],
];
