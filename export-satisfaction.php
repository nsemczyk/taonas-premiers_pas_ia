<?php
require __DIR__ . '/horodatage.php';

// Accès réservé à l'animateur
if (($_GET['cle'] ?? '') !== CLE_ANIMATEUR) {
    http_response_code(403);
    exit('Accès réservé. Ajoutez ?cle=... à l\'adresse.');
}

// Le questionnaire, dans l'ordre exact du formulaire rempli par les participants
const EXPORT_CRITERES = [
    'sat_accueil'   => 'Accueil et organisation',
    'sat_animation' => 'Qualité de l\'animation',
    'sat_clarte'    => 'Clarté des explications',
    'sat_contenu'   => 'Contenu de la formation',
    'sat_exercices' => 'Exercices proposés',
    'sat_supports'  => 'Supports remis',
    'sat_duree'     => 'Durée de la formation',
];

const EXPORT_CAPACITES = [
    'capable_utiliser_ia'      => 'Utiliser une IA générative',
    'capable_prompt'           => 'Rédiger un prompt efficace',
    'capable_cv'               => 'Améliorer mon CV grâce à l\'IA',
    'capable_lettre'           => 'Rédiger une lettre de motivation avec l\'IA',
    'capable_recherche_emploi' => 'Utiliser l\'IA dans ma recherche d\'emploi',
];

const ECHELLE_SATISFACTION = [1 => 'Pas satisfait', 2 => 'Peu satisfait', 3 => 'Satisfait', 4 => 'Très satisfait'];
const ECHELLE_CAPACITE     = [1 => 'Non', 2 => 'Partiellement', 3 => 'Oui'];
const ECHELLE_RYTHME       = ['LENT' => 'Trop lent', 'BIEN' => 'Bien dosé', 'RAPIDE' => 'Trop rapide'];
const ECHELLE_RECOMMANDE   = [
    'NON'              => 'Non',
    'NON-PAS-VRAIMENT' => 'Non, pas vraiment',
    'OUI-PROBABLEMENT' => 'Oui, probablement',
    'OUI-SANS-HESITER' => 'Oui, sans hésiter',
];
const ECHELLE_INTERESSE = ['NON' => 'Non', 'OUI' => 'Oui'];

const EXPORT_MOIS = [1 => 'janvier', 'février', 'mars', 'avril', 'mai', 'juin',
                     'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'];

function jour_valide(?string $d): string
{
    $d = trim((string)$d);
    return preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) && strtotime($d) ? $d : '';
}

function jour_en_toutes_lettres(string $d): string
{
    $t = strtotime($d);
    return (int)date('j', $t) . ' ' . EXPORT_MOIS[(int)date('n', $t)] . ' ' . date('Y', $t);
}

$jour = jour_valide($_GET['d'] ?? '');

// ------------------------------------------------- écran de choix de journée --
if ($jour === '') {
    // Regroupement en PHP : la journée est celle de l'heure locale
    $compte = [];
    foreach (db()->query('SELECT created_at FROM satisfaction') as $r) {
        $d = jour_local($r['created_at']);
        $compte[$d] = ($compte[$d] ?? 0) + 1;
    }
    krsort($compte);
    $jours = [];
    foreach ($compte as $d => $n) {
        $jours[] = ['d' => $d, 'n' => $n];
    }
    ?><!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="icon" href="assets/favicon-couleur.png">
<meta name="robots" content="noindex">
<title>Exporter les questionnaires</title>
<link rel="stylesheet" href="style.css">
</head>
<body>
<header class="site-head">
  <h1>Exporter les questionnaires</h1>
  <p>Un PDF par journée, un questionnaire rempli par page</p>
</header>
<main class="wrap">
  <section class="card">
    <h2>Choisir la journée</h2>
    <?php if ($jours): ?>
      <?php foreach ($jours as $j): ?>
        <a class="btn btn-primary"
           href="export-satisfaction.php?cle=<?= rawurlencode(CLE_ANIMATEUR) ?>&amp;d=<?= e($j['d']) ?>">
          <?= e(jour_en_toutes_lettres($j['d'])) ?> — <?= (int)$j['n'] ?> avis
        </a>
      <?php endforeach; ?>
    <?php else: ?>
      <p class="lead">Aucun avis enregistré pour le moment.</p>
    <?php endif; ?>
  </section>
  <section class="card">
    <a class="btn btn-ghost" href="resultats.php?cle=<?= rawurlencode(CLE_ANIMATEUR) ?>">Retour aux résultats</a>
  </section>
</main>
</body>
</html><?php
    exit;
}

// ------------------------------------------------------- génération du PDF ----
$chemin_fpdf = __DIR__ . '/lib/fpdf/fpdf.php';
if (!is_file($chemin_fpdf)) {
    http_response_code(500);
    exit('Bibliothèque PDF absente : déposez fpdf.php et son dossier font/ dans lib/fpdf/.');
}
require_once $chemin_fpdf;

