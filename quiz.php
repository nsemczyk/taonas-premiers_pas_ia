<?php
require __DIR__ . '/config.php';

$slug = preg_replace('/[^a-z0-9-]/', '', $_GET['slug'] ?? '');

$st = db()->prepare('SELECT id, titre FROM quizzes WHERE slug = ? AND actif = 1');
$st->execute([$slug]);
$quiz = $st->fetch();

if (!$quiz) {
    http_response_code(404);
    exit('Quiz introuvable.');
}

$st = db()->prepare('SELECT id, ordre, texte, bonne_reponse, explication FROM questions WHERE quiz_id = ? ORDER BY ordre');
$st->execute([$quiz['id']]);
$questions = $st->fetchAll();

$BONUS_SLUG = 'quiz-bonus';
$estBonus   = ($slug === $BONUS_SLUG);
?><!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title><?= e($quiz['titre']) ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:wght@600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="style.css">
</head>
<body>

<header class="site-head">
  <h1><?= e($quiz['titre']) ?></h1>
  <p><?= count($questions) ?> questions · VRAI ou FAUX</p>
</header>

<main class="wrap">

  <!-- Étape 1 : prénom -->
  <section class="card" id="step-prenom">
    <h2>Avant de commencer</h2>
    <label class="field" for="prenom">Votre prénom</label>
    <input class="field" id="prenom" type="text" maxlength="40"
           autocomplete="given-name" placeholder="Par exemple : Sam">
    <button class="btn btn-primary" id="btn-start">Commencer le quiz</button>
    <p class="rgpd">Seuls votre prénom et vos réponses sont enregistrés, pour le suivi de la formation.</p>
  </section>

  <!-- Étape 2 : questions -->
  <section class="card" id="step-quiz" hidden>
    <div class="leaf-path" id="leafpath" aria-hidden="true"></div>
    <p class="q-count" id="qcount"></p>
    <p class="q-text" id="qtext"></p>
    <div class="answers" id="answers">
      <button class="btn btn-vrai" data-rep="VRAI">VRAI</button>
      <button class="btn btn-faux" data-rep="FAUX">FAUX</button>
    </div>
    <div class="feedback" id="feedback" hidden></div>
    <button class="btn btn-ghost" id="btn-next" hidden>Question suivante</button>
  </section>

  <!-- Étape 3 : score -->
  <section class="card" id="step-fin" hidden>
    <h2 style="text-align:center">Bravo <span id="fin-prenom"></span> !</h2>
    <p class="score-final" id="fin-score"></p>
    <p style="text-align:center" id="fin-message"></p>
    <p class="rgpd" id="fin-save"></p>
    <?php if (!$estBonus): ?>
    <div style="text-align:center;margin-top:24px">
      <p>Envie d'aller plus loin ?</p>
      <a class="btn btn-ghost" href="quiz.php?slug=<?= e($BONUS_SLUG) ?>">Tenter le quiz bonus (facultatif)</a>
    </div>
    <?php endif; ?>
  </section>

</main>

<script>
var QUESTIONS = <?= json_encode(array_map(function ($q) {
    return [
        'id'  => (int)$q['id'],
        'txt' => $q['texte'],
        'rep' => $q['bonne_reponse'],
        'exp' => $q['explication'],
    ];
}, $questions), JSON_UNESCAPED_UNICODE) ?>;
var QUIZ_ID = <?= (int)$quiz['id'] ?>;

(function () {
  var idx = 0, score = 0, prenom = '', reponses = [];
  var elPrenom  = document.getElementById('step-prenom');
  var elQuiz    = document.getElementById('step-quiz');
  var elFin     = document.getElementById('step-fin');
  var qtext     = document.getElementById('qtext');
  var qcount    = document.getElementById('qcount');
  var answers   = document.getElementById('answers');
  var feedback  = document.getElementById('feedback');
  var btnNext   = document.getElementById('btn-next');
  var leafpath  = document.getElementById('leafpath');

  // sentier de feuilles (progression)
  QUESTIONS.forEach(function () {
    leafpath.appendChild(document.createElement('span'));
  });
  function paintLeaves() {
    var leaves = leafpath.children;
    for (var i = 0; i < leaves.length; i++) {
      leaves[i].className = i < idx ? 'done' : (i === idx ? 'now' : '');
    }
  }

  document.getElementById('btn-start').addEventListener('click', function () {
    prenom = document.getElementById('prenom').value.trim();
    if (!prenom) {
      document.getElementById('prenom').focus();
      return;
    }
    elPrenom.hidden = true;
    elQuiz.hidden = false;
    show();
  });

  function show() {
    var q = QUESTIONS[idx];
    qcount.textContent = 'Question ' + (idx + 1) + ' sur ' + QUESTIONS.length;
    qtext.textContent = q.txt;
    feedback.hidden = true;
    btnNext.hidden = true;
    answers.hidden = false;
    paintLeaves();
    window.scrollTo({ top: 0 });
  }

  answers.addEventListener('click', function (ev) {
    var btn = ev.target.closest('[data-rep]');
    if (!btn) return;
    var q = QUESTIONS[idx];
    var rep = btn.getAttribute('data-rep');
    var ok = rep === q.rep;
    if (ok) score++;
    reponses.push({ q: q.id, r: rep, ok: ok });

    answers.hidden = true;
    feedback.hidden = false;
    feedback.className = 'feedback ' + (ok ? 'ok' : 'ko');
    feedback.innerHTML = '<strong>' + (ok ? 'Bonne réponse !' : 'Et non… c\u2019était ' + q.rep + '.') +
                         '</strong>' + escapeHtml(q.exp);
    btnNext.hidden = false;
    btnNext.textContent = (idx + 1 < QUESTIONS.length) ? 'Question suivante' : 'Voir mon score';
    btnNext.focus();
  });

  btnNext.addEventListener('click', function () {
    idx++;
    if (idx < QUESTIONS.length) {
      show();
    } else {
      finish();
    }
  });

  function finish() {
    elQuiz.hidden = false;
    elQuiz.hidden = true;
    elFin.hidden = false;
    document.getElementById('fin-prenom').textContent = prenom;
    document.getElementById('fin-score').textContent = score + ' / ' + QUESTIONS.length;
    var msg;
    var ratio = score / QUESTIONS.length;
    if (ratio >= 0.9)      msg = 'Score de dompteur ! L\u2019IA n\u2019a plus de secret pour vous.';
    else if (ratio >= 0.7) msg = 'Très beau score : les r\u00e8gles d\u2019or sont acquises.';
    else                   msg = 'L\u2019essentiel est l\u00e0 \u2014 on reprend les points d\u00e9licats ensemble.';
    document.getElementById('fin-message').textContent = msg;

    save();
  }

  function save() {
    var note = document.getElementById('fin-save');
    fetch('save.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        quiz_id: QUIZ_ID,
        prenom: prenom,
        score: score,
        total: QUESTIONS.length,
        reponses: reponses
      })
    }).then(function (r) {
      note.textContent = r.ok ? 'R\u00e9sultat enregistr\u00e9.'
                              : 'R\u00e9sultat non enregistr\u00e9 \u2014 montrez votre \u00e9cran au formateur.';
    }).catch(function () {
      note.textContent = 'R\u00e9sultat non enregistr\u00e9 \u2014 montrez votre \u00e9cran au formateur.';
    });
  }

  function escapeHtml(s) {
    var d = document.createElement('div');
    d.textContent = s;
    return d.innerHTML;
  }
})();
</script>

</body>
</html>
