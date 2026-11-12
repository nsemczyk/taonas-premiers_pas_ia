<?php
// Mise en page du carnet de la journée (voir core/carnet.php) : page de garde,
// page « À retenir », puis une section par exercice. Les exercices n'écrivent
// qu'à travers les helpers ci-dessous, pour un rendu homogène.
// FPDF travaille en CP1252 : tout texte passe par txt().
require_once __DIR__ . '/carnet.php';
require_once __DIR__ . '/../lib/fpdf/fpdf.php';

const CARNET_MOIS = [1 => 'janvier', 'février', 'mars', 'avril', 'mai', 'juin',
                     'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'];

class CarnetPdf extends FPDF
{
    // Couleurs de la charte (RVB)
    public const MARQUE  = [27, 62, 144];
    public const NUIT    = [21, 22, 44];
    public const VIF     = [71, 180, 232];
    public const GLACIER = [226, 243, 250];
    public const OLIVE   = [99, 110, 36];
    public const BRIQUE  = [138, 59, 20];
    public const GRIS    = [194, 195, 199];

    public string $prenom = '';
    public string $formation = '';
    public string $titreCourant = '';   // titre de l'étape en cours, posé par le moteur
    public bool $equipeCourante = false;
    private bool $ouverte = false;      // la section en cours a-t-elle déjà son titre ?
    private bool $pied = false;         // pas d'en-tête ni de pied sur la page de garde

    public function txt(?string $s): string
    {
        $s = str_replace(["\u{2019}", "\u{202F}", "\u{00A0}"], ["'", ' ', ' '], (string)$s);
        $out = @iconv('UTF-8', 'CP1252//TRANSLIT', $s);
        if ($out === false) {
            $out = @iconv('UTF-8', 'CP1252//IGNORE', $s);
        }
        return $out === false ? preg_replace('/[^\x20-\x7E]/', '', $s) : $out;
    }

    private function couleur(array $c, string $quoi = 'T'): void
    {
        match ($quoi) {
            'T' => $this->SetTextColor(...$c),
            'F' => $this->SetFillColor(...$c),
            'D' => $this->SetDrawColor(...$c),
        };
    }

    public function Header(): void
    {
        if (!$this->pied) {
            return;
        }
        $this->SetFont('Helvetica', '', 8);
        $this->couleur(self::MARQUE);
        $this->Cell(0, 5, $this->txt('Mon carnet de la journée · ' . $this->prenom), 0, 0);
        $this->Cell(0, 5, $this->txt($this->formation), 0, 1, 'R');
        $this->couleur(self::VIF, 'D');
        $this->SetLineWidth(0.6);
        $this->Line($this->lMargin, $this->GetY() + 1, $this->w - $this->rMargin, $this->GetY() + 1);
        $this->SetLineWidth(0.2);
        $this->couleur(self::NUIT);
        $this->Ln(5);
    }

    public function Footer(): void
    {
        if (!$this->pied) {
            return;
        }
        $this->SetY(-12);
        $this->SetFont('Helvetica', 'I', 7.5);
        $this->couleur([110, 110, 120]);
        $this->Cell(0, 6, $this->txt('Page ' . $this->PageNo()), 0, 0, 'R');
        $this->couleur(self::NUIT);
    }

    // ------------------------------------------------------------ pages fixes

    public function pageDeGarde(string $jour, ?string $equipe, string $logo): void
    {
        $this->pied = false;
        $this->AddPage();
        if (is_file($logo)) {
            $this->Image($logo, 55, 40, 100);
        }
        $this->SetY(95);
        $this->SetFont('Helvetica', 'B', 26);
        $this->couleur(self::MARQUE);
        $this->Cell(0, 14, $this->txt('Mon carnet de la journée'), 0, 1, 'C');
        $this->SetFont('Helvetica', '', 13);
        $this->couleur(self::NUIT);
        $this->Cell(0, 8, $this->txt($this->formation), 0, 1, 'C');
        $this->Ln(14);
        $this->couleur(self::GLACIER, 'F');
        $this->Rect(45, $this->GetY(), 120, $equipe ? 36 : 28, 'F');
        $this->Ln(6);
        $this->SetFont('Helvetica', 'B', 18);
        $this->Cell(0, 10, $this->txt($this->prenom), 0, 1, 'C');
        $this->SetFont('Helvetica', '', 11);
        $this->Cell(0, 7, $this->txt($jour), 0, 1, 'C');
        if ($equipe) {
            $this->Cell(0, 7, $this->txt('Équipe ' . $equipe), 0, 1, 'C');
        }
        $this->SetY(-50);
        $this->SetFont('Helvetica', 'I', 9.5);
        $this->couleur(self::MARQUE);
        $this->MultiCell(0, 5, $this->txt("Ce que vous avez produit pendant la journée, exercice par exercice, avec les corrigés.\nÀ garder sous la main : vos prompts sont prêts à resservir."), 0, 'C');
        $this->couleur(self::NUIT);
        $this->pied = true;
    }

