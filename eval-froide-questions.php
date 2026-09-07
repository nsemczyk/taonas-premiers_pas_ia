<?php
// Définitions du questionnaire d'évaluation à froid.
// Aucune sortie : uniquement des tableaux, partagés par le formulaire
// (evaluation-froide.php) et la lecture des réponses.
//
// Chaque question à choix (simple ou multiple) associe un CODE stable (stocké
// en base) à un LIBELLÉ (affiché). On ne stocke jamais le libellé : renommer un
// intitulé ne casse pas les réponses déjà enregistrées.

// --- Questions à choix multiple : code => libellé ---------------------------
// La clé de premier niveau est le nom du champ (et la colonne SQL correspondante).

$EF_MULTI = [
    'q2_usages' => [
        'offres'        => "Rechercher ou analyser des offres d'emploi",
        'cv'            => "Améliorer ou adapter mon CV",
        'lettre'        => "Rédiger une lettre de motivation",
        'candidature'   => "Préparer une candidature ou répondre à une offre",
        'entretien'     => "Préparer un entretien d'embauche",
        'mail'          => "Rédiger un mail ou un message professionnel",
        'metier'        => "Me renseigner sur un métier ou une entreprise",
        'apprendre'     => "Apprendre ou me former sur un sujet",
        'demarches'     => "Effectuer des démarches administratives ou personnelles",
        'non_reutilise' => "Je ne l'ai pas réutilisée",
    ],
    'q5_usages' => [
        'cv'        => "Adapter ou améliorer mon CV",
        'lettre'    => "Rédiger ou améliorer une lettre de motivation",
        'offre'     => "Répondre à une offre d'emploi",
        'mail'      => "Rédiger un mail ou un message à un recruteur",
        'entretien' => "Préparer un entretien",
    ],
    'q11_freins' => [
        'rien'          => "Rien, je l'utilise comme je le souhaite",
        'pratique'      => "Je manque encore de pratique",
        'quoi_demander' => "Je ne sais pas toujours quoi demander à l'IA",
        'peur_erreurs'  => "J'ai peur de faire des erreurs",
        'confiance'     => "Je manque de confiance dans les réponses de l'IA",
        'materiel'      => "J'ai des difficultés avec l'ordinateur ou le téléphone",
        'pas_besoin'    => "Je n'en ai pas vraiment besoin actuellement",
    ],
    'q13_sujets' => [
        'emploi_avance' => "IA et recherche d'emploi – niveau avancé",
        'entretiens'    => "Préparer ses entretiens avec l'IA",
        'cv_avance'     => "CV et candidatures avancées",
        'outils'        => "Mieux maîtriser ChatGPT et les autres outils d'IA",
        'metier'        => "Utiliser l'IA dans mon futur métier",
        'images'        => "Création d'images et de contenus avec l'IA",
        'agents'        => "Assistants et agents IA",
    ],
];

// --- Questions à choix unique : code => libellé -----------------------------

$EF_SIMPLE = [
    'q3_autonomie' => [
        'oui_facile' => "Oui, facilement",
        'oui_aide'   => "Oui, mais j'ai encore parfois besoin d'aide",
        'difficile'  => "Difficilement",
        'non'        => "Non",
    ],
    'q4_prompt' => [
        'bcp_plus'     => "Oui, beaucoup plus",
        'un_peu_plus'  => "Oui, un peu plus",
        'pas_vraiment' => "Pas vraiment",
        'non'          => "Non",
    ],
    'q5_candidature' => [
        'plusieurs' => "Oui, plusieurs fois",
        'une'       => "Oui, une fois",
        'non'       => "Non",
    ],
    'q6_changement' => [
        'bcp'         => "Oui, beaucoup",
        'un_peu'      => "Oui, un peu",
        'pas_vraiment' => "Pas vraiment",
        'pas_du_tout' => "Pas du tout",
    ],
    'q7_entretiens' => [
        'oui'        => "Oui",
        'non'        => "Non",
        'pas_envoye' => "Je n'ai pas encore envoyé de candidature",
    ],
    'q7_ia_prepa' => [
        'oui' => "Oui",
        'non' => "Non",
    ],
    'q8_utile' => [
        'bcp'         => "Beaucoup",
        'assez'       => "Assez",
        'un_peu'      => "Un peu",
        'pas_du_tout' => "Pas du tout",
    ],
    'q9_autonomie' => [
        'oui_clair'   => "Oui, clairement",
        'oui_un_peu'  => "Oui, un peu",
        'pas_vraiment' => "Pas vraiment",
        'pas_du_tout' => "Pas du tout",
    ],
    'q13_poursuivre' => [
        'oui'       => "Oui",
        'peut_etre' => "Peut-être",
        'non'       => "Non",
    ],
    'q14_situation' => [
        'recherche'     => "Je suis toujours en recherche d'emploi",
        'entretiens'    => "J'ai obtenu un ou plusieurs entretiens",
        'emploi'        => "J'ai commencé un emploi",
        'futur_emploi'  => "Je vais prochainement commencer un emploi",
        'formation'     => "Je suis entré(e) en formation",
        'activite'      => "J'ai créé ou je prépare une activité professionnelle",
        'ne_repond_pas' => "Je préfère ne pas répondre",
    ],
];

// Valide un choix unique : rend le code s'il est autorisé, '' sinon.
function ef_simple_clean(array $options, ?string $v): string
{
    $v = (string)$v;
    return array_key_exists($v, $options) ? $v : '';
}