// Bornes de la journée locale traduites dans le fuseau du serveur
[$debut, $fin] = bornes_journee($jour);
$st = db()->prepare('SELECT * FROM satisfaction
                     WHERE created_at >= ? AND created_at < ? ORDER BY created_at');
$st->execute([$debut, $fin]);
$avis = $st->fetchAll();

if (!$avis) {
    http_response_code(404);
    exit('Aucun avis pour cette journée.');
}

/**
 * Questionnaire de satisfaction restitué tel qu'il a été rempli.
 * FPDF travaille en CP1252 : tout texte passe par txt().
 */
class QuestionnairePdf extends FPDF
{
    public string $journee = '';
    public int $numero = 0;
    public int $total = 0;

    private const L_UTILE  = 180;   // 210 - 2 × 15 mm de marge
    private const L_INTITULE = 66;  // colonne de gauche des grilles

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
        $this->Cell(0, 7, $this->txt('Questionnaire de satisfaction'), 0, 1);

        $this->SetFont('Helvetica', '', 9);
        $this->Cell(120, 5, $this->txt('Premiers pas avec l\'IA générative — ' . $this->journee), 0, 0);
        $this->Cell(0, 5, $this->txt('Avis ' . $this->numero . ' / ' . $this->total), 0, 1, 'R');

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
        $this->Cell(0, 8, $this->txt(
            'Questionnaire anonyme — aucune donnée personnelle collectée hormis le prénom, facultatif.'
        ), 0, 0);
        $this->Cell(0, 8, $this->txt('Page ' . $this->PageNo()), 0, 0, 'R');
        $this->SetTextColor(0);
    }

    public function titreSection(string $titre): void
    {
        $this->Ln(2);
        $this->SetFont('Helvetica', 'B', 10.5);
        $this->Cell(0, 6, $this->txt($titre), 'B', 1);
        $this->Ln(1.5);
    }

    // Case à cocher : croix tracée quand c'est la réponse retenue
    private function caseACocher(float $x, float $y, bool $cochee): void
    {
        $c = 3.4;
        $this->Rect($x, $y, $c, $c);
        if ($cochee) {
            $m = 0.7;
            $this->Line($x + $m, $y + $m, $x + $c - $m, $y + $c - $m);
            $this->Line($x + $c - $m, $y + $m, $x + $m, $y + $c - $m);
        }
    }

    // En-tête de grille : les niveaux de l'échelle, une colonne chacun
    public function enteteGrille(array $echelle): void
    {
        $largeur = (self::L_UTILE - self::L_INTITULE) / count($echelle);
        $this->SetFont('Helvetica', '', 7.5);
        $this->Cell(self::L_INTITULE, 5, '', 0, 0);
        foreach ($echelle as $libelle) {
            $this->Cell($largeur, 5, $this->txt($libelle), 0, 0, 'C');
        }
        $this->Ln(5);
    }

    // Ligne de grille : intitulé à gauche, une case par niveau
    public function ligneGrille(string $intitule, array $echelle, $reponse): void
    {
        $largeur = (self::L_UTILE - self::L_INTITULE) / count($echelle);
        $h = 6.4;
        $y = $this->GetY();

        $this->SetFont('Helvetica', '', 8.5);
        $this->Cell(self::L_INTITULE, $h, $this->txt($intitule), 'B', 0);

        $x = $this->GetX();
        foreach (array_keys($echelle) as $valeur) {
            $this->Cell($largeur, $h, '', 'B', 0);
            $this->caseACocher($x + ($largeur - 3.4) / 2, $y + ($h - 3.4) / 2,
                               (string)$valeur === (string)$reponse);
            $x += $largeur;
        }
        $this->Ln($h);
    }

    // Question à réponse unique, cases alignées sur une ligne
    public function questionEnLigne(string $intitule, array $echelle, $reponse): void
    {
        $this->SetFont('Helvetica', 'B', 8.5);
        $this->Cell(0, 5.5, $this->txt($intitule), 0, 1);

        $this->SetFont('Helvetica', '', 8.5);
        $y = $this->GetY();
        $x = 15;
        foreach ($echelle as $valeur => $libelle) {
            $largeur = $this->GetStringWidth($this->txt($libelle)) + 10;
            $this->caseACocher($x, $y + 1, (string)$valeur === (string)$reponse);
            $this->SetXY($x + 5, $y);
            $this->Cell($largeur - 5, 5.5, $this->txt($libelle), 0, 0);
            $x += $largeur;
        }
        $this->SetXY(15, $y + 5.5);

        // Ancien questionnaire : la réponse enregistrée peut ne correspondre à
        // aucune case actuelle. On la reporte plutôt que de la perdre en silence.
        $reponse = trim((string)$reponse);
        if ($reponse !== '' && !array_key_exists($reponse, $echelle)) {
            $this->SetFont('Helvetica', 'I', 7.5);
            $this->Cell(0, 4, $this->txt('Réponse enregistrée : ' . $reponse), 0, 1);
        }
        $this->Ln(1.5);
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

    // Zone de texte libre, encadrée comme sur le questionnaire papier
    public function zoneTexte(string $intitule, ?string $contenu, float $hauteurMin = 16): void
    {
        $this->SetFont('Helvetica', 'B', 8.5);
        $this->Cell(0, 5.5, $this->txt($intitule), 0, 1);

        $contenu = trim((string)$contenu);
        $this->SetFont('Helvetica', '', 9);

        if ($contenu === '') {
            $this->Rect(15, $this->GetY(), self::L_UTILE, $hauteurMin);
            $this->Ln($hauteurMin + 2.5);
            return;
        }

        $texte   = $this->txt($contenu);
        $hLigne  = 4.4;
        $hauteur = max($hauteurMin, $this->nbLignes(self::L_UTILE, $texte) * $hLigne + 3);

        $y = $this->GetY();
        $this->Rect(15, $y, self::L_UTILE, $hauteur);
        $this->SetXY(16.5, $y + 1.5);
        $this->MultiCell(self::L_UTILE - 3, $hLigne, $texte, 0, 'L');
        $this->SetXY(15, $y + $hauteur);
        $this->Ln(2.5);
    }

    // Bandeau signalant un avis issu de l'ancien questionnaire, sans grilles de notes
    public function avertissement(string $texte): void
    {
        $this->SetFont('Helvetica', 'I', 8);
        $y = $this->GetY();
        $h = 8;
        $this->SetDrawColor(120);
        $this->Rect(15, $y, self::L_UTILE, $h);
        $this->SetDrawColor(0);
        $this->SetXY(17, $y + 1);
        $this->MultiCell(self::L_UTILE - 4, 3.2, $this->txt($texte), 0, 'L');
        $this->SetXY(15, $y + $h);
        $this->Ln(2);
    }
}

$pdf = new QuestionnairePdf('P', 'mm', 'A4');
$pdf->SetTitle('Questionnaires de satisfaction - ' . $jour);
$pdf->SetAuthor('Formation IA générative');
$pdf->SetMargins(15, 15, 15);
$pdf->SetAutoPageBreak(true, 18);
$pdf->journee = jour_en_toutes_lettres($jour);
$pdf->total   = count($avis);

foreach ($avis as $i => $a) {
    $pdf->numero = $i + 1;
    $pdf->AddPage();

    // Identité : prénom facultatif, heure de dépôt
    $prenom = trim((string)($a['prenom'] ?? ''));
    $pdf->SetFont('Helvetica', '', 9);
    $pdf->Cell(18, 6, $pdf->txt('Prénom :'), 0, 0);
    $pdf->SetFont('Helvetica', 'B', 9);
    $pdf->Cell(70, 6, $pdf->txt($prenom !== '' ? $prenom : 'Anonyme'), 'B', 0);
    $pdf->SetFont('Helvetica', '', 9);
    $pdf->Cell(22, 6, $pdf->txt('   Déposé à :'), 0, 0);
    $pdf->SetFont('Helvetica', 'B', 9);
    $pdf->Cell(0, 6, $pdf->txt(heure_locale($a['created_at'])), 'B', 1);
    $pdf->Ln(2);

    // Les avis migrés de l'ancien questionnaire n'ont aucune note chiffrée
    $sansNotes = true;
    foreach (array_keys(EXPORT_CRITERES) as $k) {
        if ((int)$a[$k] > 0) { $sansNotes = false; break; }
    }
    if ($sansNotes) {
        $pdf->avertissement(
            'Avis issu de l\'ancien questionnaire : les grilles de notation n\'y figuraient pas. '
            . 'Seuls le rythme, les commentaires et les deux dernières questions ont été renseignés.'
        );
    }

    $pdf->titreSection('Votre satisfaction');
    $pdf->enteteGrille(ECHELLE_SATISFACTION);
    foreach (EXPORT_CRITERES as $cle => $libelle) {
        $pdf->ligneGrille($libelle, ECHELLE_SATISFACTION, $a[$cle]);
    }

    $pdf->titreSection('À l\'issue de cette formation, je me sens capable de');
    $pdf->enteteGrille(ECHELLE_CAPACITE);
    foreach (EXPORT_CAPACITES as $cle => $libelle) {
        $pdf->ligneGrille($libelle, ECHELLE_CAPACITE, $a[$cle]);
    }

    $pdf->titreSection('La journée');
    $pdf->questionEnLigne('Le rythme de la journée ?', ECHELLE_RYTHME, $a['rythme']);
    $pdf->zoneTexte('Ce que vous avez le plus apprécié', $a['apprecie']);
    $pdf->zoneTexte('Ce qui pourrait être amélioré', $a['ameliore']);
    $pdf->questionEnLigne(
        'Vous recommanderiez cette formation à un autre demandeur d\'emploi ?',
        ECHELLE_RECOMMANDE,
        $a['recommande']
    );
    $pdf->questionEnLigne(
        'Souhaitez-vous suivre d\'autres formations sur l\'intelligence artificielle ?',
        ECHELLE_INTERESSE,
        $a['interesse_formations']
    );
    $pdf->zoneTexte('Si oui, lesquelles ?', $a['autres_formations_lesquelles'], 12);
}

$pdf->Output('D', 'questionnaires-satisfaction-' . $jour . '.pdf');
