<?php
require __DIR__ . '/horodatage.php';

// Accès réservé à l'animateur
if (($_GET['cle'] ?? '') !== CLE_ANIMATEUR) {
    http_response_code(403);
    exit('Accès réservé. Ajoutez ?cle=... à l\'adresse.');
}

const NOMS_JOURS = ['dimanche', 'lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi'];
const NOMS_MOIS  = [1 => 'janvier', 'février', 'mars', 'avril', 'mai', 'juin',
                    'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'];

const LIB_SAT = [
    'sat_accueil'   => 'Accueil et organisation',
    'sat_animation' => 'Qualité de l\'animation',
    'sat_clarte'    => 'Clarté des explications',
    'sat_contenu'   => 'Contenu de la formation',
    'sat_exercices' => 'Exercices proposés',
    'sat_supports'  => 'Supports remis',
    'sat_duree'     => 'Durée de la formation',
];

const LIB_CAPA = [
    'capable_utiliser_ia'      => 'Utiliser une IA générative',
    'capable_prompt'           => 'Rédiger un prompt efficace',
    'capable_cv'               => 'Améliorer son CV',
    'capable_lettre'           => 'Rédiger une lettre de motivation',
    'capable_recherche_emploi' => 'Utiliser l\'IA en recherche d\'emploi',
];

const LIB_CHOIX = [
    'LENT'   => 'Trop lent',
    'BIEN'   => 'Bien dosé',
    'RAPIDE' => 'Trop rapide',
    'OUI-SANS-HESITER' => 'Oui, sans hésiter',
    'OUI-PROBABLEMENT' => 'Oui, probablement',
    'NON-PAS-VRAIMENT' => 'Non, pas vraiment',
    'NON'    => 'Non',
    'OUI'    => 'Oui',
];

function jour_libelle(string $d): string
{
    $t = strtotime($d);
    return NOMS_JOURS[(int)date('w', $t)] . ' ' . (int)date('j', $t)
         . ' ' . NOMS_MOIS[(int)date('n', $t)] . ' ' . date('Y', $t);
}

function jour_court(string $d): string
{
    $t = strtotime($d);
    return (int)date('j', $t) . ' ' . substr(NOMS_MOIS[(int)date('n', $t)], 0, 4);
}

// Date au format AAAA-MM-JJ, chaîne vide si invalide
function date_clean(?string $d): string
{
    $d = trim((string)$d);
    return preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) && strtotime($d) ? $d : '';
}

// Toutes les questions, indexées par id (volumes minuscules : on agrège en PHP)
function catalogue_questions(): array
{
    static $cat = null;
    if ($cat === null) {
        $cat = [];
        $sql = 'SELECT q.id, q.ordre, q.texte, z.titre AS quiz
                FROM questions q JOIN quizzes z ON z.id = q.quiz_id';
        foreach (db()->query($sql) as $q) {
            $cat[(int)$q['id']] = $q;
        }
    }
    return $cat;
}

/**
 * Agrège une liste de lignes `resultats` (jointes au titre du quiz) :
 * participants, score moyen, détail par quiz, taux d'erreur par question.
 */
function agreger_resultats(array $rows): array
{
    $cat     = catalogue_questions();
    $scores  = [];
    $parQuiz = [];
    $errParQ = [];
    $participants = [];

    foreach ($rows as $row) {
        $ratio    = $row['total'] ? $row['score'] / $row['total'] : 0;
        $scores[] = $ratio;

        $qz = $row['quiz'];
        $parQuiz[$qz] ??= ['quiz' => $qz, 'nb' => 0, 'somme' => 0];
        $parQuiz[$qz]['nb']++;
        $parQuiz[$qz]['somme'] += $ratio;

        foreach (json_decode($row['reponses'], true) ?: [] as $r) {
            $qid = (int)($r['q'] ?? 0);
            $errParQ[$qid] ??= ['err' => 0, 'tot' => 0];
            $errParQ[$qid]['tot']++;
            if (empty($r['ok'])) {
                $errParQ[$qid]['err']++;
            }
        }

        $participants[] = [
            'prenom'  => $row['prenom'],
            'quiz'    => $row['quiz'],
            'session' => $row['session_code'],
            'score'   => (int)$row['score'],
            'total'   => (int)$row['total'],
            'heure'   => heure_locale($row['created_at']),
            'date'    => jour_local($row['created_at']),
        ];
    }

    // Uniquement les questions réellement ratées : les autres n'apprennent rien
    $questions = [];
    foreach ($errParQ as $qid => $e) {
        if (!isset($cat[$qid]) || !$e['tot'] || !$e['err']) {
            continue;
        }
        $questions[] = [
            'quiz'        => $cat[$qid]['quiz'],
            'ordre'       => (int)$cat[$qid]['ordre'],
            'texte'       => $cat[$qid]['texte'],
            'reponses'    => $e['tot'],
            'taux_erreur' => (int)round(100 * $e['err'] / $e['tot']),
        ];
    }
    usort($questions, fn($a, $b) => $b['taux_erreur'] <=> $a['taux_erreur']);

    $quiz = array_values(array_map(fn($q) => [
        'quiz'    => $q['quiz'],
        'nb'      => $q['nb'],
        'moyenne' => (int)round(100 * $q['somme'] / $q['nb']),
    ], $parQuiz));
    usort($quiz, fn($a, $b) => $b['nb'] <=> $a['nb']);

    return [
        'nb'           => count($rows),
        'moyenne'      => $scores ? (int)round(100 * array_sum($scores) / count($scores)) : 0,
        'par_quiz'     => $quiz,
        'participants' => $participants,
        'questions'    => $questions,
    ];
}