    // Page « À retenir » : un encadré par mémo déclaré
    public function memo(array $rendus): void
    {
        if (!$rendus) {
            return;
        }
        $this->AddPage();
        $this->bandeau('À retenir');
        foreach ($rendus as $rendu) {
            $rendu($this);
            $this->Ln(3);
        }
    }

    // ------------------------------------------------------------ sections

    // Le moteur prépare la section d'une étape ; l'exercice l'ouvre s'il a du contenu
    public function preparer(string $titre): void
    {
        $this->titreCourant = $titre;
        $this->ouverte = false;
    }

    // Ouvre la section en cours (titre de l'étape) ; $equipe : travail d'équipe ;
    // $paysage : la section commence sur une page à l'italienne (un schéma)
    public function exercice(bool $equipe = false, string $complement = '', bool $paysage = false): void
    {
        if ($this->ouverte) {
            return;
        }
        $this->ouverte = true;
        if ($paysage) {
            $this->AddPage('L');
        } elseif ($this->CurOrientation !== 'P' || $this->GetY() > $this->PageBreakTrigger - 60) {
            $this->AddPage('P');
        } elseif ($this->GetY() > 30) {
            $this->Ln(6);
        }
        $this->bandeau($this->titreCourant, $equipe ? 'Travail d\'équipe' . ($complement !== '' ? ' · ' . $complement : '') : $complement);
    }

    public function bandeau(string $titre, string $etiquette = ''): void
    {
        $this->couleur(self::MARQUE, 'F');
        $this->couleur([255, 255, 255]);
        $this->SetFont('Helvetica', 'B', 13);
        $this->Cell(0, 10, '  ' . $this->txt($titre), 0, 0, 'L', true);
        if ($etiquette !== '') {
            $this->SetFont('Helvetica', '', 8.5);
            $this->SetX($this->lMargin);
            $this->Cell(0, 10, $this->txt($etiquette) . '  ', 0, 0, 'R');
        }
        $this->Ln(13);
        $this->couleur(self::NUIT);
    }

    public function titre(string $titre): void
    {
        if ($this->GetY() > $this->PageBreakTrigger - 25) {
            $this->AddPage();
        }
        $this->Ln(1.5);
        $this->SetFont('Helvetica', 'B', 10.5);
        $this->couleur(self::MARQUE);
        $this->MultiCell(0, 5.5, $this->txt($titre), 0, 'L');
        $this->couleur(self::NUIT);
        $this->Ln(0.5);
    }

    public function texte(string $texte, string $style = '', float $taille = 9.5): void
    {
        $this->SetFont('Helvetica', $style, $taille);
        $this->MultiCell(0, 4.8, $this->txt($texte), 0, 'L');
        $this->Ln(1);
    }

    public function note(string $texte): void
    {
        $this->couleur([90, 95, 120]);
        $this->texte($texte, 'I', 8.5);
        $this->couleur(self::NUIT);
    }

    // Texte encadré sur fond clair (une production, un prompt…) ; vide : tiret
    public function encadre(string $texte, ?string $etiquette = null): void
    {
        $texte = trim($texte) !== '' ? trim($texte) : '—';
        // Un encadré court ne se coupe pas entre deux pages (ni ne se sépare de son étiquette)
        $this->SetFont('Helvetica', '', 9.5);
        $haut = $this->nbLignes($this->w - $this->lMargin - $this->rMargin, $this->txt($texte)) * 4.8 + ($etiquette !== null ? 5 : 0);
        if ($haut < 60 && $this->GetY() + $haut > $this->PageBreakTrigger) {
            $this->AddPage();
        }
        if ($etiquette !== null) {
            $this->SetFont('Helvetica', 'B', 8.5);
            $this->couleur([90, 95, 120]);
            $this->Cell(0, 5, $this->txt(mb_strtoupper($etiquette)), 0, 1);
            $this->couleur(self::NUIT);
        }
        $this->SetFont('Helvetica', '', 9.5);
        $this->couleur(self::GLACIER, 'F');
        $this->SetX($this->lMargin);
        $this->MultiCell(0, 4.8, $this->txt($texte), 0, 'L', true);
        $this->Ln(2.5);
    }

    public function liste(array $lignes): void
    {
        $this->SetFont('Helvetica', '', 9.5);
        foreach ($lignes as $l) {
            $this->Cell(5, 4.8, chr(149), 0, 0);
            $this->MultiCell(0, 4.8, $this->txt($l), 0, 'L');
        }
        $this->Ln(1.5);
    }

