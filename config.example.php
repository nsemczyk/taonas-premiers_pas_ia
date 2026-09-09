<?php
// Copy this file to config.php and fill in your credentials.
// config.php is gitignore-able and never committed.

define('DB_HOST', 'localhost');
define('DB_NAME', 'formation_ia');
define('DB_USER', 'formation');
define('DB_PASS', 'change-me');

// Access key for the live results board (resultats.php?cle=...)
define('CLE_ANIMATEUR', 'change-me-too');

// ---------------------------------------------------------------------------
// Notification par mail des évaluations à froid (facultatif)
//
// Un mail est envoyé au formateur à chaque questionnaire d'évaluation à froid
// rempli. Laisser MAIL_ACTIF à false — ou supprimer ce bloc — pour ne rien
// envoyer : le site fonctionne à l'identique.
//
// Avec Gmail, SMTP_PASS n'est PAS le mot de passe du compte mais un
// « mot de passe d'application » de 16 caractères, à créer sur
// https://myaccount.google.com/apppasswords (validation en deux étapes
// obligatoire au préalable). Voir la section « Notification par mail » du
// README pour la marche à suivre complète.
// ---------------------------------------------------------------------------
define('MAIL_ACTIF', false);

// Destinataire(s) de la notification. Plusieurs adresses : séparées par des virgules.
define('MAIL_DEST', 'formateur@example.com');

// Serveur d'envoi. Gmail : smtp.gmail.com, port 587 en 'tls' (ou 465 en 'ssl').
define('SMTP_HOTE',     'smtp.gmail.com');
define('SMTP_PORT',     587);
define('SMTP_SECURITE', 'tls');          // 'tls' (STARTTLS, port 587) | 'ssl' (port 465)
define('SMTP_USER',     'moncompte@gmail.com');
define('SMTP_PASS',     'xxxxxxxxxxxxxxxx');   // mot de passe d'application Google
define('SMTP_TIMEOUT',  15);                   // secondes

// Expéditeur affiché. Gmail réécrit l'adresse : elle doit être celle du compte
// SMTP_USER (ou un alias validé dans « Envoyer des e-mails en tant que »).
define('MAIL_EXPEDITEUR',     'moncompte@gmail.com');
define('MAIL_EXPEDITEUR_NOM', 'Formation IA générative');
// Adresse à laquelle répondre, si différente (facultatif) :
// define('MAIL_REPONDRE_A', 'formateur@example.com');

// Adresse publique du site, sans slash final. Si elle est renseignée, le mail
// contient un lien direct vers le bilan des évaluations à froid.
// define('SITE_URL', 'https://exemple.fr/formation');

function db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $pdo = new PDO(
            'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
            DB_USER,
            DB_PASS,
            [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]
        );
    }
    return $pdo;
}

function e(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

// Session code: uppercase letters, digits, dash. Empty string if invalid.
function session_code_clean(?string $s): string {
    $s = strtoupper(trim((string)$s));
    return preg_match('/^[A-Z0-9-]{2,32}$/', $s) ? $s : '';
}
