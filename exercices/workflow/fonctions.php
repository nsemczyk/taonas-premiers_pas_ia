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

// Carnet de la journée : le schéma redessiné, sur une page à l'italienne.
// Mêmes formes et couleurs que le tableau blanc (schema.js).
require_once __DIR__ . '/../../core/carnet.php';
const WORKFLOW_FORMES_PDF = [   // demi-largeur, demi-hauteur (repère du tableau), fond, trait
    'declencheur' => [62, 62, [221, 241, 251], [71, 180, 232]],
    'etape'       => [100, 44, [255, 255, 255], [27, 62, 144]],
    'condition'   => [105, 66, [255, 246, 214], [201, 162, 39]],
];

// Point du bord d'une forme dans la direction (dx, dy) depuis son centre (comme bord() dans schema.js)
function workflow_bord(array $n, float $dx, float $dy): array
{
    [$rx, $ry] = WORKFLOW_FORMES_PDF[$n['type']];
    if (!$dx && !$dy) {
        return [$n['x'], $n['y']];
    }
    $t = match ($n['type']) {
        'declencheur' => $rx / sqrt($dx * $dx + $dy * $dy),
        'etape'       => min($dx ? $rx / abs($dx) : INF, $dy ? $ry / abs($dy) : INF),
        default       => 1 / (abs($dx) / $rx + abs($dy) / $ry),
    };
    return [$n['x'] + $dx * $t, $n['y'] + $dy * $t];
}

carnet_section('workflow', function (CarnetPdf $pdf, array $p) {
    $s = workflow_de((int)$p['id']);
    if (!$s['noeuds']) {
        return;
    }
    $pdf->exercice(false, '', true);
    if ($s['titre'] !== '') {
        $pdf->texte($s['titre'], 'B', 11);
    }
    // Cadrage sur les formes, à l'échelle de la place disponible
    $noeuds = array_column($s['noeuds'], null, 'id');
    $minX = $minY = INF; $maxX = $maxY = -INF;
    foreach ($noeuds as $n) {
        [$rx, $ry] = WORKFLOW_FORMES_PDF[$n['type']];
        $minX = min($minX, $n['x'] - $rx); $maxX = max($maxX, $n['x'] + $rx);
        $minY = min($minY, $n['y'] - $ry); $maxY = max($maxY, $n['y'] + $ry);
    }
    [$zx, $zl] = $pdf->zone();
    $zy = $pdf->GetY() + 2;
    $zh = $pdf->basDePage() - $zy - 2;
    $k = min($zl / ($maxX - $minX + 40), $zh / ($maxY - $minY + 40), 0.25);
    $ox = $zx + ($zl - ($maxX - $minX) * $k) / 2 - $minX * $k;
    $oy = $zy + 20 * $k - $minY * $k;
    $px = fn($x) => $ox + $x * $k;
    $py = fn($y) => $oy + $y * $k;

    // Flèches, pointe pleine à l'arrivée
    $pdf->remplissage([41, 47, 108], [41, 47, 108], 0.5);
    $etiquettes = [];
    foreach ($s['liens'] as $l) {
        if (!isset($noeuds[$l['de']], $noeuds[$l['vers']])) {
            continue;
        }
        $a = $noeuds[$l['de']]; $b = $noeuds[$l['vers']];
        [$x1, $y1] = workflow_bord($a, $b['x'] - $a['x'], $b['y'] - $a['y']);
        [$x2, $y2] = workflow_bord($b, $a['x'] - $b['x'], $a['y'] - $b['y']);
        [$x1, $y1, $x2, $y2] = [$px($x1), $py($y1), $px($x2), $py($y2)];
        $d = max(0.01, hypot($x2 - $x1, $y2 - $y1));
        [$ux, $uy] = [($x2 - $x1) / $d, ($y2 - $y1) / $d];
        $pdf->Line($x1, $y1, $x2 - $ux * 2.5, $y2 - $uy * 2.5);
        $pdf->polygone([$x2, $y2, $x2 - $ux * 3.2 - $uy * 1.5, $y2 - $uy * 3.2 + $ux * 1.5,
                        $x2 - $ux * 3.2 + $uy * 1.5, $y2 - $uy * 3.2 - $ux * 1.5], 'DF');
        if ($l['etiquette']) {
            $etiquettes[] = [($x1 + $x2) / 2, ($y1 + $y2) / 2, $l['etiquette']];
        }
    }
    // Formes et textes
    $taille = max(6, min(11, 48 * $k));
    foreach ($noeuds as $n) {
        [$rx, $ry, $fond, $trait] = WORKFLOW_FORMES_PDF[$n['type']];
        [$x, $y, $rx, $ry] = [$px($n['x']), $py($n['y']), $rx * $k, $ry * $k];
        $pdf->remplissage($fond, $trait, 0.6);
        if ($n['type'] === 'declencheur') {
            $pdf->ellipse($x, $y, $rx, $ry);
            $pdf->SetTextColor(...CarnetPdf::NUIT);
            $pdf->texteCentre($x - $rx * 0.75, $y - $ry * 0.7, $rx * 1.5, $ry * 1.4, $n['texte'], $taille);
        } elseif ($n['type'] === 'etape') {
            $pdf->Rect($x - $rx, $y - $ry, 2 * $rx, 2 * $ry, 'DF');
            $pdf->SetTextColor(...CarnetPdf::NUIT);
            $pdf->texteCentre($x - $rx * 0.92, $y - $ry * 0.85, $rx * 1.84, $ry * 1.7, $n['texte'], $taille);
        } else {
            $pdf->polygone([$x, $y - $ry, $x + $rx, $y, $x, $y + $ry, $x - $rx, $y], 'DF');
            $pdf->SetTextColor(...CarnetPdf::NUIT);
            $pdf->texteCentre($x - $rx * 0.55, $y - $ry * 0.55, $rx * 1.1, $ry * 1.1, $n['texte'], $taille);
        }
    }
    // Oui / Non au milieu des sorties de condition
    foreach ($etiquettes as [$x, $y, $e]) {
        $pdf->remplissage([255, 255, 255], [41, 47, 108], 0.3);
        $pdf->Rect($x - 5, $y - 2.6, 10, 5.2, 'DF');
        $pdf->SetFont('Helvetica', 'B', 7.5);
        $pdf->SetTextColor(...($e === 'oui' ? CarnetPdf::OLIVE : CarnetPdf::BRIQUE));
        $pdf->SetXY($x - 5, $y - 2.6);
        $pdf->Cell(10, 5.2, $e === 'oui' ? 'Oui' : 'Non', 0, 0, 'C');
    }
    $pdf->SetTextColor(...CarnetPdf::NUIT);
    $pdf->SetDrawColor(...CarnetPdf::NUIT);
    $pdf->SetLineWidth(0.2);
    $pdf->SetXY($zx, $py($maxY) + 6);
});
