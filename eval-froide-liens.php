<?php
require __DIR__ . '/horodatage.php';   // -> config.php + helpers de date

// Accès réservé à l'animateur
$cle = $_REQUEST['cle'] ?? '';
if (!hash_equals(CLE_ANIMATEUR, (string)$cle)) {
    http_response_code(403);
    exit('Accès réservé. Ajoutez ?cle=... à l\'adresse.');
}

$moi = 'eval-froide-liens.php?cle=' . rawurlencode(CLE_ANIMATEUR);

// URL absolue de base pour composer les liens participants (copiés-collés dans les mails)
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$base   = $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost')
        . rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? ''), '/\\') . '/evaluation-froide.php?t=';

$erreur = '';
$table_ok = true;

// POST puis redirection : pas de rejeu au rafraîchissement
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    try {
        if ($action === 'generer') {
            $libelle = trim((string)($_POST['libelle'] ?? ''));
            $libelle = mb_substr($libelle, 0, 120);
            $nb = (int)($_POST['nb'] ?? 1);
            $nb = max(1, min(50, $nb));   // borne raisonnable

            $st = db()->prepare('INSERT INTO eval_froide_tokens (token, libelle) VALUES (?, ?)');
            for ($i = 0; $i < $nb; $i++) {
                $lib = $libelle;
                if ($libelle !== '' && $nb > 1) {
                    $lib = $libelle . ' #' . ($i + 1);
                }
                $st->execute([bin2hex(random_bytes(16)), $lib !== '' ? $lib : null]);
            }

        } elseif ($action === 'supprimer') {
            // On ne supprime qu'un lien non utilisé : un lien répondu porte une réponse rattachée.
            $st = db()->prepare('DELETE FROM eval_froide_tokens WHERE id = ? AND used_at IS NULL');
            $st->execute([(int)($_POST['id'] ?? 0)]);
        }
    } catch (PDOException $e) {
        // Table absente : on retombera sur le message d'aide plus bas.
    }
    header('Location: ' . $moi, true, 303);
    exit;
}

// Chargement des liens
$tokens = [];
try {
    $tokens = db()->query(
        'SELECT id, token, libelle, created_at, used_at FROM eval_froide_tokens ORDER BY id DESC'
    )->fetchAll();
} catch (PDOException $e) {
    $table_ok = false;
}

