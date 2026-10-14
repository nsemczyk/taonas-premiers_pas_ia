<?php
// La fiche de poste de votre assistant : chaque stagiaire décrit, rubrique par
// rubrique, l'assistant IA qu'il veut pour son travail, puis le copie dans son
// outil d'IA pour l'essayer. La fiche reste en base pour la séance : on y
// revient en fin de journée. Exercice individuel, sans points.
//
// Pas de dépôt de fichier : les documents de référence sont seulement listés,
// le stagiaire les joindra lui-même à son IA, anonymisés.
//
// Aucune sortie : ce fichier ne définit que des helpers.
require_once __DIR__ . '/../../core/etapes.php';
require_once __DIR__ . '/../../core/participants.php';

const ASSISTANT_MAX = 2000;   // caractères par rubrique

// Rubriques de la fiche : clé (colonne en base) => titre, aide, exemple, titre dans le prompt copié
const ASSISTANT_RUBRIQUES = [
    'mission'    => ['Mission', 'Ce que l\'assistant fait, en une phrase.',
                     'Ex. : Tu m\'aides à répondre aux demandes de devis des particuliers.', 'Ta mission'],
    'contexte'   => ['Contexte et sources', 'Ce qu\'il sait de vous : votre métier, vos clients, vos habitudes.',
                     'Ex. : Je suis artisan menuisier, je travaille seul, mes clients sont surtout des particuliers.', 'Le contexte'],
    'documents'  => ['Documents de référence', 'Les documents que vous lui joindriez, un par ligne. Pas de dépôt ici : vous les ajouterez vous-même dans votre outil d\'IA, anonymisés.',
                     "Ex. : grille tarifaire 2026\nmodèle de devis (sans nom de client)", 'Documents de référence (joints à part)'],
    'regles'     => ['Règles de fonctionnement', 'Comment il travaille : étapes, ton, longueur.',
                     'Ex. : Ton chaleureux mais professionnel. Réponses de 10 lignes maximum.', 'Comment tu travailles'],
    'interdits'  => ['Ce qu\'il ne fait jamais', 'Inventer un chiffre, un nom, une expérience. Décider à votre place.',
                     'Ex. : Tu n\'inventes jamais un prix : s\'il manque, tu me le demandes.', 'Ce que tu ne fais jamais'],
    'format'     => ['Format des réponses', 'Tableau, liste, mail, script…',
                     'Ex. : Un mail prêt à envoyer, avec un objet.', 'Format de tes réponses'],
    'questions'  => ['Questions à poser', 'Ce qu\'il doit vous demander avant de répondre.',
                     'Ex. : Les dimensions, le bois souhaité, le délai.', 'Avant de répondre, demande-moi'],
];

function assistant_table_ok(): bool
{
    try {
        db()->query('SELECT 1 FROM assistant_fiches LIMIT 1');
        return true;
    } catch (PDOException $e) {
        return false;
    }
}

// Fiche du participant pour la séance (rubriques vides si rien encore)
function assistant_fiche(int $participant_id): array
{
    $st = db()->prepare('SELECT * FROM assistant_fiches WHERE formation = ? AND seance = ? AND participant_id = ?');
    $st->execute([formation_slug(), aujourdhui_local(), $participant_id]);
    $ligne = $st->fetch() ?: [];
    $fiche = [];
    foreach (ASSISTANT_RUBRIQUES as $cle => $r) {
        $fiche[$cle] = (string)($ligne[$cle] ?? '');
    }
    return $fiche;
}

function assistant_vide(array $fiche): bool
{
    return implode('', $fiche) === '';
}

// Enregistre la fiche entière ; une rubrique absente est vidée
function assistant_sauver(int $participant_id, array $saisie): void
{
    $valeurs = [];
    foreach (array_keys(ASSISTANT_RUBRIQUES) as $cle) {
        $v = str_replace("\r\n", "\n", (string)($saisie[$cle] ?? ''));
        $valeurs[$cle] = mb_substr(trim($v), 0, ASSISTANT_MAX);
    }
    $cols = array_keys($valeurs);
    $st = db()->prepare('INSERT INTO assistant_fiches (formation, seance, participant_id, ' . implode(', ', $cols) . ')
                         VALUES (?, ?, ?' . str_repeat(', ?', count($cols)) . ')
                         ON DUPLICATE KEY UPDATE ' . implode(', ', array_map(fn($c) => "$c = VALUES($c)", $cols)));
    $st->execute(array_merge([formation_slug(), aujourdhui_local(), $participant_id], array_values($valeurs)));
}

// La fiche en instructions à coller dans son outil d'IA ; rubriques vides omises
function assistant_prompt(array $fiche): string
{
    $parties = ['Tu es mon assistant. Voici ta fiche de poste.'];
    foreach (ASSISTANT_RUBRIQUES as $cle => [, , , $titre]) {
        $texte = trim($fiche[$cle] ?? '');
        if ($texte === '') {
            continue;
        }
        if ($cle === 'documents') {
            $lignes = array_filter(array_map('trim', explode("\n", $texte)));
            $texte = implode("\n", array_map(fn($l) => '- ' . ltrim($l, '-• '), $lignes));
        }
        $parties[] = '## ' . $titre . "\n" . $texte;
    }
    return implode("\n\n", $parties);
}

// Fiche de poste de l'assistant : le bouton vers assistant.php, derrière l'étape $cle
function bloc_assistant(string $cle = 'assistant'): string
{
    if (!etape_ouverte($cle)) {
        return carte_verrouillee($cle);
    }
    $p = participant_courant();
    $h = '<section class="card">' . "\n"
       . '    <h2>' . e(etape_titre($cle)) . '</h2>' . "\n";
    if (!$p) {
        return $h . '    <p class="lead">Indiquez d\'abord votre prénom ci-dessus.</p>' . "\n" . '  </section>';
    }
    $commencee = assistant_table_ok() && !assistant_vide(assistant_fiche((int)$p['id']));
    return $h . '    <p class="lead">Rédigez la fiche de poste de l\'assistant IA qui vous ferait gagner du temps, puis essayez-le.</p>' . "\n"
              . '    <a class="btn btn-primary" href="' . e(avec_f('assistant.php')) . '">' . ($commencee ? 'Reprendre ma fiche' : 'Écrire la fiche de poste') . '</a>' . "\n"
              . '  </section>';
}
