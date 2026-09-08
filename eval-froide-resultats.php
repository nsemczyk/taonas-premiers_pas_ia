<?php
require __DIR__ . '/horodatage.php';            // -> config.php + dates
require __DIR__ . '/eval-froide-questions.php'; // définitions + helpers

// Accès réservé à l'animateur
if (!hash_equals(CLE_ANIMATEUR, (string)($_GET['cle'] ?? ''))) {
    http_response_code(403);
    exit('Accès réservé. Ajoutez ?cle=... à l\'adresse.');
}
$cle = rawurlencode(CLE_ANIMATEUR);

$table_ok = true;
$reponses = [];
try {
    $reponses = db()->query(
        'SELECT r.*, t.libelle FROM eval_froide r
         JOIN eval_froide_tokens t ON t.id = r.token_id
         ORDER BY r.created_at DESC'
    )->fetchAll();
} catch (PDOException $e) {
    $table_ok = false;
}

// --- Synthèse : distribution des choix -------------------------------------
// $synthese[field][code] = nombre. Calculée en PHP (volumes faibles).
$synthese = [];
foreach ($EF_QUESTIONS as $q) {
    if ($q['type'] === 'texte') {
        continue;
    }
    $synthese[$q['field']] = array_fill_keys(array_keys(${$q['type'] === 'simple' ? 'EF_SIMPLE' : 'EF_MULTI'}[$q['opt']]), 0);
    foreach ($reponses as $r) {
        if ($q['type'] === 'simple') {
            $code = trim((string)$r[$q['field']]);
            if ($code !== '' && isset($synthese[$q['field']][$code])) {
                $synthese[$q['field']][$code]++;
            }
        } else {
            foreach (explode(',', (string)$r[$q['field']]) as $code) {
                $code = trim($code);
                if ($code !== '' && isset($synthese[$q['field']][$code])) {
                    $synthese[$q['field']][$code]++;
                }
            }
        }
    }
}