$repondus = array_filter($tokens, fn ($t) => $t['used_at'] !== null);
$en_attente = count($tokens) - count($repondus);
?><!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="icon" href="assets/favicon-couleur.png">
<meta name="robots" content="noindex">
<title>Liens — évaluation à froid</title>
<link rel="stylesheet" href="style.css">
<style>
.gen { display:flex; flex-wrap:wrap; gap:10px; align-items:flex-end; }
.gen .field { flex:1; min-width:180px; padding:12px 14px; font-size:1.05rem;
  font-family:inherit; border:2px solid #C2C3C7; border-radius:0; }
.gen .nb { flex:0 0 90px; min-width:70px; }
.gen label { display:block; font-weight:600; color:var(--navy); margin-bottom:6px; font-size:.95rem; }
table.liens { width:100%; border-collapse:collapse; margin-top:8px; }
table.liens th, table.liens td { text-align:left; padding:10px 8px; border-bottom:1px solid #C2C3C7; vertical-align:top; font-size:.95rem; }
table.liens th { color:var(--navy); }
.lien-url { font-family:monospace; font-size:.82rem; word-break:break-all; color:var(--muted); }
.badge { display:inline-block; padding:2px 10px; border-radius:999px; font-size:.82rem; font-weight:600; white-space:nowrap; }
.badge-attente { background:var(--cream); color:var(--navy); }
.badge-repondu { background:#EDF1DE; color:#4E5A1C; }
.mini { padding:6px 12px; font-size:.9rem; }
.copied { color:#4E5A1C; font-weight:600; font-size:.85rem; margin-left:6px; opacity:0; transition:opacity .2s; }
.copied.show { opacity:1; }
.muted { color:var(--muted); }
form.inline { display:inline; }
</style>
</head>
<body>

<header class="site-head">
  <h1>Évaluation à froid — liens</h1>
  <p>Un lien par participant, à usage unique</p>
</header>

<main class="wrap">

<?php if ($table_ok): ?>
  <div style="display:flex;flex-wrap:wrap;gap:8px;margin-bottom:16px">
    <a class="btn btn-ghost" href="eval-froide-resultats.php?cle=<?= rawurlencode(CLE_ANIMATEUR) ?>">Voir les réponses</a>
    <?php if ($en_attente > 0): ?>
    <a class="btn btn-primary" href="export-eval-froide-liens.php?cle=<?= rawurlencode(CLE_ANIMATEUR) ?>">
      Exporter les liens en attente (Excel) · <?= (int)$en_attente ?>
    </a>
    <?php endif; ?>
  </div>
<?php endif; ?>

<?php if (!$table_ok): ?>
  <section class="card">
    <h2>Migration à jouer</h2>
    <p>Les tables de l'évaluation à froid n'existent pas encore. Jouez une fois,
       avec un compte administrateur&nbsp;:</p>
    <pre>mysql --default-character-set=utf8mb4 -u root -p formation_ia &lt; migration-eval-froide.sql</pre>
  </section>
<?php else: ?>

  <section class="card">
    <h2>Générer des liens</h2>
    <p class="lead">Un libellé (nom, email…) aide à retrouver qui a répondu. Facultatif.</p>
    <form method="post" action="<?= e($moi) ?>" class="gen">
      <input type="hidden" name="action" value="generer">
      <div style="flex:1;min-width:180px">
        <label for="libelle">Libellé</label>
        <input class="field" id="libelle" name="libelle" type="text" maxlength="120" placeholder="Ex : Sam G. / sam@exemple.fr">
      </div>
      <div class="nb">
        <label for="nb">Nombre</label>
        <input class="field nb" id="nb" name="nb" type="number" value="1" min="1" max="50">
      </div>
      <button class="btn btn-primary">Générer</button>
    </form>
  </section>

  <section class="card">
    <h2>Les liens (<?= count($tokens) ?>)
      <span class="muted" style="font-weight:400;font-size:1rem">· <?= count($repondus) ?> répondu(s)</span>
    </h2>

    <?php if (!$tokens): ?>
      <p class="lead">Aucun lien pour le moment. Générez-en ci-dessus.</p>
    <?php else: ?>
    <table class="liens">
      <thead>
        <tr><th>Libellé</th><th>Lien</th><th>Statut</th><th></th></tr>
      </thead>
      <tbody>
      <?php foreach ($tokens as $t): $url = $base . $t['token']; ?>
        <tr>
          <td><?= $t['libelle'] !== null ? e($t['libelle']) : '<span class="muted">—</span>' ?><br>
              <span class="muted" style="font-size:.82rem">créé le <?= e(moment_local($t['created_at'])->format('d/m/Y H:i')) ?></span>
          </td>
          <td>
            <span class="lien-url" id="url-<?= (int)$t['id'] ?>"><?= e($url) ?></span>
            <button type="button" class="btn btn-ghost mini" data-copy="<?= (int)$t['id'] ?>">Copier</button>
            <span class="copied" id="copied-<?= (int)$t['id'] ?>">Copié</span>
          </td>
          <td>
            <?php if ($t['used_at'] !== null): ?>
              <span class="badge badge-repondu">Répondu</span><br>
              <span class="muted" style="font-size:.82rem"><?= e(moment_local($t['used_at'])->format('d/m/Y H:i')) ?></span>
            <?php else: ?>
              <span class="badge badge-attente">En attente</span>
            <?php endif; ?>
          </td>
          <td>
            <?php if ($t['used_at'] === null): ?>
            <form method="post" action="<?= e($moi) ?>" class="inline"
                  onsubmit="return confirm('Supprimer ce lien non utilisé ?');">
              <input type="hidden" name="action" value="supprimer">
              <input type="hidden" name="id" value="<?= (int)$t['id'] ?>">
              <button class="btn btn-ghost mini">Supprimer</button>
            </form>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    <?php endif; ?>
  </section>

<?php endif; ?>

</main>

<script>
document.querySelectorAll('[data-copy]').forEach(function (btn) {
  btn.addEventListener('click', function () {
    var id = btn.getAttribute('data-copy');
    var text = document.getElementById('url-' + id).textContent;
    var note = document.getElementById('copied-' + id);
    function done() { note.classList.add('show'); setTimeout(function () { note.classList.remove('show'); }, 2000); }
    if (navigator.clipboard && navigator.clipboard.writeText) {
      navigator.clipboard.writeText(text).then(done).catch(fallback);
    } else { fallback(); }
    function fallback() {
      var ta = document.createElement('textarea');
      ta.value = text; ta.style.position = 'fixed'; ta.style.opacity = '0';
      document.body.appendChild(ta); ta.select();
      try { document.execCommand('copy'); done(); } catch (e) {}
      document.body.removeChild(ta);
    }
  });
});
</script>

</body>
</html>
