<?php
require __DIR__ . '/horodatage.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'err' => 'method']);
    exit;
}

$in = json_decode(file_get_contents('php://input'), true);
if (!is_array($in)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'err' => 'json']);
    exit;
}

// Plus de code de session à saisir : la journée suffit à regrouper les résultats
$session = aujourdhui_local();
$quiz_id = (int)($in['quiz_id'] ?? 0);
$prenom  = trim((string)($in['prenom'] ?? ''));
$score   = (int)($in['score'] ?? -1);
$total   = (int)($in['total'] ?? 0);
$reps    = $in['reponses'] ?? null;

// Validations
if (!preg_match('/^.{1,40}$/us', $prenom)
    || $quiz_id <= 0 || $total <= 0 || $total > 100
    || $score < 0 || $score > $total || !is_array($reps) || count($reps) !== $total) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'err' => 'invalid']);
    exit;
}

// Le quiz existe et est actif ?
$st = db()->prepare('SELECT 1 FROM quizzes WHERE id = ? AND actif = 1');
$st->execute([$quiz_id]);
if (!$st->fetch()) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'err' => 'quiz']);
    exit;
}

// Réponses : ne garder que les champs attendus
$clean = [];
foreach ($reps as $r) {
    if (!is_array($r)) continue;
    $rep = ($r['r'] ?? '') === 'VRAI' ? 'VRAI' : 'FAUX';
    $clean[] = ['q' => (int)($r['q'] ?? 0), 'r' => $rep, 'ok' => (bool)($r['ok'] ?? false)];
}

$st = db()->prepare(
    'INSERT INTO resultats (session_code, quiz_id, prenom, score, total, reponses)
     VALUES (?, ?, ?, ?, ?, ?)'
);
$st->execute([
    $session,
    $quiz_id,
    $prenom,
    $score,
    $total,
    json_encode($clean, JSON_UNESCAPED_UNICODE),
]);

echo json_encode(['ok' => true]);
