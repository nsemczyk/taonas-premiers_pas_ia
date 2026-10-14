<?php
// La fiche de poste de votre assistant : chaque stagiaire décrit, rubrique par
// rubrique, l'assistant IA qu'il veut pour son travail, puis le copie dans son
// outil d'IA pour l'essayer. La fiche reste en base pour la séance : on y
// revient en fin de journée. Exercice individuel, sans points.
//
// Pas de dépôt de fichier : les documents de référence sont seulement listés,
// le stagiaire les joindra lui-même à son IA, anonymisés.
//
// Speed-dating d'assistants : quand le formateur ouvre l'étape ASSISTANT_ETAPE_SPEED,
// chacun teste l'assistant d'un binôme qu'il choisit (en changeant de poste)
// et le note sur la grille de la page ; le propriétaire voit les retours sous sa fiche.
//
// Aucune sortie : ce fichier ne définit que des helpers.
require_once __DIR__ . '/../../core/etapes.php';
require_once __DIR__ . '/../../core/participants.php';

const ASSISTANT_MAX = 2000;   // caractères par rubrique
const ASSISTANT_ETAPE_SPEED = 'speeddating';
const ASSISTANT_MAX_REMARQUE = 500;

// Grille du speed-dating : colonne => question, et réponse qui signale un problème
const ASSISTANT_GRILLE = [
    'compris'   => ['A-t-il compris la mission ?', false],
    'invente'   => ['A-t-il inventé quelque chose ?', true],
    'regles'    => ['A-t-il respecté les règles ?', false],
    'questions' => ['A-t-il posé des questions ?', false],
];

// Le test des 3 questions, avant de coller une information dans une IA
const ASSISTANT_TROIS_QUESTIONS = [
    ['Est-ce nécessaire ?', 'L\'IA a-t-elle vraiment besoin de cette information pour faire le travail ? Sinon, on retire.'],
    ['Est-ce identifiant ?', 'Permet-elle, seule ou combinée, de reconnaître une personne ? Si oui, on anonymise.'],
    ['Est-ce confidentiel ?', 'Est-ce que mon employeur, mon client, mon proche accepterait de la voir ailleurs ? Si non, on n\'envoie pas.'],
];

// Rubriques de la fiche : clé (colonne en base) => titre, aide, exemple, titre dans le prompt copié
const ASSISTANT_RUBRIQUES = [
    'mission'    => ['Mission', 'Ce que l\'assistant fait, en une phrase. UNE seule mission : assistant de candidature, de synthèse, de préparation de rendez-vous, de procédure métier…',
                     'Ex. : Tu m\'aides à répondre aux demandes de devis des particuliers.', 'Ta mission'],
    'contexte'   => ['Contexte et sources', 'Ce qu\'il sait de vous : votre métier, vos clients, vos habitudes. Chaque information passe le test des 3 questions.',
                     'Ex. : Je suis artisan menuisier, je travaille seul, mes clients sont surtout des particuliers.', 'Le contexte'],
    'documents'  => ['Documents de référence', 'Les documents que vous lui joindriez, un par ligne. Pas de dépôt ici : vous les ajouterez vous-même dans votre outil d\'IA, après le test des 3 questions.',
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

function assistant_speed_ouvert(): bool
{
    return etape_ouverte(ASSISTANT_ETAPE_SPEED);
}

// Le retour d'un testeur sur l'assistant d'un binôme, enregistré ou remplacé
function assistant_evaluer(int $testeur_id, int $cible_id, array $reponses, string $remarque): bool
{
    $ids = array_map('intval', array_column(participants_seance(), 'id'));
    if ($testeur_id === $cible_id || !in_array($cible_id, $ids, true)) {
        return false;
    }
    $cols = array_keys(ASSISTANT_GRILLE);
    $st = db()->prepare('INSERT INTO assistant_retours (formation, seance, testeur_id, cible_id, ' . implode(', ', $cols) . ', remarque)
                         VALUES (?, ?, ?, ?' . str_repeat(', ?', count($cols)) . ', ?)
                         ON DUPLICATE KEY UPDATE ' . implode(', ', array_map(fn($c) => "$c = VALUES($c)", $cols)) . ', remarque = VALUES(remarque)');
    $st->execute(array_merge([formation_slug(), aujourdhui_local(), $testeur_id, $cible_id],
                             array_map(fn($c) => empty($reponses[$c]) ? 0 : 1, $cols),
                             [mb_substr(trim($remarque), 0, ASSISTANT_MAX_REMARQUE)]));
    return true;
}

// Retours de la séance : reçus par un participant (sur son assistant) ou donnés par lui
function assistant_retours(int $participant_id, bool $recus = true): array
{
    $moi   = $recus ? 'cible_id' : 'testeur_id';
    $autre = $recus ? 'testeur_id' : 'cible_id';
    $st = db()->prepare("SELECT r.*, p.prenom, p.equipe FROM assistant_retours r
                         JOIN participants p ON p.id = r.$autre
                         WHERE r.formation = ? AND r.seance = ? AND r.$moi = ? ORDER BY r.updated_at, r.id");
    $st->execute([formation_slug(), aujourdhui_local(), $participant_id]);
    return $st->fetchAll();
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
    if (assistant_speed_ouvert()) {
        return $h . '    <p class="lead"><strong>Speed-dating d\'assistants :</strong> changez de poste avec votre binôme, testez SON assistant avec une vraie demande, puis notez-le.</p>' . "\n"
                  . '    <a class="btn btn-primary" href="' . e(avec_f('assistant.php#speed-dating')) . '">Noter l\'assistant de mon binôme</a>' . "\n"
                  . '  </section>';
    }
    return $h . '    <p class="lead">Rédigez la fiche de poste de l\'assistant IA qui vous ferait gagner du temps, puis essayez-le.</p>' . "\n"
              . '    <a class="btn btn-primary" href="' . e(avec_f('assistant.php')) . '">' . ($commencee ? 'Reprendre ma fiche' : 'Écrire la fiche de poste') . '</a>' . "\n"
              . '  </section>';
}
