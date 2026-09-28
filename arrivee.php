<?php
// Arrivée d'un participant : son prénom, une fois, depuis l'accueil.
// Le téléphone reçoit un cookie qui le relie à sa ligne pour la journée.
// « Ce n'est pas moi » oublie ce lien ; la ligne reste, le formateur la supprime.
require __DIR__ . '/core/participants.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Méthode non autorisée.');
}

$action = $_POST['action'] ?? '';

if ($action === 'arriver' && !participant_courant()) {
    $prenom = trim(preg_replace('/\s+/u', ' ', (string)($_POST['prenom'] ?? '')));
    if (preg_match('/^.{1,40}$/u', $prenom)) {
        $jeton = bin2hex(random_bytes(16));
        $st = db()->prepare('INSERT INTO participants (formation, seance, prenom, jeton) VALUES (?, ?, ?, ?)');
        $st->execute([formation_slug(), aujourdhui_local(), $prenom, $jeton]);
        cookie_participant($jeton, 86400);
    }
} elseif ($action === 'oublier') {
    cookie_participant('', -3600);
}

header('Location: ' . avec_f('index.php'), true, 303);
