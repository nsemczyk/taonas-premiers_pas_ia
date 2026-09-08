<?php
require __DIR__ . '/horodatage.php';   // -> config.php + helpers de date

// Accès réservé à l'animateur
if (!hash_equals(CLE_ANIMATEUR, (string)($_GET['cle'] ?? ''))) {
    http_response_code(403);
    exit('Accès réservé. Ajoutez ?cle=... à l\'adresse.');
}

// URL absolue de base pour composer les liens participants
// (logique identique à eval-froide-liens.php)
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$base   = $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost')
        . rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? ''), '/\\') . '/evaluation-froide.php?t=';

// Liens « en attente » uniquement : non encore utilisés (used_at IS NULL).
try {
    $liens = db()->query(
        'SELECT token, libelle, created_at FROM eval_froide_tokens
         WHERE used_at IS NULL ORDER BY id'
    )->fetchAll();
} catch (PDOException $e) {
    http_response_code(500);
    exit('Les tables de l\'évaluation à froid ne sont pas disponibles.');
}

if (!$liens) {
    http_response_code(404);
    exit('Aucun lien en attente à exporter.');
}

// ---------------------------------------------------------------------------
// Construction d'un classeur .xlsx minimal (OOXML), sans bibliothèque tierce.
// Un .xlsx est une archive ZIP contenant quelques fichiers XML.
// ---------------------------------------------------------------------------

// Échappe une valeur pour du contenu XML (et retire les caractères de contrôle
// interdits par la spécification XML, qui feraient rejeter le fichier).
function xlsx_esc(string $s): string
{
    $s = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/u', '', $s) ?? '';
    return htmlspecialchars($s, ENT_QUOTES | ENT_XML1, 'UTF-8');
}

// Une cellule texte, en chaîne « inline » (pas de table de chaînes partagées).
// $style : index dans <cellXfs> (0 = normal, 1 = gras pour l'en-tête).
function xlsx_cell(string $ref, string $valeur, int $style = 0): string
{
    $s = $style ? ' s="' . $style . '"' : '';
    return '<c r="' . $ref . '" t="inlineStr"' . $s . '>'
        . '<is><t xml:space="preserve">' . xlsx_esc($valeur) . '</t></is></c>';
}

$colonnes = ['A', 'B', 'C'];
$entetes  = ['Libellé', 'Lien à envoyer', 'Lien créé le'];

// Ligne d'en-tête (style 1 = gras)
$rows = '<row r="1">';
foreach ($entetes as $i => $titre) {
    $rows .= xlsx_cell($colonnes[$i] . '1', $titre, 1);
}
$rows .= '</row>';

// Lignes de données, dans l'ordre de création
$ligne = 1;
foreach ($liens as $l) {
    $ligne++;
    $rows .= '<row r="' . $ligne . '">'
        . xlsx_cell('A' . $ligne, (string)($l['libelle'] ?? ''))
        . xlsx_cell('B' . $ligne, $base . (string)$l['token'])
        . xlsx_cell('C' . $ligne, moment_local($l['created_at'])->format('d/m/Y H:i'))
        . '</row>';
}

$sheet = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
    . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
    . '<cols>'
    . '<col min="1" max="1" width="34" customWidth="1"/>'
    . '<col min="2" max="2" width="72" customWidth="1"/>'
    . '<col min="3" max="3" width="18" customWidth="1"/>'
    . '</cols>'
    . '<sheetData>' . $rows . '</sheetData>'
    . '</worksheet>';

$styles = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
    . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
    . '<fonts count="2">'
    . '<font><sz val="11"/><name val="Calibri"/></font>'
    . '<font><b/><sz val="11"/><name val="Calibri"/></font>'
    . '</fonts>'
    . '<fills count="2">'
    . '<fill><patternFill patternType="none"/></fill>'
    . '<fill><patternFill patternType="gray125"/></fill>'
    . '</fills>'
    . '<borders count="1"><border/></borders>'
    . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
    . '<cellXfs count="2">'
    . '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
    . '<xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1"/>'
    . '</cellXfs>'
    . '</styleSheet>';

$workbook = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
    . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" '
    . 'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
    . '<sheets><sheet name="Liens en attente" sheetId="1" r:id="rId1"/></sheets>'
    . '</workbook>';

$wbRels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
    . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
    . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
    . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
    . '</Relationships>';

$contentTypes = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
    . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
    . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
    . '<Default Extension="xml" ContentType="application/xml"/>'
    . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
    . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
    . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
    . '</Types>';

$rootRels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
    . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
    . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
    . '</Relationships>';

// --- Assemblage de l'archive -----------------------------------------------
$tmp = tempnam(sys_get_temp_dir(), 'xlsx');
$zip = new ZipArchive();
if ($tmp === false || $zip->open($tmp, ZipArchive::OVERWRITE) !== true) {
    http_response_code(500);
    exit('Impossible de préparer le fichier Excel.');
}
$zip->addFromString('[Content_Types].xml', $contentTypes);
$zip->addFromString('_rels/.rels', $rootRels);
$zip->addFromString('xl/workbook.xml', $workbook);
$zip->addFromString('xl/_rels/workbook.xml.rels', $wbRels);
$zip->addFromString('xl/styles.xml', $styles);
$zip->addFromString('xl/worksheets/sheet1.xml', $sheet);
$zip->close();

$contenu = file_get_contents($tmp);
@unlink($tmp);

if ($contenu === false) {
    http_response_code(500);
    exit('Impossible de lire le fichier Excel généré.');
}

$nomFichier = 'liens-eval-froide-en-attente-' . date('Y-m-d') . '.xlsx';

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $nomFichier . '"');
header('Content-Length: ' . strlen($contenu));
header('Cache-Control: no-store');
echo $contenu;
