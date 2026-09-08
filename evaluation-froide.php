<?php
require __DIR__ . '/config.php';
require __DIR__ . '/eval-froide-questions.php';

// ---------------------------------------------------------------------------
// Accès par lien unique : ?t=TOKEN. Aucune entrée depuis l'accueil.
// ---------------------------------------------------------------------------
$token = (string)($_GET['t'] ?? '');
$token = preg_match('/^[a-f0-9]{32}$/', $token) ? $token : '';

$etat   = 'form';   // form | invalide | deja | merci | indispo
$erreur = '';
$ligne  = null;     // ligne du token

if ($token === '') {
    $etat = 'invalide';
} else {
    try {
        $st = db()->prepare('SELECT id, used_at FROM eval_froide_tokens WHERE token = ?');
        $st->execute([$token]);
        $ligne = $st->fetch();
    } catch (PDOException $e) {
        // Table absente : migration-eval-froide.sql pas encore jouée.
        $etat = 'indispo';
    }
    if ($etat !== 'indispo') {
        if (!$ligne) {
            $etat = 'invalide';
        } elseif ($ligne['used_at'] !== null) {
            $etat = 'deja';
        }
    }
}

// ---------------------------------------------------------------------------
// Enregistrement
// ---------------------------------------------------------------------------
if ($etat === 'form' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'nom_prenom'     => ef_texte_clean($_POST['nom_prenom'] ?? '', 120) ?: null,
        'q2_usages'      => ef_multi_clean($EF_MULTI['q2_usages'], $_POST['q2_usages'] ?? null) ?: null,
        'q2_autre'       => ef_texte_clean($_POST['q2_autre'] ?? '', 255) ?: null,
        'q3_autonomie'   => ef_simple_clean($EF_SIMPLE['q3_autonomie'], $_POST['q3_autonomie'] ?? null) ?: null,
        'q4_prompt'      => ef_simple_clean($EF_SIMPLE['q4_prompt'], $_POST['q4_prompt'] ?? null) ?: null,
        'q5_candidature' => ef_simple_clean($EF_SIMPLE['q5_candidature'], $_POST['q5_candidature'] ?? null) ?: null,
        'q5_usages'      => ef_multi_clean($EF_MULTI['q5_usages'], $_POST['q5_usages'] ?? null) ?: null,
        'q5_autre'       => ef_texte_clean($_POST['q5_autre'] ?? '', 255) ?: null,
        'q6_changement'  => ef_simple_clean($EF_SIMPLE['q6_changement'], $_POST['q6_changement'] ?? null) ?: null,
        'q7_entretiens'  => ef_simple_clean($EF_SIMPLE['q7_entretiens'], $_POST['q7_entretiens'] ?? null) ?: null,
        'q7_ia_prepa'    => ef_simple_clean($EF_SIMPLE['q7_ia_prepa'], $_POST['q7_ia_prepa'] ?? null) ?: null,
        'q8_utile'       => ef_simple_clean($EF_SIMPLE['q8_utile'], $_POST['q8_utile'] ?? null) ?: null,
        'q9_autonomie'   => ef_simple_clean($EF_SIMPLE['q9_autonomie'], $_POST['q9_autonomie'] ?? null) ?: null,
        'q10_apprise'    => ef_texte_clean($_POST['q10_apprise'] ?? '', 2000) ?: null,
        'q11_freins'     => ef_multi_clean($EF_MULTI['q11_freins'], $_POST['q11_freins'] ?? null) ?: null,
        'q11_autre'      => ef_texte_clean($_POST['q11_autre'] ?? '', 255) ?: null,
        'q12_manque'     => ef_texte_clean($_POST['q12_manque'] ?? '', 2000) ?: null,
        'q13_poursuivre' => ef_simple_clean($EF_SIMPLE['q13_poursuivre'], $_POST['q13_poursuivre'] ?? null) ?: null,
        'q13_sujets'     => ef_multi_clean($EF_MULTI['q13_sujets'], $_POST['q13_sujets'] ?? null) ?: null,
        'q13_autre'      => ef_texte_clean($_POST['q13_autre'] ?? '', 255) ?: null,
        'q14_situation'  => ef_simple_clean($EF_SIMPLE['q14_situation'], $_POST['q14_situation'] ?? null) ?: null,
        'q14_autre'      => ef_texte_clean($_POST['q14_autre'] ?? '', 255) ?: null,
    ];

    try {
        $pdo = db();
        $pdo->beginTransaction();

        // Consommation atomique du lien : une seule ligne peut passer used_at de NULL à maintenant.
        $maj = $pdo->prepare('UPDATE eval_froide_tokens SET used_at = NOW() WHERE id = ? AND used_at IS NULL');
        $maj->execute([$ligne['id']]);

        if ($maj->rowCount() === 0) {
            // Course : le lien vient d'être utilisé (double soumission / rechargement).
            $pdo->rollBack();
            $etat = 'deja';
        } else {
            $cols = array_merge(['token_id' => $ligne['id']], $data);
            $noms = implode(', ', array_map(fn ($c) => "`$c`", array_keys($cols)));
            $ph   = implode(', ', array_fill(0, count($cols), '?'));
            $ins  = $pdo->prepare("INSERT INTO eval_froide ($noms) VALUES ($ph)");
            $ins->execute(array_values($cols));

            $pdo->commit();
            $etat = 'merci';
        }
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $erreur = "L'enregistrement a échoué. Réessayez ; si le problème persiste, prévenez le formateur.";
    }
}