// Rend la réponse d'une question (ou sous-question) pour une ligne donnée.
function ef_rendu_reponse(array $q, array $r): string
{
    global $EF_SIMPLE, $EF_MULTI;
    if ($q['type'] === 'texte') {
        $v = trim((string)$r[$q['field']]);
        return $v === '' ? '<span class="vide">—</span>' : '<span class="libre">' . nl2br(e($v)) . '</span>';
    }
    if ($q['type'] === 'simple') {
        $lib = ef_libelle_simple($EF_SIMPLE[$q['opt']], $r[$q['field']]);
        $out = $lib === '' ? '<span class="vide">—</span>' : '<span class="rep">' . e($lib) . '</span>';
    } else {
        $libs = ef_libelles_multi($EF_MULTI[$q['opt']], $r[$q['field']]);
        $out = $libs
            ? implode(' ', array_map(fn ($l) => '<span class="chip">' . e($l) . '</span>', $libs))
            : '<span class="vide">—</span>';
    }
    if (!empty($q['autre']) && trim((string)$r[$q['autre']]) !== '') {
        $out .= ' <span class="chip chip-autre">Autre : ' . e(trim((string)$r[$q['autre']])) . '</span>';
    }
    return $out;
}
?><!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="icon" href="assets/favicon-couleur.png">
<meta name="robots" content="noindex">
<title>Évaluation à froid — réponses</title>
<link rel="stylesheet" href="style.css">
<style>
.nav { display:flex; flex-wrap:wrap; gap:8px; margin-bottom:16px; }
.q { margin:16px 0 4px; font-weight:600; color:var(--navy); }
.q .num { color:var(--gold); font-weight:700; margin-right:6px; }
.sub-q { margin-left:14px; padding-left:12px; border-left:3px solid #C2C3C7; }
.rep { font-weight:600; }
.libre { white-space:pre-line; }
.vide { color:var(--muted); }
.chip { display:inline-block; padding:2px 10px; margin:2px 2px 2px 0; border-radius:999px;
  background:var(--cream); color:var(--navy); font-size:.9rem; }
.chip-autre { background:#D6ECF6; font-style:italic; }
.bars { margin:2px 0 12px; }
.bar-row { display:flex; align-items:center; gap:8px; margin:3px 0; font-size:.92rem; }
.bar-label { flex:0 0 46%; }
.bar-track { flex:1; height:14px; background:#E2F3FA; border-radius:0; overflow:hidden; }
.bar-fill { height:100%; background:var(--gold); }
.bar-n { flex:0 0 34px; text-align:right; color:var(--muted); }
.resp-head { display:flex; justify-content:space-between; align-items:baseline; gap:10px; flex-wrap:wrap; }
.resp-head .who { font-weight:700; color:var(--navy); font-size:1.1rem; }
.resp-head .when { color:var(--muted); font-size:.9rem; }
details.resp > summary { cursor:pointer; font-weight:700; color:var(--navy); }
</style>
</head>
<body>

<header class="site-head">
  <h1>Évaluation à froid</h1>
  <p>Réponses recueillies après la formation</p>
</header>

<main class="wrap">

<?php if (!$table_ok): ?>
  <section class="card">
    <h2>Migration à jouer</h2>
    <p>Les tables de l'évaluation à froid n'existent pas encore&nbsp;:</p>
    <pre>mysql --default-character-set=utf8mb4 -u root -p formation_ia &lt; migration-eval-froide.sql</pre>
  </section>
<?php else: ?>

  <div class="nav">
    <a class="btn btn-ghost" href="eval-froide-liens.php?cle=<?= $cle ?>">Gérer les liens</a>
    <?php if ($reponses): ?>
      <a class="btn btn-primary" href="export-eval-froide.php?cle=<?= $cle ?>">Exporter en PDF</a>
    <?php endif; ?>
  </div>

  <?php if (!$reponses): ?>
    <section class="card">
      <h2>Aucune réponse pour l'instant</h2>
      <p class="lead">Les réponses apparaîtront ici dès que des participants auront rempli le questionnaire.</p>
    </section>
  <?php else: ?>

    <section class="card">
      <h2>Synthèse <span style="font-weight:400;color:var(--muted);font-size:1rem">· <?= count($reponses) ?> réponse(s)</span></h2>
      <?php foreach ($EF_QUESTIONS as $q): if ($q['type'] === 'texte') continue; ?>
        <p class="q"><span class="num"><?= e($q['num']) ?>.</span><?= e($q['q']) ?>
          <?php if ($q['type'] === 'multi'): ?><span style="font-weight:400;color:var(--muted);font-size:.85rem"> (choix multiples)</span><?php endif; ?>
        </p>
        <div class="bars">
          <?php
            $opts = ${$q['type'] === 'simple' ? 'EF_SIMPLE' : 'EF_MULTI'}[$q['opt']];
            $max = max(1, max($synthese[$q['field']]));
            foreach ($opts as $code => $label):
              $n = $synthese[$q['field']][$code];
          ?>
          <div class="bar-row">
            <span class="bar-label"><?= e($label) ?></span>
            <span class="bar-track"><span class="bar-fill" style="width:<?= (int)round(100 * $n / $max) ?>%"></span></span>
            <span class="bar-n"><?= $n ?></span>
          </div>
          <?php endforeach; ?>
        </div>
      <?php endforeach; ?>
    </section>

    <?php foreach ($reponses as $i => $r): ?>
    <details class="card resp" <?= $i === 0 ? 'open' : '' ?>>
      <summary>
        <?php
          $qui = trim((string)($r['nom_prenom'] ?? ''));
          if ($qui === '') $qui = trim((string)($r['libelle'] ?? ''));
          if ($qui === '') $qui = 'Anonyme';
        ?>
        <?= e($qui) ?> — <?= e(moment_local($r['created_at'])->format('d/m/Y H:i')) ?>
      </summary>

      <?php
        $nom = trim((string)$r['nom_prenom']);
        $lib = trim((string)$r['libelle']);
      ?>
      <div class="resp-head" style="margin-top:10px">
        <span class="who"><?= $nom !== '' ? e($nom) : '<span class="vide">Nom non renseigné</span>' ?></span>
        <span class="when"><?php if ($lib !== ''): ?>lien : <?= e($lib) ?> · <?php endif; ?><?= e(moment_local($r['created_at'])->format('d/m/Y H:i')) ?></span>
      </div>

      <?php foreach ($EF_QUESTIONS as $q): ?>
        <p class="q"><span class="num"><?= e($q['num']) ?>.</span><?= e($q['q']) ?></p>
        <div><?= ef_rendu_reponse($q, $r) ?></div>
        <?php foreach ($q['sub'] ?? [] as $sub): ?>
          <div class="sub-q">
            <p class="q" style="font-size:.97rem"><?= e($sub['q']) ?></p>
            <div><?= ef_rendu_reponse($sub, $r) ?></div>
          </div>
        <?php endforeach; ?>
      <?php endforeach; ?>
    </details>
    <?php endforeach; ?>

  <?php endif; ?>
<?php endif; ?>

</main>
</body>
</html>
