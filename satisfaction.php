<?php
require __DIR__ . '/horodatage.php';

// Plus de code de session à saisir : la journée suffit à regrouper les avis
$session = aujourdhui_local();

// Section 1 du questionnaire papier : grille de satisfaction
$CRITERES = [
    'sat_accueil'   => 'Accueil et organisation',
    'sat_animation' => 'Qualité de l\'animation',
    'sat_clarte'    => 'Clarté des explications',
    'sat_contenu'   => 'Contenu de la formation',
    'sat_exercices' => 'Exercices proposés',
    'sat_supports'  => 'Supports remis',
    'sat_duree'     => 'Durée de la formation',
];

// Section 2 : "Je me sens capable..." (verbes à l'infinitif avec élision pour la légende)
$CAPACITES = [
    'capable_utiliser_ia'     => 'd\'utiliser une IA générative',
    'capable_prompt'          => 'de rédiger un prompt efficace',
    'capable_cv'               => 'd\'améliorer mon CV grâce à l\'IA',
    'capable_lettre'           => 'de rédiger une lettre de motivation avec l\'IA',
    'capable_recherche_emploi' => 'd\'utiliser l\'IA dans ma recherche d\'emploi',
];

// Toutes les échelles smileys : value => ['face' => emoji, 'label' => libellé accessible]
// (définies ici, avant le traitement du POST, car recommande/interesse_formations en ont besoin pour se valider)
$FACES_SATISFACTION = [
    1 => ['face' => '😞', 'label' => 'Pas satisfait'],
    2 => ['face' => '😕', 'label' => 'Peu satisfait'],
    3 => ['face' => '🙂', 'label' => 'Satisfait'],
    4 => ['face' => '😃', 'label' => 'Très satisfait'],
];

$FACES_CAPACITE = [
    1 => ['face' => '😞', 'label' => 'Non'],
    2 => ['face' => '😐', 'label' => 'Partiellement'],
    3 => ['face' => '😃', 'label' => 'Oui'],
];

$FACES_RECOMMANDE = [
    'NON'              => ['face' => '😞', 'label' => 'Non'],
    'NON-PAS-VRAIMENT' => ['face' => '😕', 'label' => 'Non, pas vraiment'],
    'OUI-PROBABLEMENT' => ['face' => '🙂', 'label' => 'Oui, probablement'],
    'OUI-SANS-HESITER' => ['face' => '😃', 'label' => 'Oui, sans hésiter'],
];

$FACES_INTERESSE = [
    'NON' => ['face' => '😞', 'label' => 'Non'],
    'OUI' => ['face' => '😃', 'label' => 'Oui'],
];

