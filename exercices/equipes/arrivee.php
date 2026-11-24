<?php
// Arrivée d'un participant : son prénom, une fois, depuis l'accueil.
// Le téléphone reçoit un cookie qui le relie à sa ligne pour la journée.
// Les prénoms sont uniques dans la séance (sans tenir compte de la casse ni des
// accents) : un prénom déjà pris propose de reprendre sa session (cookie perdu,
// navigateur fermé, autre appareil) ou d'ajouter l'initiale de son nom.
// « Ce n'est pas moi » oublie ce lien ; la ligne reste, le formateur la supprime.
require __DIR__ . '/../../core/participants.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Méthode non autorisée.');
}

$action = $_POST['action'] ?? '';

$retour = 'index.php';
if ($action === 'arriver' && !participant_courant()) {
    $prenom = trim(preg_replace('/\s+/u', ' ', (string)($_POST['prenom'] ?? '')));
    if (preg_match('/^.{1,40}$/u', $prenom)) {
        if (participant_par_prenom($prenom)) {
            $retour = 'index.php?reprise=' . rawurlencode($prenom);   // déjà pris : reprise ou initiale
        } else {
            $jeton = bin2hex(random_bytes(16));
            $st = db()->prepare('INSERT INTO participants (formation, seance, prenom, jeton) VALUES (?, ?, ?, ?)');
            $st->execute([formation_slug(), aujourdhui_local(), $prenom, $jeton]);
            cookie_participant($jeton, 86400);
        }
    }
} elseif ($action === 'reprendre' && !participant_courant()) {
    // Même jeton : les deux appareils restent reliés au même participant
    $p = participant_par_prenom((string)($_POST['prenom'] ?? ''));
    if ($p) {
        cookie_participant($p['jeton'], 86400);
    }
} elseif ($action === 'oublier') {
    cookie_participant('', -3600);
}

header('Location: ' . avec_f($retour), true, 303);
