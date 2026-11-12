<?php
// Atelier workflow : chaque stagiaire dessine un processus sur un tableau
// blanc, à la manière de n8n mais sans rien exécuter. Trois formes :
//   - rond     : déclencheur (ce qui lance le processus)
//   - boîte    : étape du processus
//   - losange  : condition, avec deux sorties étiquetées Oui et Non
// reliées par des flèches. Deux déclencheurs ne se relient jamais entre eux.
// Le schéma est enregistré au fil de l'eau ; le formateur les voit tous et
// peut en projeter un.
//
// Aucune sortie : ce fichier ne définit que des helpers.
require_once __DIR__ . '/../../core/etapes.php';
require_once __DIR__ . '/../../core/participants.php';

const WORKFLOW_TYPES      = ['declencheur', 'etape', 'condition'];
const WORKFLOW_LARGEUR    = 1600;   // repère du tableau (viewBox SVG)
const WORKFLOW_HAUTEUR    = 1000;
const WORKFLOW_MAX_NOEUDS = 80;
const WORKFLOW_MAX_LIENS  = 160;
const WORKFLOW_MAX_TEXTE  = 120;
const WORKFLOW_MAX_TITRE  = 100;

function workflow_table_ok(): bool
{
    try {
        db()->query('SELECT 1 FROM workflow_schemas LIMIT 1');
        return true;
    } catch (PDOException $e) {
        return false;
    }
}

// Schéma reçu du navigateur, remis en ordre : formes valides, liens permis
// (pas de boucle sur soi, pas de doublon, pas de déclencheur vers déclencheur,
// deux sorties au plus par condition, étiquetées oui puis non)
function workflow_nettoyer(array $in): array
{
    $noeuds = [];
    foreach (array_slice((array)($in['noeuds'] ?? []), 0, WORKFLOW_MAX_NOEUDS) as $n) {
        $id = (string)($n['id'] ?? '');
        if (!preg_match('/^n\d{1,6}$/', $id) || isset($noeuds[$id]) || !in_array($n['type'] ?? '', WORKFLOW_TYPES, true)) {
            continue;
        }
        $noeuds[$id] = [
            'id'    => $id,
            'type'  => $n['type'],
            'x'     => round(max(0, min(WORKFLOW_LARGEUR, (float)($n['x'] ?? 0))), 1),
            'y'     => round(max(0, min(WORKFLOW_HAUTEUR, (float)($n['y'] ?? 0))), 1),
            'texte' => mb_substr(trim((string)($n['texte'] ?? '')), 0, WORKFLOW_MAX_TEXTE),
        ];
    }

    $liens = [];
    $vus = [];
    $sorties = [];   // condition => étiquettes déjà prises
    foreach (array_slice((array)($in['liens'] ?? []), 0, WORKFLOW_MAX_LIENS) as $l) {
        $de = (string)($l['de'] ?? '');
        $vers = (string)($l['vers'] ?? '');
        if (!isset($noeuds[$de], $noeuds[$vers]) || $de === $vers || isset($vus["$de>$vers"])
            || ($noeuds[$de]['type'] === 'declencheur' && $noeuds[$vers]['type'] === 'declencheur')) {
            continue;
        }
        $etiquette = null;
        if ($noeuds[$de]['type'] === 'condition') {
            $prises = $sorties[$de] ?? [];
            $voulue = in_array($l['etiquette'] ?? '', ['oui', 'non'], true) ? $l['etiquette'] : null;
            $libres = array_values(array_diff(['oui', 'non'], $prises));
            if (!$libres) {
                continue;
            }
            $etiquette = in_array($voulue, $libres, true) ? $voulue : $libres[0];
            $sorties[$de][] = $etiquette;
        }
        $vus["$de>$vers"] = true;
        $liens[] = ['de' => $de, 'vers' => $vers, 'etiquette' => $etiquette];
    }

    return [
        'titre'  => mb_substr(trim((string)($in['titre'] ?? '')), 0, WORKFLOW_MAX_TITRE),
        'noeuds' => array_values($noeuds),
        'liens'  => $liens,
    ];
}

function workflow_vide(): array
{
    return ['titre' => '', 'noeuds' => [], 'liens' => []];
}

// Schéma d'un participant pour la séance (vide s'il n'a pas commencé)
function workflow_de(int $participant_id): array
{
    $st = db()->prepare('SELECT donnees FROM workflow_schemas WHERE formation = ? AND seance = ? AND participant_id = ?');
    $st->execute([formation_slug(), aujourdhui_local(), $participant_id]);
    $d = $st->fetchColumn();
    return $d ? workflow_nettoyer(json_decode($d, true) ?: []) : workflow_vide();
}

function workflow_sauver(int $participant_id, array $schema): void
{
    $st = db()->prepare('INSERT INTO workflow_schemas (formation, seance, participant_id, donnees) VALUES (?, ?, ?, ?)
                         ON DUPLICATE KEY UPDATE donnees = VALUES(donnees)');
    $st->execute([formation_slug(), aujourdhui_local(), $participant_id,
                  json_encode(workflow_nettoyer($schema), JSON_UNESCAPED_UNICODE)]);
}

// Tous les schémas de la séance, avec prénom et équipe
function workflow_schemas(): array
{
    $st = db()->prepare('SELECT w.participant_id, w.donnees, w.updated_at, p.prenom, p.equipe
                         FROM workflow_schemas w JOIN participants p ON p.id = w.participant_id
                         WHERE w.formation = ? AND w.seance = ? ORDER BY p.prenom, w.id');
    $st->execute([formation_slug(), aujourdhui_local()]);
    return array_map(function ($r) {
        $r['schema'] = workflow_nettoyer(json_decode($r['donnees'], true) ?: []);
        unset($r['donnees']);
        return $r;
    }, $st->fetchAll());
}

// Le moteur du tableau blanc, injecté dans les pages (le dossier des exercices
// n'est pas servi directement)
function workflow_script(): string
{
    return '<script>' . file_get_contents(__DIR__ . '/schema.js') . '</script>';
}

// Atelier workflow : le bouton vers workflow.php, derrière l'étape $cle
function bloc_workflow(string $cle = 'workflow'): string
{
    if (!etape_ouverte($cle)) {
        return carte_verrouillee($cle);
    }
    $h = '<section class="card">' . "\n"
       . '    <h2>' . e(etape_titre($cle)) . '</h2>' . "\n";
    if (!participant_courant()) {
        return $h . '    <p class="lead">Indiquez d\'abord votre prénom ci-dessus.</p>' . "\n" . '  </section>';
    }
    return $h . '    <p class="lead">Dessinez un processus : déclencheurs, étapes et conditions, reliés par des flèches. Sur ordinateur ou tablette.</p>' . "\n"
              . '    <a class="btn btn-primary" href="' . e(avec_f('workflow.php')) . '">Ouvrir mon tableau</a>' . "\n"
              . '  </section>';
}
