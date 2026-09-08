<?php
require __DIR__ . '/horodatage.php';            // -> config.php + dates
require __DIR__ . '/eval-froide-questions.php'; // définitions + helpers

// Accès réservé à l'animateur
if (!hash_equals(CLE_ANIMATEUR, (string)($_GET['cle'] ?? ''))) {
    http_response_code(403);
    exit('Accès réservé. Ajoutez ?cle=... à l\'adresse.');
}

$chemin_fpdf = __DIR__ . '/lib/fpdf/fpdf.php';
if (!is_file($chemin_fpdf)) {
    http_response_code(500);
    exit('Bibliothèque PDF absente : déposez fpdf.php et son dossier font/ dans lib/fpdf/.');
}
require_once $chemin_fpdf;

try {
    $reponses = db()->query(
        'SELECT r.*, t.libelle FROM eval_froide r
         JOIN eval_froide_tokens t ON t.id = r.token_id
         ORDER BY r.created_at'
    )->fetchAll();
} catch (PDOException $e) {
    http_response_code(500);
    exit('Tables absentes : jouez migration-eval-froide.sql.');
}

if (!$reponses) {
    http_response_code(404);
    exit('Aucune réponse à exporter pour le moment.');
}

/**
 * Évaluation à froid restituée telle qu'elle a été remplie.
 * FPDF travaille en CP1252 : tout texte passe par txt().
 */
class EvalFroidePdf extends FPDF
{
    public int $numero = 0;
    public int $total = 0;

    private const L_UTILE = 180;   // 210 - 2 × 15 mm de marge

    // UTF-8 -> CP1252, qui couvre tout le français (accents, œ, €, « », — et ')
    public function txt(?string $s): string
    {
        $s = (string)$s;
        $out = @iconv('UTF-8', 'CP1252//TRANSLIT', $s);
        if ($out === false) {
            $out = @iconv('UTF-8', 'CP1252//IGNORE', $s);
        }
        return $out === false ? preg_replace('/[^\x20-\x7E]/', '', $s) : $out;
    }

    public function Header(): void
    {
        $this->SetFont('Helvetica', 'B', 14);
        $this->Cell(0, 7, $this->txt('Évaluation à froid'), 0, 1);

        $this->SetFont('Helvetica', '', 9);
        $this->Cell(120, 5, $this->txt('Premiers pas avec l\'IA générative — suivi après formation'), 0, 0);
        $this->Cell(0, 5, $this->txt('Réponse ' . $this->numero . ' / ' . $this->total), 0, 1, 'R');

        $this->SetLineWidth(0.4);
        $this->Line(15, $this->GetY() + 1, 195, $this->GetY() + 1);
        $this->SetLineWidth(0.2);
        $this->Ln(4);
    }

    public function Footer(): void
    {
        $this->SetY(-15);
        $this->SetLineWidth(0.2);
        $this->Line(15, $this->GetY(), 195, $this->GetY());
        $this->SetFont('Helvetica', 'I', 7.5);
        $this->SetTextColor(80);
        $this->Cell(0, 8, $this->txt('Nom / Prénom facultatif — suppression sur simple demande auprès du formateur.'), 0, 0);
        $this->Cell(0, 8, $this->txt('Page ' . $this->PageNo()), 0, 0, 'R');
        $this->SetTextColor(0);
    }

    // Intitulé de question (numéro en gras)
    public function question(string $num, string $intitule): void
    {
        $this->Ln(1.5);
        $this->SetFont('Helvetica', 'B', 9.5);
        $this->MultiCell(self::L_UTILE, 5, $this->txt(($num !== '' ? $num . '. ' : '') . $intitule), 0, 'L');
    }

    // Réponse à choix unique : uniquement le libellé retenu, indenté.
    // Si rien n'a été renseigné, on l'indique en gris plutôt que de lister
    // toutes les options possibles.
    public function reponse(string $texte, bool $renseigne = true): void
    {
        if (!$renseigne || $texte === '') {
            $this->SetFont('Helvetica', 'I', 8.5);
            $this->SetTextColor(120);
            $texte = 'Non renseigné';
        } else {
            $this->SetFont('Helvetica', '', 9);
            $this->SetTextColor(0);
        }
        $this->SetX(22);
        $this->MultiCell(self::L_UTILE - 7, 4.8, $this->txt($texte), 0, 'L');
        $this->SetTextColor(0);
        $this->Ln(0.6);
    }

    // Réponses à choix multiple : une puce par réponse cochée seulement.
    public function reponsesMulti(array $libelles): void
    {
        $this->SetFont('Helvetica', '', 9);
        $this->SetTextColor(0);
        foreach ($libelles as $lib) {
            $y = $this->GetY();
            $this->SetXY(22, $y);
            $this->MultiCell(self::L_UTILE - 7, 4.8, $this->txt('- ' . $lib), 0, 'L');
            $this->Ln(0.4);
        }
    }

