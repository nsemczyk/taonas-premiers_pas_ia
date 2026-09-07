<?php
// Copy this file to config.php and fill in your credentials.
// config.php is gitignore-able and never committed.

define('DB_HOST', 'localhost');
define('DB_NAME', 'formation_ia');
define('DB_USER', 'formation');
define('DB_PASS', 'change-me');

// Access key for the live results board (resultats.php?cle=...)
define('CLE_ANIMATEUR', 'change-me-too');

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