    // Coche verte ou croix rouge, dessinées (pas de police de symboles), puis texte
    public function verdict(bool $ok, string $texte): void
    {
        $this->signe($ok, $this->GetX() + 2.5, $this->GetY() + 2.5);
        $this->SetX($this->GetX() + 6);
        $this->SetFont('Helvetica', '', 9.5);
        $this->couleur($ok ? self::OLIVE : self::BRIQUE);
        $this->MultiCell(0, 5, $this->txt($texte), 0, 'L');
        $this->couleur(self::NUIT);
    }

    // Coche ou croix de 3 mm centrée en (x, y)
    public function signe(bool $ok, float $x, float $y): void
    {
        $this->couleur($ok ? self::OLIVE : self::BRIQUE, 'D');
        $this->SetLineWidth(0.6);
        if ($ok) {
            $this->Line($x - 1.5, $y, $x - 0.4, $y + 1.2);
            $this->Line($x - 0.4, $y + 1.2, $x + 1.6, $y - 1.3);
        } else {
            $this->Line($x - 1.2, $y - 1.2, $x + 1.2, $y + 1.2);
            $this->Line($x - 1.2, $y + 1.2, $x + 1.2, $y - 1.2);
        }
        $this->SetLineWidth(0.2);
        $this->couleur(self::NUIT, 'D');
    }

    // Tableau : $largeurs en mm (somme ≤ 180), lignes de hauteur ajustée au texte
    public function tableau(array $entete, array $lignes, array $largeurs): void
    {
        // En-tête : libellés longs sur plusieurs lignes
        $this->SetFont('Helvetica', 'B', 8.5);
        $h = 0;
        foreach ($entete as $i => $t) {
            $h = max($h, $this->nbLignes($largeurs[$i], $this->txt($t)) * 4.2 + 2.8);
        }
        if ($this->GetY() + $h + 12 > $this->PageBreakTrigger) {
            $this->AddPage();
        }
        $this->couleur(self::NUIT, 'F');
        $this->couleur([255, 255, 255]);
        $y = $this->GetY();
        $this->Rect($this->lMargin, $y, array_sum($largeurs), $h, 'F');
        $x = $this->lMargin;
        foreach ($entete as $i => $t) {
            $this->SetXY($x, $y + 1.4);
            $this->MultiCell($largeurs[$i], 4.2, $this->txt($t), 0, 'L');
            $x += $largeurs[$i];
        }
        $this->SetXY($this->lMargin, $y + $h);
        $this->couleur(self::NUIT);
        $this->SetFont('Helvetica', '', 8.5);
        foreach ($lignes as $ligne) {
            $h = 0;
            foreach ($ligne as $i => $t) {
                $h = max($h, $this->nbLignes($largeurs[$i], $this->txt((string)$t)) * 4.4 + 2);
            }
            if ($this->GetY() + $h > $this->PageBreakTrigger) {
                $this->AddPage();
            }
            $x = $this->lMargin;
            $y = $this->GetY();
            foreach ($ligne as $i => $t) {
                $this->SetXY($x, $y + 1);
                $this->MultiCell($largeurs[$i], 4.4, $this->txt((string)$t), 0, 'L');
                $x += $largeurs[$i];
            }
            $this->couleur(self::GRIS, 'D');
            $this->Line($this->lMargin, $y + $h, $this->lMargin + array_sum($largeurs), $y + $h);
            $this->couleur(self::NUIT, 'D');
            $this->SetXY($this->lMargin, $y + $h);
        }
        $this->Ln(3);
    }

