<?php
// Questionnaire de satisfaction du niveau 2, repris du questionnaire papier.
// Lu par formation.php (clé 'questionnaire') ; formulaire, tableau de bord et
// export PDF se construisent à partir de cette description.
//
// Types de question :
//   choix : une seule réponse parmi 'options'
//   multi : plusieurs réponses possibles ; 'autre' ajoute « Autres : … » à préciser
//   texte : réponse libre
//   note  : note de 0 à 'max'
// 'obligatoire' => false rend une question facultative (les questions à
// plusieurs réponses le sont toujours).
//
// Les clés (q1…, et celles des options) sont enregistrées en base : en changer
// une détache les réponses déjà reçues. Les libellés, eux, se modifient librement.
return [
    'titre'     => 'Questionnaire de satisfaction – Formation IA niveau 2',
    'formation' => 'IA générative – Structurer, personnaliser et construire ses usages',
    'duree'     => '7 heures – Présentiel',
    'formateur' => '',   // nom du formateur, imprimé sur l'export PDF
    // Texte de la carte « Votre avis » sur l'accueil
    'accroche'  => 'En fin de journée : 5 minutes pour nous dire ce que vous retenez de la journée.',
    'sections'  => [
        ['titre' => 'Votre appréciation générale', 'questions' => [
            'q1' => ['type' => 'choix', 'intitule' => 'Quel est votre niveau de satisfaction global concernant cette journée ?',
                     'options' => ['tres' => 'Très satisfait(e)', 'satisfait' => 'Satisfait(e)', 'plutot' => 'Plutôt satisfait(e)', 'peu' => 'Peu satisfait(e)']],
            'q2' => ['type' => 'choix', 'intitule' => 'La formation a-t-elle répondu à vos attentes ?',
                     'options' => ['tout' => 'Tout à fait', 'grande' => 'En grande partie', 'partiel' => 'Partiellement', 'insuffisant' => 'Pas suffisamment']],
            'q3' => ['type' => 'choix', 'intitule' => 'Comment avez-vous trouvé le déroulement de la journée ?',
                     'options' => ['dynamique' => 'Très bien organisé et dynamique', 'rythme' => 'Agréable et adapté à mon rythme',
                                   'temps' => 'Intéressant, mais certains sujets mériteraient plus de temps', 'ajustements' => 'Quelques ajustements seraient utiles']],
            'q4' => ['type' => 'choix', 'intitule' => 'Comment évaluez-vous l\'accompagnement et les explications du formateur ?',
                     'options' => ['excellent' => 'Excellent', 'tres-bon' => 'Très bon', 'bon' => 'Bon', 'ameliorer' => 'À améliorer']],
        ]],
        ['titre' => 'Les contenus et les compétences développées', 'questions' => [
            'q5' => ['type' => 'multi', 'intitule' => 'Quels thèmes vous ont particulièrement intéressé(e) ?',
                     'options' => ['prompts' => 'Les prompts avancés et réutilisables', 'assistants' => 'La création d\'assistants IA personnalisés',
                                   'documents' => 'L\'utilisation de documents avec l\'IA', 'workflows' => 'Les workflows et l\'organisation des tâches',
                                   'agents' => 'La découverte des automatisations et des agents IA', 'exercices' => 'Les exercices pratiques sur des situations réelles',
                                   'confidentialite' => 'Les bonnes pratiques de confidentialité et de vérification']],
            'q6' => ['type' => 'choix', 'intitule' => 'Après cette journée, comment évaluez-vous votre progression dans l\'utilisation de l\'IA ?',
                     'options' => ['beaucoup' => 'J\'ai beaucoup progressé', 'nouvelles' => 'J\'ai acquis de nouvelles compétences utiles',
                                   'consolide' => 'J\'ai consolidé mes connaissances', 'pratique' => 'J\'ai encore besoin de pratique']],
            'q7' => ['type' => 'choix', 'intitule' => 'Vous sentez-vous désormais plus à l\'aise pour créer des prompts structurés et obtenir des réponses adaptées à vos besoins ?',
                     'options' => ['tout' => 'Oui, tout à fait', 'davantage' => 'Oui, davantage qu\'avant',
                                   'progresse' => 'Je progresse, mais j\'ai besoin de pratiquer', 'approfondir' => 'J\'aimerais approfondir ce sujet']],
            'q8' => ['type' => 'choix', 'intitule' => 'La découverte des assistants IA personnalisés vous a-t-elle donné des idées d\'utilisation ?',
                     'options' => ['plusieurs' => 'Oui, plusieurs applications concrètes', 'une' => 'Oui, au moins une application intéressante',
                                   'experimenter' => 'J\'ai compris le principe et souhaite expérimenter', 'accompagne' => 'J\'aimerais être davantage accompagné(e)']],
            'q9' => ['type' => 'choix', 'intitule' => 'Les exercices et mises en situation vous ont-ils aidé(e) à mieux comprendre les possibilités de l\'IA ?',
                     'options' => ['enormement' => 'Oui, énormément', 'tout' => 'Oui, tout à fait', 'partie' => 'Oui, en partie', 'pratique' => 'J\'aurais apprécié davantage de pratique']],
        ]],
        ['titre' => 'Vos futurs usages de l\'IA', 'questions' => [
            'q10' => ['type' => 'multi', 'intitule' => 'Dans quels domaines pensez-vous réutiliser les compétences acquises ?', 'autre' => true,
                      'options' => ['emploi' => 'Recherche d\'emploi et candidatures', 'entretiens' => 'Préparation aux entretiens',
                                    'productivite' => 'Organisation et productivité personnelle', 'redaction' => 'Rédaction et communication professionnelle',
                                    'metier' => 'Développement de compétences dans mon métier', 'projet' => 'Création ou développement d\'un projet professionnel']],
            'q11' => ['type' => 'choix', 'intitule' => 'À l\'issue de la formation, avez-vous identifié un outil, un assistant ou une méthode que vous pourrez réutiliser ?',
                      'options' => ['rapidement' => 'Oui, et je compte l\'utiliser rapidement', 'idees' => 'Oui, j\'ai plusieurs idées à tester',
                                    'possibilites' => 'J\'ai identifié des possibilités intéressantes', 'accompagne' => 'J\'aimerais encore être accompagné(e) pour les mettre en pratique']],
            'q12' => ['type' => 'texte', 'intitule' => 'Quel serait votre premier projet concret avec l\'IA après cette formation ?', 'obligatoire' => false],
        ]],
        ['titre' => 'Vos attentes pour la suite', 'questions' => [
            'q13' => ['type' => 'multi', 'intitule' => 'Quels sujets aimeriez-vous approfondir lors d\'une prochaine formation ?', 'autre' => true,
                      'options' => ['prompts' => 'Techniques de prompts avancées', 'assistants' => 'Création et personnalisation d\'assistants IA',
                                    'automatisation' => 'Automatisation de tâches répétitives', 'agents' => 'Découverte et utilisation des agents IA',
                                    'metier' => 'Applications de l\'IA à mon métier', 'projet' => 'Utilisation de l\'IA pour développer un projet professionnel']],
            'q14' => ['type' => 'choix', 'intitule' => 'Quel format vous semblerait le plus adapté pour approfondir vos compétences ?',
                      'options' => ['journee' => 'Une nouvelle journée de formation pratique', 'ateliers' => 'Des ateliers courts consacrés à un sujet précis',
                                    'accompagnement' => 'Un accompagnement sur un projet personnel', 'pratiquer' => 'Je souhaite d\'abord mettre en pratique mes acquis']],
        ]],
        ['titre' => 'Votre avis nous intéresse', 'questions' => [
            'q15' => ['type' => 'texte', 'intitule' => 'Qu\'avez-vous le plus apprécié durant cette formation ?'],
            'q16' => ['type' => 'texte', 'intitule' => 'Quelle amélioration ou quel complément souhaiteriez-vous pour les prochaines sessions ?'],
            'q17' => ['type' => 'choix', 'intitule' => 'Recommanderiez-vous cette formation à une personne souhaitant approfondir ses connaissances en IA ?',
                      'options' => ['sans-hesiter' => 'Oui, sans hésitation', 'certainement' => 'Oui, certainement', 'probablement' => 'Probablement', 'pas-maintenant' => 'Pas pour le moment']],
            'q18' => ['type' => 'note', 'intitule' => 'Quelle note globale attribuez-vous à cette formation ?', 'max' => 10],
        ]],
    ],
];