    // Nombre de lignes qu'occupera un MultiCell : sert à cadrer la zone de texte
    private function nbLignes(float $w, string $txt): int
    {
        $cw = $this->CurrentFont['cw'];
        $wmax = ($w - 2 * $this->cMargin) * 1000 / $this->FontSize;
        $s = str_replace("\r", '', $txt);
        $nb = strlen($s);
        if ($nb > 0 && $s[$nb - 1] === "\n") {
            $nb--;
        }
        $sep = -1; $i = 0; $j = 0; $l = 0; $nl = 1;
        while ($i < $nb) {
            $c = $s[$i];
            if ($c === "\n") { $i++; $sep = -1; $j = $i; $l = 0; $nl++; continue; }
            if ($c === ' ') { $sep = $i; }
            $l += $cw[$c] ?? 0;
            if ($l > $wmax) {
                if ($sep === -1) { if ($i === $j) { $i++; } }
                else { $i = $sep + 1; }
                $sep = -1; $j = $i; $l = 0; $nl++;
            } else { $i++; }
        }
        return $nl;
    }

    // Zone de texte libre : encadré ajusté au contenu réellement saisi.
    // Vide, on l'indique en gris au lieu d'un grand cadre vierge.
    public function zoneTexte(?string $contenu): void
    {
        $contenu = trim((string)$contenu);
        $this->Ln(0.5);

        if ($contenu === '') {
            $this->reponse('', false);
            return;
        }

        $this->SetFont('Helvetica', '', 9);
        $texte   = $this->txt($contenu);
        $hLigne  = 4.4;
        $hauteur = $this->nbLignes(self::L_UTILE, $texte) * $hLigne + 3;

        $y = $this->GetY();
        $this->Rect(15, $y, self::L_UTILE, $hauteur);
        $this->SetXY(16.5, $y + 1.5);
        $this->MultiCell(self::L_UTILE - 3, $hLigne, $texte, 0, 'L');
        $this->SetXY(15, $y + $hauteur);
        $this->Ln(2.5);
    }

    // « Autre : … » libre saisi en complément d'une liste
    public function autre(?string $contenu): void
    {
        $contenu = trim((string)$contenu);
        if ($contenu === '') {
            return;
        }
        $this->SetFont('Helvetica', 'I', 8.5);
        $this->SetX(22);
        $this->MultiCell(self::L_UTILE - 7, 4.6, $this->txt('Autre : ' . $contenu), 0, 'L');
        $this->Ln(0.6);
    }
}

$pdf = new EvalFroidePdf('P', 'mm', 'A4');
$pdf->SetTitle('Évaluations à froid');
$pdf->SetAuthor('Formation IA générative');
$pdf->SetMargins(15, 15, 15);
$pdf->SetAutoPageBreak(true, 18);
$pdf->total = count($reponses);

foreach ($reponses as $i => $r) {
    $pdf->numero = $i + 1;
    $pdf->AddPage();

    // Identité : nom facultatif, repère du lien, date de dépôt
    $nom = trim((string)($r['nom_prenom'] ?? ''));
    $lib = trim((string)($r['libelle'] ?? ''));
    $pdf->SetFont('Helvetica', '', 9);
    $pdf->Cell(24, 6, $pdf->txt('Nom / Prénom :'), 0, 0);
    $pdf->SetFont('Helvetica', 'B', 9);
    $pdf->Cell(90, 6, $pdf->txt($nom !== '' ? $nom : 'Non renseigné'), 'B', 0);
    $pdf->SetFont('Helvetica', '', 9);
    $pdf->Cell(20, 6, $pdf->txt('   Reçu le :'), 0, 0);
    $pdf->SetFont('Helvetica', 'B', 9);
    $pdf->Cell(0, 6, $pdf->txt(moment_local($r['created_at'])->format('d/m/Y H:i')), 'B', 1);
    if ($lib !== '') {
        $pdf->SetFont('Helvetica', 'I', 8);
        $pdf->SetTextColor(90);
        $pdf->Cell(0, 5, $pdf->txt('Lien : ' . $lib), 0, 1);
        $pdf->SetTextColor(0);
    }
    $pdf->Ln(1.5);

    foreach ($EF_QUESTIONS as $q) {
        $pdf->question($q['num'], $q['q']);
        rendre_reponse_pdf($pdf, $q, $r);

        foreach ($q['sub'] ?? [] as $sub) {
            $pdf->question('', $sub['q']);
            rendre_reponse_pdf($pdf, $sub, $r);
        }
    }
}

// Rend une question (ou sous-question) : seules les réponses retenues sont
// imprimées, jamais la liste complète des options possibles.
function rendre_reponse_pdf(EvalFroidePdf $pdf, array $q, array $r): void
{
    global $EF_SIMPLE, $EF_MULTI;

    if ($q['type'] === 'texte') {
        $pdf->zoneTexte($r[$q['field']] ?? '');
        return;
    }

    $autre = !empty($q['autre']) ? trim((string)($r[$q['autre']] ?? '')) : '';

    if ($q['type'] === 'simple') {
        $code = trim((string)($r[$q['field']] ?? ''));
        if ($code === '' && $autre === '') {
            $pdf->reponse('', false);
            return;
        }
        if ($code !== '') {
            $pdf->reponse(ef_libelle_simple($EF_SIMPLE[$q['opt']], $code));
        }
    } else {
        $libelles = ef_libelles_multi($EF_MULTI[$q['opt']], $r[$q['field']] ?? '');
        if (!$libelles && $autre === '') {
            $pdf->reponse('', false);
            return;
        }
        $pdf->reponsesMulti($libelles);
    }

    if ($autre !== '') {
        $pdf->autre($autre);
    }
}

$pdf->Output('D', 'evaluations-a-froid.pdf');