$erreur = '';
$merci  = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $notes = [];
    foreach ($CRITERES as $k => $label) {
        $v = (int)($_POST[$k] ?? 0);
        if ($v < 1 || $v > 4) { $erreur = 'Il manque une réponse (les smileys).'; }
        $notes[$k] = $v;
    }

    $capacites = [];
    foreach ($CAPACITES as $k => $label) {
        $v = (int)($_POST[$k] ?? 0);
        if ($v < 1 || $v > 3) { $erreur = 'Il manque une réponse (les smileys).'; }
        $capacites[$k] = $v;
    }

    $prenom = trim((string)($_POST['prenom'] ?? ''));
    if (strlen($prenom) > 100) $prenom = substr($prenom, 0, 100);

    $rythme     = in_array($_POST['rythme'] ?? '', ['LENT', 'BIEN', 'RAPIDE'], true) ? $_POST['rythme'] : '';
    $recommande = array_key_exists($_POST['recommande'] ?? '', $FACES_RECOMMANDE) ? $_POST['recommande'] : '';
    $interesse  = array_key_exists($_POST['interesse_formations'] ?? '', $FACES_INTERESSE) ? $_POST['interesse_formations'] : '';

    $apprecie = trim((string)($_POST['apprecie'] ?? ''));
    if (strlen($apprecie) > 2000) $apprecie = substr($apprecie, 0, 2000);

    $ameliore = trim((string)($_POST['ameliore'] ?? ''));
    if (strlen($ameliore) > 2000) $ameliore = substr($ameliore, 0, 2000);

    $lesquelles = trim((string)($_POST['autres_formations_lesquelles'] ?? ''));
    if (strlen($lesquelles) > 500) $lesquelles = substr($lesquelles, 0, 500);

    if (!$erreur) {
        if ($apprecie === '') $erreur = 'Merci d\'indiquer ce que vous avez apprécié.';
        elseif ($ameliore === '') $erreur = 'Merci d\'indiquer ce qui pourrait être amélioré.';
        elseif ($rythme === '' || $recommande === '' || $interesse === '') $erreur = 'Il manque une réponse.';
    }

    if (!$erreur) {
        $st = db()->prepare(
            'INSERT INTO satisfaction
             (session_code, prenom,
              sat_accueil, sat_animation, sat_clarte, sat_contenu, sat_exercices, sat_supports, sat_duree,
              capable_utiliser_ia, capable_prompt, capable_cv, capable_lettre, capable_recherche_emploi,
              rythme, apprecie, ameliore, recommande, interesse_formations, autres_formations_lesquelles)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $st->execute([
            $session,
            $prenom !== '' ? $prenom : null,
            $notes['sat_accueil'], $notes['sat_animation'], $notes['sat_clarte'], $notes['sat_contenu'],
            $notes['sat_exercices'], $notes['sat_supports'], $notes['sat_duree'],
            $capacites['capable_utiliser_ia'], $capacites['capable_prompt'], $capacites['capable_cv'],
            $capacites['capable_lettre'], $capacites['capable_recherche_emploi'],
            $rythme,
            $apprecie,
            $ameliore,
            $recommande,
            $interesse,
            $lesquelles !== '' ? $lesquelles : null,
        ]);
        $merci = true;
    }
}