/**
 * Agrège une liste d'avis `satisfaction`.
 * Les notes à 0 viennent d'anciens enregistrements migrés : on les ignore
 * pour ne pas fausser les moyennes.
 */
function agreger_satisfaction(array $avis): array
{
    $moyenne = function (string $k) use ($avis) {
        $v = array_filter(array_column($avis, $k), fn($x) => (int)$x > 0);
        return $v ? round(array_sum($v) / count($v), 1) : null;
    };
    $repartition = function (string $k) use ($avis) {
        $out = [];
        foreach ($avis as $a) {
            $val = trim((string)$a[$k]);
            if ($val === '') {
                continue;
            }
            $out[$val] = ($out[$val] ?? 0) + 1;
        }
        arsort($out);
        $lignes = [];
        foreach ($out as $val => $n) {
            $lignes[] = ['libelle' => LIB_CHOIX[$val] ?? $val, 'n' => $n];
        }
        return $lignes;
    };
    $lignes = function (array $libelles, int $base) use ($moyenne) {
        $out = [];
        foreach ($libelles as $k => $lib) {
            $out[] = ['libelle' => $lib, 'valeur' => $moyenne($k), 'base' => $base];
        }
        return $out;
    };
    $moyenneDes = function (array $rows) {
        $v = array_filter(array_column($rows, 'valeur'), fn($x) => $x !== null);
        return $v ? round(array_sum($v) / count($v), 1) : null;
    };
    $commentaires = fn(string $k) => array_values(array_filter(
        array_map(fn($c) => trim((string)$c), array_column($avis, $k)),
        fn($c) => $c !== ''
    ));

    $criteres  = $lignes(LIB_SAT, 4);
    $capacites = $lignes(LIB_CAPA, 3);

    // Avis réellement notés : les anciens enregistrements migrés n'ont que des 0
    $notes = array_filter($avis, function ($a) {
        foreach (array_keys(LIB_SAT) as $k) {
            if ((int)$a[$k] > 0) return true;
        }
        return false;
    });

    return [
        'nb'                => count($avis),
        'nb_notes'          => count($notes),
        'moyenne_generale'  => $moyenneDes($criteres),
        'moyenne_capacites' => $moyenneDes($capacites),
        'criteres'          => $criteres,
        'capacites'         => $capacites,
        'repartitions'      => [
            ['libelle' => 'Rythme',                            'valeurs' => $repartition('rythme')],
            ['libelle' => 'Recommanderait la formation',       'valeurs' => $repartition('recommande')],
            ['libelle' => 'Intéressé par d\'autres formations', 'valeurs' => $repartition('interesse_formations')],
        ],
        'commentaires' => [
            ['libelle' => 'Ce qui a été apprécié',      'items' => $commentaires('apprecie')],
            ['libelle' => 'Ce qui pourrait être amélioré', 'items' => $commentaires('ameliore')],
            ['libelle' => 'Autres formations souhaitées',  'items' => $commentaires('autres_formations_lesquelles')],
        ],
    ];
}

const CHAMPS_SAT = 'session_code, sat_accueil, sat_animation, sat_clarte, sat_contenu, sat_exercices,
                    sat_supports, sat_duree, capable_utiliser_ia, capable_prompt, capable_cv,
                    capable_lettre, capable_recherche_emploi, rythme, apprecie, ameliore,
                    recommande, interesse_formations, autres_formations_lesquelles, created_at';

