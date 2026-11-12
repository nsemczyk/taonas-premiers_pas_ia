<?php
// Un prompt pour dans un mois : chaque stagiaire choisit une tâche récurrente
// (liée si possible à son objectif du jour), écrit un prompt en 7 blocs avec
// ses variables entre [crochets], le teste, l'analyse (ce qui est bon, ce qui
// manque), en écrit une version 2, puis le copie dans sa bibliothèque de
// prompts. Individuel, sans points. Enregistré au fil de la frappe.
//
// Aucune sortie : ce fichier ne définit que des helpers.
require_once __DIR__ . '/../../core/etapes.php';
require_once __DIR__ . '/../../core/participants.php';
require_once __DIR__ . '/../mur/fonctions.php';   // objectif du jour

const PROMPT_MOIS_MAX = 2000;   // caractères par champ

// Les 7 blocs : clé => titre, aide, exemple
const PROMPT_MOIS_BLOCS = [
    'objectif'     => ['Objectif', 'Ce que je veux obtenir, concrètement.', 'Ex. : un compte rendu de réunion prêt à envoyer'],
    'contexte'     => ['Contexte', 'Qui je suis, dans quelle situation.', 'Ex. : je suis assistante de direction dans une PME de [effectif] personnes'],
    'public'       => ['Public / destinataire', 'À qui s\'adresse le résultat.', 'Ex. : les participants de la réunion [nom de la réunion]'],
    'informations' => ['Informations disponibles', 'Ce que je fournis : faits, documents, chiffres.', 'Ex. : mes notes brutes ci-dessous : [notes]'],
    'contraintes'  => ['Contraintes', 'Longueur, ton, interdits, délais.', 'Ex. : une page maximum, ton neutre, ne rien inventer'],
    'format'       => ['Format attendu', 'E-mail, tableau, liste, plan, script…', 'Ex. : trois parties : décisions, actions (qui, quoi, quand), points ouverts'],
    'criteres'     => ['Critères de réussite', 'À quoi je reconnais un bon résultat.', 'Ex. : chaque action a un responsable et une date'],
];

function prompt_mois_table_ok(): bool
{
    try {
        db()->query('SELECT 1 FROM prompt_mois_fiches LIMIT 1');
        return true;
    } catch (PDOException $e) {
        return false;
    }
}

// Fiche vide : la tâche, les 7 blocs de la version 1, l'analyse, les 7 blocs de la version 2
function prompt_mois_vide(): array
{
    $blocs = array_fill_keys(array_keys(PROMPT_MOIS_BLOCS), '');
    return ['tache' => '', 'v1' => $blocs, 'bon' => '', 'manque' => '', 'v2' => $blocs];
}

// Fiche reçue du navigateur, remise en forme (champs connus, longueurs bornées)
function prompt_mois_nettoyer(array $in): array
{
    $propre = fn($v) => mb_substr(trim(str_replace("\r\n", "\n", (string)$v)), 0, PROMPT_MOIS_MAX);
    $fiche = prompt_mois_vide();
    foreach (['tache', 'bon', 'manque'] as $champ) {
        $fiche[$champ] = $propre($in[$champ] ?? '');
    }
    foreach (['v1', 'v2'] as $v) {
        foreach (array_keys(PROMPT_MOIS_BLOCS) as $bloc) {
            $fiche[$v][$bloc] = $propre($in[$v][$bloc] ?? '');
        }
    }
    return $fiche;
}

function prompt_mois_fiche(int $participant_id): array
{
    $st = db()->prepare('SELECT donnees FROM prompt_mois_fiches WHERE formation = ? AND seance = ? AND participant_id = ?');
    $st->execute([formation_slug(), aujourdhui_local(), $participant_id]);
    $d = $st->fetchColumn();
    return $d ? prompt_mois_nettoyer(json_decode($d, true) ?: []) : prompt_mois_vide();
}

function prompt_mois_sauver(int $participant_id, array $in): void
{
    $st = db()->prepare('INSERT INTO prompt_mois_fiches (formation, seance, participant_id, donnees) VALUES (?, ?, ?, ?)
                         ON DUPLICATE KEY UPDATE donnees = VALUES(donnees)');
    $st->execute([formation_slug(), aujourdhui_local(), $participant_id,
                  json_encode(prompt_mois_nettoyer($in), JSON_UNESCAPED_UNICODE)]);
}

// Un prompt pour dans un mois : le bouton vers prompt-mois.php, derrière l'étape $cle
function bloc_prompt_mois(string $cle = 'promptmois'): string
{
    if (!etape_ouverte($cle)) {
        return carte_verrouillee($cle);
    }
    $h = '<section class="card">' . "\n"
       . '    <h2>' . e(etape_titre($cle)) . '</h2>' . "\n";
    if (!participant_courant()) {
        return $h . '    <p class="lead">Indiquez d\'abord votre prénom ci-dessus.</p>' . "\n" . '  </section>';
    }
    return $h . '    <p class="lead">Un prompt réutilisable pour une tâche qui revient souvent : écrit en 7 blocs, testé, amélioré, rangé dans votre bibliothèque.</p>' . "\n"
              . '    <a class="btn btn-primary" href="' . e(avec_f('prompt-mois.php')) . '">Écrire mon prompt</a>' . "\n"
              . '  </section>';
}
