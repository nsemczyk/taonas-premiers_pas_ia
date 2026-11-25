<?php
// Formulaire de satisfaction d'une formation qui décrit son questionnaire
// (clé 'questionnaire' de formation.php). Inclus par satisfaction.php, qui
// garde son formulaire historique pour les autres formations. Même rendu.
// Nominatif : il faut s'être présenté à l'arrivée ; un avis par participant et par jour.
require_once __DIR__ . '/questionnaire.php';

$q_def  = questionnaire();
$moi    = participant_courant();
$pret   = questionnaire_table_ok();
$deja   = $moi && $pret && questionnaire_deja_rempli((int)$moi['id']);
$erreur = '';
$merci  = false;
$saisie = $_POST;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $moi && $pret && !$deja) {
    [$reponses, $erreur] = questionnaire_lire($_POST);
    if (!$erreur) {
        questionnaire_enregistrer($reponses, $moi);
        $merci = true;
    }
}

// Une question du formulaire, valeurs déjà saisies conservées en cas d'erreur
function question_html(string $cle, array $q, array $saisie): string
{
    $requis = question_obligatoire($q) ? ' required' : '';
    $h = '<fieldset><legend>' . e($q['intitule'])
       . ($q['type'] === 'multi' ? ' <span class="aide">Plusieurs réponses possibles.</span>' : '')
       . (!question_obligatoire($q) && $q['type'] !== 'multi' ? ' <span class="aide">(facultatif)</span>' : '')
       . '</legend>';
    switch ($q['type']) {
        case 'choix':
        case 'multi':
            $multi = $q['type'] === 'multi';
            $h .= '<div class="choices">';
            foreach ($q['options'] as $k => $libelle) {
                $coche = $multi ? in_array($k, (array)($saisie[$cle] ?? []), true) : ($saisie[$cle] ?? '') === $k;
                $h .= '<label><input type="' . ($multi ? 'checkbox' : 'radio') . '" name="' . e($cle) . ($multi ? '[]' : '') . '"'
                    . ' value="' . e($k) . '"' . ($coche ? ' checked' : '') . ($multi ? '' : $requis) . '>' . e($libelle) . '</label>';
            }
            if (!empty($q['autre'])) {
                $h .= '<label class="autre">Autres :<input class="field" type="text" name="' . e($cle) . '_autre" maxlength="' . QUESTIONNAIRE_MAX_AUTRE . '"'
                    . ' value="' . e($saisie[$cle . '_autre'] ?? '') . '" placeholder="à préciser"></label>';
            }
            $h .= '</div>';
            break;
        case 'texte':
            $h .= '<textarea class="field" name="' . e($cle) . '" maxlength="' . QUESTIONNAIRE_MAX_TEXTE . '"' . $requis . '>'
                . e($saisie[$cle] ?? '') . '</textarea>';
            break;
        case 'note':
            $h .= '<div class="note-echelle" role="radiogroup">';
            for ($n = 0; $n <= $q['max']; $n++) {
                $id = $cle . '-' . $n;
                $h .= '<input type="radio" name="' . e($cle) . '" id="' . e($id) . '" value="' . $n . '"'
                    . (($saisie[$cle] ?? '') === (string)$n ? ' checked' : '') . $requis . '>'
                    . '<label for="' . e($id) . '">' . $n . '</label>';
            }
            $h .= '</div><p class="aide">' . e('sur ' . $q['max']) . '</p>';
            break;
    }
    return $h . '</fieldset>';
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
</head>
<body class="questionnaire">

<header class="site-head">
  <h1>Votre avis sur la journée</h1>
  <p>5 minutes environ · pour améliorer la prochaine session</p>
</header>

<main class="wrap">

<?php if (!$moi): ?>
  <section class="card">
    <h2>D'abord, votre prénom</h2>
    <p class="lead">Indiquez votre prénom sur l'accueil : votre avis y sera rattaché.</p>
    <a class="btn btn-primary" href="<?= e(avec_f('index.php')) ?>">Retour à l'accueil</a>
  </section>
<?php elseif (!$pret): ?>
  <section class="card"><h2>Bientôt…</h2><p class="lead">Le questionnaire n'est pas encore installé : prévenez le formateur.</p></section>
<?php elseif ($deja && !$merci): ?>
  <section class="card" style="text-align:center">
    <h2>Merci <?= e($moi['prenom']) ?> !</h2>
    <p>Votre avis sur la journée est déjà enregistré.</p>
    <a class="btn btn-primary" href="<?= e(avec_f('index.php')) ?>">Retour à l'accueil</a>
  </section>
<?php elseif ($merci): ?>
  <section class="card" style="text-align:center">
    <h2>Merci pour votre participation !</h2>
    <p>Vos réponses nous aideront à faire évoluer nos formations et à proposer des contenus toujours plus adaptés à vos besoins.</p>
    <p style="font-size:2.5rem;margin:8px 0">🌱</p>
    <a class="btn btn-primary" href="<?= e(avec_f('index.php')) ?>">Retour à l'accueil</a>
  </section>
<?php else: ?>

  <form method="post" class="card" action="<?= e(avec_f('satisfaction.php')) ?>">

    <?php if ($erreur): ?><div class="err"><?= e($erreur) ?></div><?php endif; ?>

    <p class="q-formation"><?= e($q_def['formation'] ?? '') ?><?= !empty($q_def['duree']) ? ' · ' . e($q_def['duree']) : '' ?></p>

    <p class="q-identite">Questionnaire de <strong><?= e($moi['prenom']) ?></strong> · <?= e(date('d/m/Y', strtotime(aujourdhui_local()))) ?></p>

    <?php $n = 0; foreach ($q_def['sections'] as $i => $section): ?>
    <p class="section-title"><?= $i + 1 ?>. <?= e($section['titre']) ?></p>
      <?php foreach ($section['questions'] as $cle => $q): $n++; ?>
        <?= str_replace('<legend>', '<legend>' . $n . '. ', question_html($cle, $q, $saisie)) ?>
      <?php endforeach; ?>
    <?php endforeach; ?>

    <button class="btn btn-primary">Envoyer mon avis</button>
    <p class="rgpd">Vos réponses sont enregistrées avec votre prénom, pour le suivi de la formation. Suppression sur simple demande auprès du formateur.</p>
  </form>

<?php endif; ?>

</main>
</body>
</html>