const CHAMPS_RES = 'r.session_code, r.prenom, r.score, r.total, r.reponses, r.created_at, z.titre AS quiz';

function repondre(array $data): void
{
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

// ---------------------------------------------------------------- API JSON --
$api = $_GET['json'] ?? '';

// Liste des journées de formation, de la plus récente à la plus ancienne
if ($api === 'jours') {
    // Regroupement en PHP et non en SQL : la journée est celle de l'heure
    // locale, que le serveur soit en UTC ou non (volumes minuscules).
    $jours = [];
    $collecte = function (string $table, string $cle) use (&$jours) {
        foreach (db()->query("SELECT session_code, created_at FROM $table") as $r) {
            $d = jour_local($r['created_at']);
            $jours[$d] ??= ['date' => $d, 'quiz' => 0, 'avis' => 0, 'sessions' => []];
            $jours[$d][$cle]++;
            if ($r['session_code'] !== '') {
                $jours[$d]['sessions'][$r['session_code']] = true;
            }
        }
    };
    $collecte('resultats', 'quiz');
    $collecte('satisfaction', 'avis');

    krsort($jours);
    repondre(['jours' => array_values(array_map(fn($j) => [
        'date'     => $j['date'],
        'libelle'  => jour_libelle($j['date']),
        'quiz'     => $j['quiz'],
        'avis'     => $j['avis'],
        'sessions' => array_keys($j['sessions']),
    ], $jours))]);
}

// Détail d'une journée
if ($api === 'jour') {
    $d = date_clean($_GET['d'] ?? '');
    if ($d === '') {
        http_response_code(400);
        repondre(['err' => 'date']);
    }

    // Bornes de la journée locale traduites dans le fuseau du serveur
    [$debut, $fin] = bornes_journee($d);

    $st = db()->prepare('SELECT ' . CHAMPS_RES . ' FROM resultats r
                         JOIN quizzes z ON z.id = r.quiz_id
                         WHERE r.created_at >= ? AND r.created_at < ? ORDER BY r.created_at DESC');
    $st->execute([$debut, $fin]);
    $rows = $st->fetchAll();

    $st = db()->prepare('SELECT ' . CHAMPS_SAT . ' FROM satisfaction
                         WHERE created_at >= ? AND created_at < ? ORDER BY created_at DESC');
    $st->execute([$debut, $fin]);
    $avis = $st->fetchAll();

    // Codes historiques uniquement : depuis la suppression de la saisie,
    // session_code vaut la date du jour et n'apprend rien de plus que la
    // journée affichée. On ne garde donc que les vrais codes (PARIS-0726…).
    $sessions = array_values(array_unique(array_merge(
        array_column($rows, 'session_code'),
        array_column($avis, 'session_code')
    )));
    $sessions = array_values(array_filter(
        $sessions,
        fn($c) => !preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$c)
    ));
    sort($sessions);

    repondre(agreger_resultats($rows) + [
        'date'         => $d,
        'libelle'      => jour_libelle($d),
        'sessions'     => $sessions,
        'satisfaction' => agreger_satisfaction($avis),
    ]);
}

// Statistiques cumulées sur toutes les sessions
if ($api === 'global') {
    $rows = db()->query('SELECT ' . CHAMPS_RES . ' FROM resultats r
                         JOIN quizzes z ON z.id = r.quiz_id')->fetchAll();
    $avis = db()->query('SELECT ' . CHAMPS_SAT . ' FROM satisfaction')->fetchAll();

    $agr = agreger_resultats($rows);
    unset($agr['participants']); // inutile ici : on ne liste pas 12 mois de prénoms

    // Évolution journée par journée
    $satParJour = [];
    foreach ($avis as $a) {
        $satParJour[jour_local($a['created_at'])][] = $a;
    }
    $resParJour = [];
    foreach ($rows as $r) {
        $resParJour[jour_local($r['created_at'])][] = $r;
    }

    $evolution = [];
    foreach (array_keys($resParJour + $satParJour) as $d) {
        $lignes = $resParJour[$d] ?? [];
        $sat    = agreger_satisfaction($satParJour[$d] ?? []);
        $evolution[] = [
            'date'         => $d,
            'court'        => jour_court($d),
            'libelle'      => jour_libelle($d),
            'nb'           => count($lignes),
            'moyenne'      => $lignes ? agreger_resultats($lignes)['moyenne'] : 0,
            'avis'         => $sat['nb'],
            'satisfaction' => $sat['moyenne_generale'],
        ];
    }
    usort($evolution, fn($a, $b) => strcmp($a['date'], $b['date']));

    repondre($agr + [
        'nb_jours'     => count($evolution),
        'nb_prenoms'   => count(array_unique(array_map(
            fn($r) => mb_strtolower($r['prenom']) . '|' . jour_local($r['created_at']), $rows))),
        'evolution'    => $evolution,
        'satisfaction' => agreger_satisfaction($avis),
    ]);
}

// Rétro-compatibilité : ?s=CODE présélectionne la journée de cette session
$dateInitiale = date_clean($_GET['d'] ?? '');
if ($dateInitiale === '' && ($code = session_code_clean($_GET['s'] ?? '')) !== '') {
    $st = db()->prepare('SELECT MAX(created_at) d FROM resultats WHERE session_code = ?');
    $st->execute([$code]);
    $max = $st->fetchColumn();
    $dateInitiale = $max ? jour_local($max) : '';
}
?><!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title>Résultats des formations</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:wght@600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="style.css">
</head>
<body>

<header class="site-head">
  <h1>Résultats des formations</h1>
  <p>Choisissez une journée pour voir le détail, ou consultez le bilan de toutes les sessions</p>
</header>

<main class="wrap wrap-large">

  <div class="dash">

    <aside class="card dash-side">
      <h2>Journées</h2>
      <p class="lead">De la plus récente à la plus ancienne.</p>
      <select id="liste-jours" class="listbox" size="14" aria-label="Journées de formation">
        <option disabled>Chargement…</option>
      </select>
      <button type="button" class="btn btn-ghost" id="btn-global" style="margin-top:14px">
        Bilan toutes sessions
      </button>
    </aside>

    <div class="dash-main">

      <section class="card" id="vue-jour" hidden>
        <h2 id="jour-titre">—</h2>
        <p class="lead" id="jour-sessions"></p>

        <div class="stat-row">
          <div><div class="stat-big" id="jour-nb">–</div><div>quiz remplis</div></div>
          <div><div class="stat-big" id="jour-moy">–</div><div>score moyen</div></div>
          <div><div class="stat-big" id="jour-avis">–</div><div>avis recueillis</div></div>
        </div>

        <h3>Participants</h3>
        <table class="board">
          <thead><tr><th>Prénom</th><th>Quiz</th><th>Score</th><th>Heure</th></tr></thead>
          <tbody id="jour-participants"></tbody>
        </table>

        <h3>Questions les plus ratées</h3>
        <table class="board"><tbody id="jour-questions"></tbody></table>

        <h3>Satisfaction</h3>
        <div id="jour-satisfaction"></div>
        <a class="btn btn-ghost" id="jour-export" style="margin-top:16px" href="#">
          Exporter les questionnaires en PDF
        </a>
      </section>

      <section class="card" id="vue-global" hidden>
        <h2>Bilan de toutes les sessions</h2>

        <div class="stat-row">
          <div><div class="stat-big" id="g-jours">–</div><div>journées</div></div>
          <div><div class="stat-big" id="g-participants">–</div><div>participants</div></div>
          <div><div class="stat-big" id="g-nb">–</div><div>quiz remplis</div></div>
          <div><div class="stat-big" id="g-moy">–</div><div>score moyen</div></div>
          <div><div class="stat-big" id="g-avis">–</div><div>avis recueillis</div></div>
        </div>

        <h3>Évolution par journée</h3>
        <table class="board">
          <thead><tr><th>Journée</th><th>Quiz</th><th>Score moyen</th><th>Satisfaction</th></tr></thead>
          <tbody id="g-evolution"></tbody>
        </table>

        <h3>Par quiz</h3>
        <table class="board">
          <thead><tr><th>Quiz</th><th>Passages</th><th>Score moyen</th></tr></thead>
          <tbody id="g-quiz"></tbody>
        </table>

        <h3>Questions les plus ratées, tous quiz confondus</h3>
        <table class="board"><tbody id="g-questions"></tbody></table>

        <h3>Satisfaction</h3>
        <div id="g-satisfaction"></div>
      </section>

      <section class="card" id="vue-vide">
        <h2>Aucune journée sélectionnée</h2>
        <p class="lead">Choisissez une journée dans la liste, ou affichez le bilan de toutes les sessions.</p>
      </section>

    </div>
  </div>

</main>

<script>
var CLE  = '<?= rawurlencode(CLE_ANIMATEUR) ?>';
var JOUR_INITIAL = '<?= e($dateInitiale) ?>';
var AUJOURDHUI   = '<?= aujourdhui_local() ?>';
var timerLive = null;

function api(params, ok) {
  fetch('resultats.php?cle=' + CLE + '&' + params)
    .then(function (r) { return r.json(); })
    .then(ok)
    .catch(function () {});
}

function esc(s) {
  var d = document.createElement('div');
  d.textContent = s === null || s === undefined ? '' : s;
  return d.innerHTML;
}

function montrer(id) {
  ['vue-jour', 'vue-global', 'vue-vide'].forEach(function (v) {
    document.getElementById(v).hidden = (v !== id);
  });
}

// ---- fragments réutilisés par la vue « journée » et la vue « bilan » ----

function lignesQuestions(questions, avecQuiz) {
  if (!questions.length) {
    return '<tr><td class="muted">Aucune question ratée.</td></tr>';
  }
  return questions.map(function (q) {
    var couleur = q.taux_erreur >= 50 ? 'var(--error)' : 'var(--leaf)';
    var titre = (avecQuiz ? '<span class="pill">' + esc(q.quiz) + '</span> ' : '') +
                q.ordre + '. ' + esc(q.texte);
    return '<tr><td style="width:62%">' + titre + '</td>' +
           '<td><div class="bar"><i style="width:' + q.taux_erreur + '%;background:' + couleur + '"></i></div>' +
           q.taux_erreur + ' % d’erreurs <span class="muted">(' + q.reponses +
           (q.reponses > 1 ? ' réponses' : ' réponse') + ')</span></td></tr>';
  }).join('');
}

function blocSatisfaction(s) {
  if (!s.nb) {
    return '<p class="muted">Aucun avis recueilli.</p>';
  }
  var html = '<div class="stat-row">' +
    '<div><div class="stat-big">' + (s.moyenne_generale === null ? '–' : s.moyenne_generale) +
    '</div><div>satisfaction /4</div></div>' +
    '<div><div class="stat-big">' + (s.moyenne_capacites === null ? '–' : s.moyenne_capacites) +
    '</div><div>capacités /3</div></div>' +
    '<div><div class="stat-big">' + s.nb + '</div><div>avis</div></div></div>';

  if (s.nb_notes === 0) {
    html += '<p class="muted">Ces avis proviennent de l’ancien questionnaire : ils ne contiennent aucune note.</p>';
  } else if (s.nb_notes < s.nb) {
    html += '<p class="muted">Moyennes calculées sur ' + s.nb_notes + ' avis noté' +
            (s.nb_notes > 1 ? 's' : '') + ' : les autres proviennent de l’ancien questionnaire, sans notes.</p>';
  }

  html += '<table class="board"><tbody>';
  s.criteres.concat(s.capacites).forEach(function (c) {
    var pct = c.valeur === null ? 0 : Math.round(100 * c.valeur / c.base);
    html += '<tr><td style="width:45%"><strong>' + esc(c.libelle) + '</strong></td>' +
            '<td><div class="bar"><i style="width:' + pct + '%"></i></div>' +
            (c.valeur === null ? '–' : c.valeur + ' / ' + c.base) + '</td></tr>';
  });
  s.repartitions.forEach(function (r) {
    var parts = r.valeurs.map(function (v) { return esc(v.libelle) + ' × ' + v.n; });
    html += '<tr><td><strong>' + esc(r.libelle) + '</strong></td><td>' +
            (parts.length ? parts.join(' · ') : '–') + '</td></tr>';
  });
  html += '</tbody></table>';

  s.commentaires.forEach(function (c) {
    html += '<h3>' + esc(c.libelle) + '</h3>';
    html += c.items.length
      ? c.items.map(function (t) { return '<p class="verbatim">' + esc(t) + '</p>'; }).join('')
      : '<p class="muted">Aucun retour.</p>';
  });
  return html;
}

// ---- vue « journée » ----

function chargerJour(d) {
  api('json=jour&d=' + encodeURIComponent(d), function (j) {
    if (j.err) { return; }
    montrer('vue-jour');
    document.getElementById('jour-titre').textContent = j.libelle;
    document.getElementById('jour-sessions').innerHTML = j.sessions.length
      ? 'Session' + (j.sessions.length > 1 ? 's' : '') + ' : ' +
        j.sessions.map(function (c) { return '<span class="pill">' + esc(c) + '</span>'; }).join(' ')
      : '';
    document.getElementById('jour-nb').textContent   = j.nb;
    document.getElementById('jour-moy').textContent  = j.nb ? j.moyenne + ' %' : '–';
    document.getElementById('jour-avis').textContent = j.satisfaction.nb;

    document.getElementById('jour-participants').innerHTML = j.participants.length
      ? j.participants.map(function (p) {
          return '<tr><td>' + esc(p.prenom) + '</td><td><span class="pill">' + esc(p.quiz) + '</span></td>' +
                 '<td><strong>' + p.score + ' / ' + p.total + '</strong></td><td>' + p.heure + '</td></tr>';
        }).join('')
      : '<tr><td colspan="4" class="muted">Aucun quiz rempli ce jour-là.</td></tr>';

    document.getElementById('jour-questions').innerHTML = lignesQuestions(j.questions, j.par_quiz.length > 1);
    document.getElementById('jour-satisfaction').innerHTML = blocSatisfaction(j.satisfaction);

    var exporter = document.getElementById('jour-export');
    exporter.href = 'export-satisfaction.php?cle=' + CLE + '&d=' + encodeURIComponent(j.date);
    exporter.hidden = !j.satisfaction.nb;
  });
}

// ---- vue « bilan » ----

function chargerGlobal() {
  api('json=global', function (g) {
    montrer('vue-global');
    document.getElementById('g-jours').textContent        = g.nb_jours;
    document.getElementById('g-participants').textContent = g.nb_prenoms;
    document.getElementById('g-nb').textContent           = g.nb;
    document.getElementById('g-moy').textContent      = g.nb ? g.moyenne + ' %' : '–';
    document.getElementById('g-avis').textContent     = g.satisfaction.nb;

    document.getElementById('g-evolution').innerHTML = g.evolution.slice().reverse().map(function (e) {
      return '<tr><td><a href="#" data-jour="' + esc(e.date) + '">' + esc(e.libelle) + '</a></td>' +
             '<td>' + e.nb + '</td>' +
             '<td><div class="bar"><i style="width:' + e.moyenne + '%"></i></div>' + e.moyenne + ' %</td>' +
             '<td>' + (e.satisfaction === null ? '–' : e.satisfaction + ' / 4') +
             ' <span class="muted">(' + e.avis + ')</span></td></tr>';
    }).join('') || '<tr><td colspan="4" class="muted">Aucune donnée.</td></tr>';

    document.getElementById('g-quiz').innerHTML = g.par_quiz.map(function (q) {
      return '<tr><td>' + esc(q.quiz) + '</td><td>' + q.nb + '</td>' +
             '<td><div class="bar"><i style="width:' + q.moyenne + '%"></i></div>' + q.moyenne + ' %</td></tr>';
    }).join('') || '<tr><td colspan="3" class="muted">Aucune donnée.</td></tr>';

    document.getElementById('g-questions').innerHTML = lignesQuestions(g.questions, true);
    document.getElementById('g-satisfaction').innerHTML = blocSatisfaction(g.satisfaction);
  });
}

// ---- liste des journées ----

var liste = document.getElementById('liste-jours');

function selectionner(d) {
  liste.value = d;
  clearInterval(timerLive);
  chargerJour(d);
  // La journée en cours se rafraîchit toute seule pendant la formation
  if (d === AUJOURDHUI) {
    timerLive = setInterval(function () { chargerJour(d); }, 10000);
  }
}

liste.addEventListener('change', function () {
  if (liste.value) { selectionner(liste.value); }
});

document.getElementById('btn-global').addEventListener('click', function () {
  clearInterval(timerLive);
  liste.selectedIndex = -1;
  chargerGlobal();
});

document.getElementById('g-evolution').addEventListener('click', function (ev) {
  var a = ev.target.closest('[data-jour]');
  if (a) { ev.preventDefault(); selectionner(a.getAttribute('data-jour')); }
});

api('json=jours', function (d) {
  if (!d.jours.length) {
    liste.innerHTML = '<option disabled>Aucune journée enregistrée</option>';
    return;
  }
  liste.innerHTML = d.jours.map(function (j) {
    var resume = j.quiz + ' quiz' + (j.avis ? ' · ' + j.avis + ' avis' : '');
    return '<option value="' + esc(j.date) + '">' + esc(j.libelle) + ' — ' + resume + '</option>';
  }).join('');

  var voulu = JOUR_INITIAL;
  var dispo = d.jours.map(function (j) { return j.date; });
  selectionner(dispo.indexOf(voulu) >= 0 ? voulu : dispo[0]);
});
</script>

</body>
</html>