// Valide un choix multiple : filtre les codes autorisés, rend "a,b,c" ('' si vide).
// Préserve l'ordre de définition des options pour un rendu stable.
function ef_multi_clean(array $options, $values): string
{
    if (!is_array($values)) {
        return '';
    }
    $recus = array_flip($values);
    $garde = [];
    foreach (array_keys($options) as $code) {
        if (isset($recus[$code])) {
            $garde[] = $code;
        }
    }
    return implode(',', $garde);
}

// Coupe et borne un champ texte libre.
function ef_texte_clean(?string $v, int $max): string
{
    $v = trim((string)$v);
    return mb_substr($v, 0, $max);
}

// --- Disposition du questionnaire, dans l'ordre du formulaire ----------------
// Partagée par la consultation (eval-froide-resultats.php) et l'export PDF
// (export-eval-froide.php). Chaque entrée décrit une question :
//   num    : numéro affiché
//   q      : intitulé
//   type   : 'simple' (radio) | 'multi' (cases) | 'texte' (zone libre)
//   field  : colonne SQL
//   opt    : clé dans $EF_SIMPLE / $EF_MULTI (types simple/multi)
//   autre  : colonne du « Autre : … » libre (facultatif)
//   sub    : sous-questions conditionnelles (même forme)
$EF_QUESTIONS = [
    ['num' => '2',  'q' => "Depuis la formation, pour quoi avez-vous utilisé l'IA ?",
        'type' => 'multi', 'field' => 'q2_usages', 'opt' => 'q2_usages', 'autre' => 'q2_autre'],
    ['num' => '3',  'q' => "Aujourd'hui, vous sentez-vous capable d'utiliser seul(e) une IA générative ?",
        'type' => 'simple', 'field' => 'q3_autonomie', 'opt' => 'q3_autonomie'],
    ['num' => '4',  'q' => "Vous sentez-vous plus à l'aise pour rédiger un prompt efficace ?",
        'type' => 'simple', 'field' => 'q4_prompt', 'opt' => 'q4_prompt'],
    ['num' => '5',  'q' => "Depuis la formation, avez-vous utilisé l'IA pour une candidature réelle ?",
        'type' => 'simple', 'field' => 'q5_candidature', 'opt' => 'q5_candidature',
        'sub' => [
            ['q' => "Si oui, pour quoi ?", 'type' => 'multi', 'field' => 'q5_usages', 'opt' => 'q5_usages', 'autre' => 'q5_autre'],
        ]],
    ['num' => '6',  'q' => "Avez-vous changé votre manière de rechercher un emploi ?",
        'type' => 'simple', 'field' => 'q6_changement', 'opt' => 'q6_changement'],
    ['num' => '7',  'q' => "Depuis la formation, avez-vous obtenu un ou plusieurs entretiens d'embauche ?",
        'type' => 'simple', 'field' => 'q7_entretiens', 'opt' => 'q7_entretiens',
        'sub' => [
            ['q' => "Si oui, avez-vous utilisé l'IA pour en préparer au moins un ?", 'type' => 'simple', 'field' => 'q7_ia_prepa', 'opt' => 'q7_ia_prepa'],
        ]],
    ['num' => '8',  'q' => "Avec le recul, cette formation vous est-elle utile dans votre recherche d'emploi ?",
        'type' => 'simple', 'field' => 'q8_utile', 'opt' => 'q8_utile'],
    ['num' => '9',  'q' => "L'utilisation de l'IA vous permet-elle davantage d'autonomie dans vos démarches ?",
        'type' => 'simple', 'field' => 'q9_autonomie', 'opt' => 'q9_autonomie'],
    ['num' => '10', 'q' => "Quelle chose apprise pendant la formation utilisez-vous le plus aujourd'hui ?",
        'type' => 'texte', 'field' => 'q10_apprise'],
    ['num' => '11', 'q' => "Qu'est-ce qui vous empêche encore d'utiliser davantage l'IA aujourd'hui ?",
        'type' => 'multi', 'field' => 'q11_freins', 'opt' => 'q11_freins', 'autre' => 'q11_autre'],
    ['num' => '12', 'q' => "Qu'auriez-vous aimé apprendre ou pratiquer davantage pendant la formation ?",
        'type' => 'texte', 'field' => 'q12_manque'],
    ['num' => '13', 'q' => "Souhaiteriez-vous poursuivre votre apprentissage de l'IA ?",
        'type' => 'simple', 'field' => 'q13_poursuivre', 'opt' => 'q13_poursuivre',
        'sub' => [
            ['q' => "Si oui ou peut-être, quels sujets vous intéresseraient ?", 'type' => 'multi', 'field' => 'q13_sujets', 'opt' => 'q13_sujets', 'autre' => 'q13_autre'],
        ]],
    ['num' => '14', 'q' => "Depuis la formation, votre situation professionnelle a-t-elle évolué ?",
        'type' => 'simple', 'field' => 'q14_situation', 'opt' => 'q14_situation', 'autre' => 'q14_autre'],
];

// Libellé d'un code de choix unique ('' si vide ; le code brut si inconnu,
// pour ne jamais perdre une réponse d'un ancien enregistrement).
function ef_libelle_simple(array $options, ?string $code): string
{
    $code = trim((string)$code);
    if ($code === '') {
        return '';
    }
    return $options[$code] ?? $code;
}

// Liste de libellés d'un choix multiple stocké en "a,b,c".
function ef_libelles_multi(array $options, ?string $csv): array
{
    $csv = trim((string)$csv);
    if ($csv === '') {
        return [];
    }
    $out = [];
    foreach (explode(',', $csv) as $code) {
        $out[] = $options[$code] ?? $code;
    }
    return $out;
}
