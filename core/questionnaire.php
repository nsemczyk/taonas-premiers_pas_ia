<?php
// Questionnaire de satisfaction décrit dans formations/<slug>/formation.php
// (clé 'questionnaire', voir formations/n2/questionnaire.php). Une formation
// sans cette clé garde le questionnaire historique du niveau 1, en dur dans
// satisfaction.php, resultats.php et export-satisfaction.php.
//
// Réponses dans la table `satisfaction_reponses` : une ligne par avis, les
// réponses en JSON (clé de question => clé d'option, liste de clés, texte ou note).
// Questionnaire nominatif : l'avis porte le participant de la séance (prénom
// saisi à l'arrivée), un seul avis par participant et par journée.
//
// Aucune sortie : ce fichier ne définit que des helpers.
require_once __DIR__ . '/horodatage.php';
require_once __DIR__ . '/participants.php';

const QUESTIONNAIRE_MAX_TEXTE = 2000;
const QUESTIONNAIRE_MAX_AUTRE = 300;

function questionnaire(): ?array
{
    return formation()['questionnaire'] ?? null;
}

function questionnaire_table_ok(): bool
{
    try {
        db()->query('SELECT 1 FROM satisfaction_reponses LIMIT 1');
        return true;
    } catch (PDOException $e) {
        return false;
    }
}

// Toutes les questions, à plat : clé => définition
function questionnaire_questions(): array
{
    $out = [];
    foreach (questionnaire()['sections'] as $s) {
        $out += $s['questions'];
    }
    return $out;
}

function question_obligatoire(array $q): bool
{
    return $q['type'] !== 'multi' && ($q['obligatoire'] ?? true);
}

function questionnaire_texte(string $s, int $max): string
{
    return mb_substr(trim(str_replace("\r\n", "\n", $s)), 0, $max);
}

// Lit le formulaire : [réponses nettoyées, message d'erreur ou '']
function questionnaire_lire(array $post): array
{
    $reponses = [];
    $manque = null;
    foreach (questionnaire_questions() as $cle => $q) {
        $v = $post[$cle] ?? null;
        switch ($q['type']) {
            case 'choix':
                $v = is_string($v) && isset($q['options'][$v]) ? $v : null;
                break;
            case 'multi':
                $v = array_values(array_intersect(array_keys($q['options']), (array)$v));
                if (!empty($q['autre'])) {
                    $autre = questionnaire_texte((string)($post[$cle . '_autre'] ?? ''), QUESTIONNAIRE_MAX_AUTRE);
                    if ($autre !== '') {
                        $reponses[$cle . '_autre'] = $autre;
                    }
                }
                break;
            case 'texte':
                $v = questionnaire_texte((string)$v, QUESTIONNAIRE_MAX_TEXTE);
                $v = $v === '' ? null : $v;
                break;
            case 'note':
                $v = is_string($v) && ctype_digit($v) && (int)$v <= $q['max'] ? (int)$v : null;
                break;
        }
        if ($v === null || $v === []) {
            if (question_obligatoire($q) && $manque === null) {
                $manque = $q['intitule'];
            }
            continue;
        }
        $reponses[$cle] = $v;
    }
    return [$reponses, $manque === null ? '' : 'Il manque une réponse : « ' . $manque . ' »'];
}

// Avis d'un participant pour la journée : le prénom est celui de son arrivée,
// conservé dans l'avis même s'il est retiré de la séance ensuite
function questionnaire_enregistrer(array $reponses, array $participant): void
{
    $st = db()->prepare('INSERT INTO satisfaction_reponses (formation, session_code, participant_id, prenom, reponses)
                         VALUES (?, ?, ?, ?, ?)');
    $st->execute([formation_slug(), aujourdhui_local(), (int)$participant['id'], $participant['prenom'],
                  json_encode($reponses, JSON_UNESCAPED_UNICODE)]);
}

// Ce participant a-t-il déjà donné son avis aujourd'hui ?
function questionnaire_deja_rempli(int $participant_id): bool
{
    $st = db()->prepare('SELECT 1 FROM satisfaction_reponses WHERE formation = ? AND session_code = ? AND participant_id = ? LIMIT 1');
    $st->execute([formation_slug(), aujourdhui_local(), $participant_id]);
    return (bool)$st->fetchColumn();
}

// Avis de la formation courante, réponses décodées ; entre deux bornes serveur si données
function questionnaire_avis(?string $debut = null, ?string $fin = null): array
{
    $sql = 'SELECT * FROM satisfaction_reponses WHERE formation = ?';
    $args = [formation_slug()];
    if ($debut !== null) {
        $sql .= ' AND created_at >= ? AND created_at < ?';
        array_push($args, $debut, $fin);
    }
    $st = db()->prepare($sql . ' ORDER BY created_at');
    $st->execute($args);
    return array_map(function ($a) {
        $a['reponses'] = json_decode($a['reponses'], true) ?: [];
        return $a;
    }, $st->fetchAll());
}

// Synthèse pour le tableau de bord, dans la forme attendue par resultats.php
// (nb, moyenne_generale, criteres, repartitions, commentaires) : la note
// globale fait office de moyenne, chaque question à choix devient une
// répartition, chaque texte un bloc de verbatims.
function questionnaire_agreger(array $avis): array
{
    $repartitions = [];
    $commentaires = [];
    $note = null;
    foreach (questionnaire_questions() as $cle => $q) {
        $valeurs = array_column(array_column($avis, 'reponses'), $cle);
        switch ($q['type']) {
            case 'choix':
            case 'multi':
                $choisies = [];
                foreach ($valeurs as $v) {
                    foreach ((array)$v as $option) {
                        $choisies[] = (string)$option;
                    }
                }
                $n = array_count_values($choisies);
                $lignes = [];
                foreach ($q['options'] as $k => $libelle) {
                    if (!empty($n[$k])) {
                        $lignes[] = ['libelle' => $libelle, 'n' => $n[$k]];
                    }
                }
                $repartitions[] = ['libelle' => $q['intitule'], 'valeurs' => $lignes];
                if (!empty($q['autre'])) {
                    $autres = array_values(array_filter(array_column(array_column($avis, 'reponses'), $cle . '_autre')));
                    $commentaires[] = ['libelle' => $q['intitule'] . ' (autres)', 'items' => $autres];
                }
                break;
            case 'texte':
                $commentaires[] = ['libelle' => $q['intitule'], 'items' => array_values(array_filter($valeurs, 'strlen'))];
                break;
            case 'note':
                $note ??= ['libelle' => $q['intitule'], 'base' => $q['max'],
                           'valeur' => $valeurs ? round(array_sum($valeurs) / count($valeurs), 1) : null];
                break;
        }
    }
    return [
        'nb'               => count($avis),
        'nb_notes'         => count($avis),
        'moyenne_generale' => $note['valeur'] ?? null,
        'base'             => $note['base'] ?? 10,
        'criteres'         => $note ? [$note] : [],
        'capacites'        => [],
        'repartitions'     => $repartitions,
        'commentaires'     => $commentaires,
    ];
}