// --- Rendu des groupes de réponses -----------------------------------------
// Choix unique : liste déroulante. Aucune réponse n'est obligatoire.
function ef_select(string $name, array $options, string $current = ''): string
{
    $h = '<select class="field select" name="' . e($name) . '" id="' . e($name) . '">';
    $h .= '<option value="">— Choisir une réponse —</option>';
    foreach ($options as $code => $label) {
        $sel = ($current !== '' && (string)$code === $current) ? ' selected' : '';
        $h .= '<option value="' . e($code) . '"' . $sel . '>' . e($label) . '</option>';
    }
    return $h . '</select>';
}

// Choix multiple (cases à cocher).
function ef_checks(string $name, array $options): string
{
    $h = '<div class="choices">';
    foreach ($options as $code => $label) {
        $id = $name . '-' . $code;
        $h .= '<label for="' . $id . '">'
            . '<input type="checkbox" name="' . $name . '[]" id="' . $id . '" value="' . e($code) . '">'
            . e($label) . '</label>';
    }
    return $h . '</div>';
}
?><!DOCTYPE html>
<html lang="fr">
<head>
<script>document.documentElement.className += ' js';</script>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="icon" href="assets/favicon-couleur.png">
<meta name="robots" content="noindex">
<title>Évaluation à froid — Premiers pas avec l'IA générative</title>
<link rel="stylesheet" href="style.css">
<style>
fieldset { border:none; margin:0 0 22px; padding:0; }
legend { font-weight:600; color:var(--navy); font-size:1.05rem; margin-bottom:2px; padding:0; }
.section-num { color:var(--gold); font-weight:700; margin-right:6px; }
.choices label {
  display:block; padding:12px 14px; margin-top:8px;
  border:2px solid #C2C3C7; border-radius:0; background:#fff; cursor:pointer;
}
.choices input { margin-right:10px; transform:scale(1.3); }
.choices label:has(input:checked) { border-color:var(--gold); background:var(--cream); }
.sous-question { margin:10px 0 0 0; padding:14px 16px; border-left:4px solid #C2C3C7; background:#F4FAFD; border-radius:0; }
.sous-question legend { font-size:.98rem; }
input.field, textarea.field, select.field { width:100%; padding:12px 14px; font-size:1.05rem;
  font-family:inherit; border:2px solid #C2C3C7; border-radius:0; }
textarea.field { min-height:90px; }
input.field:focus, textarea.field:focus, select.field:focus { border-color:var(--navy); outline:none; }
select.field { background:#fff; cursor:pointer; -webkit-appearance:none; -moz-appearance:none; appearance:none;
  background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 12 8'%3E%3Cpath fill='%23103B5A' d='M1 1l5 5 5-5'/%3E%3C/svg%3E");
  background-repeat:no-repeat; background-position:right 14px center; background-size:12px; padding-right:40px; }
.autre-inline { margin-top:8px; }
/* Sous-questions conditionnelles : visibles seulement si JS est actif et
   la réponse déclenchante est sélectionnée. Sans JS, tout reste affiché. */
.js .cond { display:none; }
.js .cond.is-visible { display:block; }
.err { background:var(--error-soft); border-left:6px solid var(--error); padding:12px 14px;
  border-radius:0; margin-bottom:16px; font-weight:600; }
.intro { color:var(--muted); }
.intro p { margin:0 0 10px; }
</style>
</head>
<body>

<header class="site-head">
  <h1>Évaluation à froid</h1>
  <p>Premiers pas avec l'IA générative · suivi après formation</p>
</header>

<main class="wrap">

<?php if ($etat === 'invalide'): ?>
  <section class="card" style="text-align:center">
    <h2>Lien invalide</h2>
    <p>Ce lien n'est pas reconnu. Utilisez le lien personnel reçu par message.
       En cas de doute, contactez le formateur.</p>
  </section>

<?php elseif ($etat === 'indispo'): ?>
  <section class="card" style="text-align:center">
    <h2>Questionnaire indisponible</h2>
    <p>Le questionnaire n'est pas encore activé. Merci de réessayer plus tard.</p>
  </section>

<?php elseif ($etat === 'deja'): ?>
  <section class="card" style="text-align:center">
    <h2>Vous avez déjà répondu</h2>
    <p style="font-size:2.5rem;margin:8px 0">✅</p>
    <p>Ce questionnaire a déjà été rempli avec ce lien. Merci pour votre retour !</p>
  </section>

<?php elseif ($etat === 'merci'): ?>
  <section class="card" style="text-align:center">
    <h2>Merci pour votre retour !</h2>
    <p style="font-size:2.5rem;margin:8px 0">🌱</p>
    <p>Vos réponses nous permettent de mesurer l'utilité de la formation dans la
       durée, d'améliorer son contenu et d'identifier les besoins d'accompagnement.</p>
  </section>

<?php else: ?>

  <section class="card intro">
    <p>Bonjour,</p>
    <p>Vous avez participé il y a quelques semaines à notre formation consacrée à
       l'utilisation de l'intelligence artificielle dans la recherche d'emploi.</p>
    <p>Ce questionnaire très court a pour objectif de savoir ce que vous avez
       réellement utilisé depuis la formation, ce qu'elle vous a apporté et les
       éventuels besoins que vous rencontrez aujourd'hui.</p>
    <p>Il n'y a pas de bonne ou de mauvaise réponse. Vos réponses nous permettront
       également d'améliorer les prochaines formations.</p>
    <p><strong>Temps de réponse : environ 3 minutes.</strong></p>
  </section>

  <form method="post" class="card" action="evaluation-froide.php?t=<?= e($token) ?>">

    <?php if ($erreur): ?><div class="err"><?= e($erreur) ?></div><?php endif; ?>

    <fieldset>
      <legend>Nom / Prénom <span style="font-weight:400;color:var(--muted)">(facultatif)</span></legend>
      <input class="field" name="nom_prenom" type="text" maxlength="120" value="<?= e($_POST['nom_prenom'] ?? '') ?>">
    </fieldset>

    <fieldset>
      <legend><span class="section-num">2.</span>Depuis la formation, pour quoi avez-vous utilisé l'IA ? <span style="font-weight:400;color:var(--muted)">(plusieurs réponses possibles)</span></legend>
      <?= ef_checks('q2_usages', $EF_MULTI['q2_usages']) ?>
      <input class="field autre-inline" name="q2_autre" type="text" maxlength="255" placeholder="Autre usage (facultatif)">
    </fieldset>

    <fieldset>
      <legend><span class="section-num">3.</span>Aujourd'hui, vous sentez-vous capable d'utiliser seul(e) une IA générative ?</legend>
      <?= ef_select('q3_autonomie', $EF_SIMPLE['q3_autonomie'], $_POST['q3_autonomie'] ?? '') ?>
    </fieldset>

    <fieldset>
      <legend><span class="section-num">4.</span>Depuis la formation, vous sentez-vous plus à l'aise pour rédiger une consigne efficace à une IA (« prompt ») ?</legend>
      <?= ef_select('q4_prompt', $EF_SIMPLE['q4_prompt'], $_POST['q4_prompt'] ?? '') ?>
    </fieldset>

    <fieldset>
      <legend><span class="section-num">5.</span>Depuis la formation, avez-vous utilisé l'IA pour une candidature réelle ?</legend>
      <?= ef_select('q5_candidature', $EF_SIMPLE['q5_candidature'], $_POST['q5_candidature'] ?? '') ?>
      <div class="sous-question cond" data-depends="q5_candidature" data-show-values="plusieurs,une">
        <fieldset style="margin:0">
          <legend>Si oui, pour quoi ? <span style="font-weight:400;color:var(--muted)">(plusieurs réponses possibles)</span></legend>
          <?= ef_checks('q5_usages', $EF_MULTI['q5_usages']) ?>
          <input class="field autre-inline" name="q5_autre" type="text" maxlength="255" placeholder="Autre (facultatif)">
        </fieldset>
      </div>
    </fieldset>

    <fieldset>
      <legend><span class="section-num">6.</span>Depuis la formation, avez-vous changé votre manière de rechercher un emploi grâce à ce que vous avez appris ?</legend>
      <?= ef_select('q6_changement', $EF_SIMPLE['q6_changement'], $_POST['q6_changement'] ?? '') ?>
    </fieldset>

    <fieldset>
      <legend><span class="section-num">7.</span>Depuis la formation, avez-vous obtenu un ou plusieurs entretiens d'embauche ?</legend>
      <?= ef_select('q7_entretiens', $EF_SIMPLE['q7_entretiens'], $_POST['q7_entretiens'] ?? '') ?>
      <div class="sous-question cond" data-depends="q7_entretiens" data-show-values="oui">
        <fieldset style="margin:0">
          <legend>Si oui, avez-vous utilisé l'IA pour préparer au moins un de ces entretiens ?</legend>
          <?= ef_select('q7_ia_prepa', $EF_SIMPLE['q7_ia_prepa'], $_POST['q7_ia_prepa'] ?? '') ?>
        </fieldset>
      </div>
    </fieldset>

    <fieldset>
      <legend><span class="section-num">8.</span>Avec le recul, cette formation vous est-elle utile dans votre recherche d'emploi ?</legend>
      <?= ef_select('q8_utile', $EF_SIMPLE['q8_utile'], $_POST['q8_utile'] ?? '') ?>
    </fieldset>

    <fieldset>
      <legend><span class="section-num">9.</span>Aujourd'hui, l'utilisation de l'IA vous permet-elle de réaliser certaines démarches avec davantage d'autonomie ?</legend>
      <?= ef_select('q9_autonomie', $EF_SIMPLE['q9_autonomie'], $_POST['q9_autonomie'] ?? '') ?>
    </fieldset>

    <fieldset>
      <legend><span class="section-num">10.</span>Quelle chose apprise pendant la formation utilisez-vous le plus aujourd'hui ?</legend>
      <textarea class="field" name="q10_apprise" maxlength="2000" placeholder="Réponse libre"></textarea>
    </fieldset>

    <fieldset>
      <legend><span class="section-num">11.</span>Qu'est-ce qui vous empêche encore d'utiliser davantage l'IA aujourd'hui ? <span style="font-weight:400;color:var(--muted)">(plusieurs réponses possibles)</span></legend>
      <?= ef_checks('q11_freins', $EF_MULTI['q11_freins']) ?>
      <input class="field autre-inline" name="q11_autre" type="text" maxlength="255" placeholder="Autre (facultatif)">
    </fieldset>

    <fieldset>
      <legend><span class="section-num">12.</span>Avec le recul, qu'auriez-vous aimé apprendre ou pratiquer davantage pendant la formation ?</legend>
      <textarea class="field" name="q12_manque" maxlength="2000" placeholder="Réponse libre"></textarea>
    </fieldset>

    <fieldset>
      <legend><span class="section-num">13.</span>Souhaiteriez-vous poursuivre votre apprentissage de l'intelligence artificielle ?</legend>
      <?= ef_select('q13_poursuivre', $EF_SIMPLE['q13_poursuivre'], $_POST['q13_poursuivre'] ?? '') ?>
      <div class="sous-question cond" data-depends="q13_poursuivre" data-show-values="oui,peut_etre">
        <fieldset style="margin:0">
          <legend>Si oui ou peut-être, quels sujets vous intéresseraient ? <span style="font-weight:400;color:var(--muted)">(plusieurs réponses possibles)</span></legend>
          <?= ef_checks('q13_sujets', $EF_MULTI['q13_sujets']) ?>
          <input class="field autre-inline" name="q13_autre" type="text" maxlength="255" placeholder="Autre (facultatif)">
        </fieldset>
      </div>
    </fieldset>

    <fieldset>
      <legend><span class="section-num">14.</span>Depuis la formation, votre situation professionnelle a-t-elle évolué ?</legend>
      <?= ef_select('q14_situation', $EF_SIMPLE['q14_situation'], $_POST['q14_situation'] ?? '') ?>
      <input class="field autre-inline" name="q14_autre" type="text" maxlength="255" placeholder="Autre (facultatif)">
    </fieldset>

    <button class="btn btn-primary">Envoyer mes réponses</button>
    <p class="rgpd">Le champ Nom / Prénom est facultatif : ne le renseignez que si vous
       acceptez d'être identifiable. Vos réponses servent à mesurer l'utilité de la
       formation et à améliorer les prochaines sessions. Suppression sur simple demande
       auprès du formateur.</p>
  </form>

<?php endif; ?>

</main>

<script>
// Sous-questions conditionnelles : chaque bloc .cond n'apparaît que si la
// réponse de son <select> déclencheur figure dans data-show-values.
// Quand il est masqué, ses champs sont réinitialisés pour ne pas transmettre
// de réponse à une question invisible.
(function () {
  document.querySelectorAll('.cond[data-depends]').forEach(function (bloc) {
    var control = document.querySelector('[name="' + bloc.getAttribute('data-depends') + '"]');
    if (!control) return;
    var declencheurs = (bloc.getAttribute('data-show-values') || '').split(',');

    function maj() {
      var afficher = declencheurs.indexOf(control.value) !== -1;
      bloc.classList.toggle('is-visible', afficher);
      if (!afficher) {
        bloc.querySelectorAll('select, input[type="text"], textarea').forEach(function (c) { c.value = ''; });
        bloc.querySelectorAll('input[type="checkbox"], input[type="radio"]').forEach(function (c) { c.checked = false; });
      }
    }

    control.addEventListener('change', maj);
    maj(); // état initial (utile après un rechargement où la réponse est déjà posée)
  });
})();
</script>
</body>
</html>