// Échelle de smileys réutilisable. $options[$value] = ['face' => '😃', 'label' => 'Oui']
// Le libellé est exposé en aria-label : c'est l'équivalent accessible de "alt" pour un <label>/<input>
// (alt n'existe que sur <img>, qu'on n'utilise pas ici puisque les smileys sont des emojis texte).
function smileys(string $name, array $options): string {
    $h = '<div class="smileys" role="radiogroup">';
    foreach ($options as $value => $opt) {
        $id = $name . '-' . $value;
        $h .= '<input type="radio" name="' . $name . '" id="' . $id . '" value="' . e((string)$value) . '" required>'
            . '<label for="' . $id . '" aria-label="' . e($opt['label']) . '" title="' . e($opt['label']) . '">' . $opt['face'] . '</label>';
    }
    return $h . '</div>';
}
?><!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="icon" href="assets/favicon-couleur.png">
<meta name="robots" content="noindex">
<title>Votre avis sur la journée</title>
<link rel="stylesheet" href="style.css">
<style>
.smileys { display:flex; justify-content:space-between; gap:6px; margin:10px 0 4px; }
.smileys input { position:absolute; opacity:0; width:1px; height:1px; }
.smileys label {
  flex:1; text-align:center; font-size:2rem; line-height:1;
  padding:12px 0; border:2px solid #C2C3C7; border-radius:0;
  background:#fff; cursor:pointer; filter:grayscale(1); opacity:.6;
  transition: transform .06s ease;
}
.smileys label:active { transform:scale(.95); }
.smileys input:checked + label { border-color:var(--gold); background:var(--cream); filter:none; opacity:1; }
.smileys input:focus-visible + label { outline:3px solid var(--gold); outline-offset:2px; }
fieldset { border:none; margin:0 0 22px; padding:0; }
legend { font-weight:600; color:var(--navy); font-size:1.05rem; margin-bottom:2px; padding:0; }
.section-title { font-weight:700; color:var(--navy); margin:28px 0 4px; font-size:1.15rem; }
.section-title:first-of-type { margin-top:0; }
.choices label {
  display:block; padding:12px 14px; margin-top:8px;
  border:2px solid #C2C3C7; border-radius:0; background:#fff; cursor:pointer;
}
.choices input { margin-right:10px; transform:scale(1.3); }
.choices label:has(input:checked) { border-color:var(--gold); background:var(--cream); }
textarea.field { width:100%; min-height:110px; padding:12px 14px; font-size:1.05rem;
  font-family:inherit; border:2px solid #C2C3C7; border-radius:0; }
textarea.field:focus { border-color:var(--navy); outline:none; }
.err { background:var(--error-soft); border-left:6px solid var(--error); padding:12px 14px;
  border-radius:0; margin-bottom:16px; font-weight:600; }
</style>
</head>
<body>

<header class="site-head">
  <h1>Votre avis sur la journée</h1>
  <p>Anonyme · 4 minutes environ · pour améliorer la prochaine session</p>
</header>

<main class="wrap">

<?php if ($merci): ?>
  <section class="card" style="text-align:center">
    <h2>Merci !</h2>
    <p>Votre avis est bien enregistré. Il servira à améliorer la prochaine journée.</p>
    <p style="font-size:2.5rem;margin:8px 0">🌱</p>
    <a class="btn btn-primary" href="index.php">Retour à l'accueil</a>
  </section>
<?php else: ?>

  <form method="post" class="card" action="satisfaction.php">

    <?php if ($erreur): ?><div class="err"><?= e($erreur) ?></div><?php endif; ?>

    <fieldset>
      <legend>Prénom <span style="font-weight:400;color:var(--muted)">(facultatif)</span></legend>
      <input class="field" name="prenom" type="text" maxlength="100" value="<?= e($_POST['prenom'] ?? '') ?>">
    </fieldset>

    <p class="section-title">Votre satisfaction</p>
    <?php foreach ($CRITERES as $key => $label): ?>
    <fieldset>
      <legend><?= e($label) ?></legend>
      <?= smileys($key, $FACES_SATISFACTION) ?>
    </fieldset>
    <?php endforeach; ?>

    <p class="section-title">À l'issue de cette formation</p>
    <?php foreach ($CAPACITES as $key => $label): ?>
    <fieldset>
      <legend>Je me sens capable <?= e($label) ?></legend>
      <?= smileys($key, $FACES_CAPACITE) ?>
    </fieldset>
    <?php endforeach; ?>

    <fieldset>
      <legend>Le rythme de la journée ?</legend>
      <div class="choices">
        <label><input type="radio" name="rythme" value="LENT" required>Trop lent</label>
        <label><input type="radio" name="rythme" value="BIEN">Bien dosé</label>
        <label><input type="radio" name="rythme" value="RAPIDE">Trop rapide</label>
      </div>
    </fieldset>

    <fieldset>
      <legend>Ce que vous avez le plus apprécié</legend>
      <textarea class="field" name="apprecie" maxlength="2000" required
                placeholder="Une activité, un moment, une explication..."></textarea>
    </fieldset>

    <fieldset>
      <legend>Ce qui pourrait être amélioré</legend>
      <textarea class="field" name="ameliore" maxlength="2000" required
                placeholder="Un mot, une idée, une critique : tout est bon à prendre."></textarea>
    </fieldset>

    <fieldset>
      <legend>Vous recommanderiez cette formation à un autre demandeur d'emploi ?</legend>
      <?= smileys('recommande', $FACES_RECOMMANDE) ?>
    </fieldset>

    <fieldset>
      <legend>Souhaitez-vous suivre d'autres formations sur l'intelligence artificielle ?</legend>
      <?= smileys('interesse_formations', $FACES_INTERESSE) ?>
    </fieldset>

    <fieldset>
      <legend>Si oui, lesquelles ? <span style="font-weight:400;color:var(--muted)">(facultatif)</span></legend>
      <textarea class="field" name="autres_formations_lesquelles" maxlength="500"
                placeholder="Prompt avancé, agents IA, automatisation..."></textarea>
    </fieldset>

    <button class="btn btn-primary">Envoyer mon avis</button>
    <p class="rgpd">Vos réponses sont anonymes. Le prénom est facultatif : ne le renseignez que si vous acceptez d'être identifiable.</p>
  </form>

<?php endif; ?>

</main>
</body>
</html>