    // Nombre de lignes qu'occupera un MultiCell de largeur $w
    public function nbLignes(float $w, string $txt): int
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
            if ($c === "\n") {
                $i++; $sep = -1; $j = $i; $l = 0; $nl++;
                continue;
            }
            if ($c === ' ') {
                $sep = $i;
            }
            $l += $cw[$c] ?? 0;
            if ($l > $wmax) {
                if ($sep === -1) {
                    if ($i === $j) { $i++; }
                } else {
                    $i = $sep + 1;
                }
                $sep = -1; $j = $i; $l = 0; $nl++;
            } else {
                $i++;
            }
        }
        return $nl;
    }

    // ------------------------------------------------------------ dessin

    // Zone utile de la page courante : [x, largeur]
    public function zone(): array
    {
        return [$this->lMargin, $this->w - $this->lMargin - $this->rMargin];
    }

    public function basDePage(): float
    {
        return $this->PageBreakTrigger;
    }

    public function remplissage(array $fond, array $trait, float $epaisseur = 0.4): void
    {
        $this->couleur($fond, 'F');
        $this->couleur($trait, 'D');
        $this->SetLineWidth($epaisseur);
    }

    // Polygone fermé : [x1, y1, x2, y2, …] en mm
    public function polygone(array $p, string $style = 'DF'): void
    {
        $op = $style === 'F' ? 'f' : ($style === 'D' ? 's' : 'b');
        $s = '';
        for ($i = 0; $i < count($p); $i += 2) {
            $s .= sprintf('%.2F %.2F %s ', $p[$i] * $this->k, ($this->h - $p[$i + 1]) * $this->k, $i === 0 ? 'm' : 'l');
        }
        $this->_out($s . $op);
    }

    // Ellipse de centre (x, y) et rayons rx, ry (courbes de Bézier)
    public function ellipse(float $x, float $y, float $rx, float $ry, string $style = 'DF'): void
    {
        $op = $style === 'F' ? 'f' : ($style === 'D' ? 'S' : 'B');
        $lx = 4 / 3 * (M_SQRT2 - 1) * $rx;
        $ly = 4 / 3 * (M_SQRT2 - 1) * $ry;
        $k = $this->k;
        $h = $this->h;
        $pt = fn($a, $b) => sprintf('%.2F %.2F', $a * $k, ($h - $b) * $k);
        $this->_out($pt($x + $rx, $y) . ' m '
            . $pt($x + $rx, $y - $ly) . ' ' . $pt($x + $lx, $y - $ry) . ' ' . $pt($x, $y - $ry) . ' c '
            . $pt($x - $lx, $y - $ry) . ' ' . $pt($x - $rx, $y - $ly) . ' ' . $pt($x - $rx, $y) . ' c '
            . $pt($x - $rx, $y + $ly) . ' ' . $pt($x - $lx, $y + $ry) . ' ' . $pt($x, $y + $ry) . ' c '
            . $pt($x + $lx, $y + $ry) . ' ' . $pt($x + $rx, $y + $ly) . ' ' . $pt($x + $rx, $y) . ' c ' . $op);
    }

    // Texte centré dans une boîte (lignes coupées, taille réduite si besoin)
    public function texteCentre(float $x, float $y, float $w, float $h, string $texte, float $taille = 8): void
    {
        $t = $this->txt($texte);
        do {
            $this->SetFont('Helvetica', '', $taille);
            $lignes = $this->nbLignes($w, $t);
            $hl = $taille * 0.42;
            $taille -= 0.5;
        } while ($lignes * $hl > $h && $taille > 4.5);
        $this->SetXY($x, $y + ($h - $lignes * $hl) / 2);
        $this->MultiCell($w, $hl, $t, 0, 'C');
    }
}

// Le carnet d'un participant : page de garde, « À retenir », puis les sections
// déclarées par les exercices, dans l'ordre des étapes de la formation
function carnet_pdf(array $participant): CarnetPdf
{
    $pdf = new CarnetPdf('P', 'mm', 'A4');
    $pdf->SetMargins(15, 15, 15);
    $pdf->SetAutoPageBreak(true, 18);
    $pdf->SetTitle($pdf->txt('Mon carnet de la journée - ' . $participant['prenom']));
    $pdf->SetAuthor($pdf->txt(formation()['titre']));
    $pdf->prenom = $participant['prenom'];
    $pdf->formation = formation()['titre'];

    $t = strtotime(aujourdhui_local());
    $jour = (int)date('j', $t) . ' ' . CARNET_MOIS[(int)date('n', $t)] . ' ' . date('Y', $t);
    $equipe = equipe_existe($participant['equipe']) ? equipes()[$participant['equipe']]['nom'] : null;
    $pdf->pageDeGarde($jour, $equipe, __DIR__ . '/../assets/logo-carnet.jpg');

    $registre = carnet_registre();
    $etapes = formation()['etapes'] ?? [];
    $pdf->memo(array_column(array_filter($registre['memo'], fn($m) => isset($etapes[$m[0]])), 1));

    $pdf->AddPage();
    $debut = $pdf->PageNo();
    foreach (array_keys($etapes) as $etape) {
        foreach ($registre['sections'][$etape] ?? [] as $rendu) {
            $pdf->preparer(etape_titre($etape));
            try {
                $rendu($pdf, $participant);
            } catch (PDOException $e) {
                // table de l'exercice pas encore migrée : section ignorée
            }
        }
    }
    if ($pdf->GetY() < 40 && $pdf->PageNo() === $debut) {
        $pdf->texte('Rien à montrer pour l\'instant : vos productions apparaîtront ici au fil des exercices.', 'I');
    }
    return $pdf;
}
