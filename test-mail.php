<?php
// Vérification de la configuration d'envoi de mail.
//
//   En ligne de commande :  php test-mail.php
//   Depuis un navigateur :  test-mail.php?cle=CLE_ANIMATEUR
//
// Envoie un message d'essai aux adresses de MAIL_DEST et affiche l'erreur
// exacte en cas d'échec. Avec l'option « derniere », renvoie la notification
// de la dernière évaluation à froid reçue, telle qu'elle serait envoyée :
//
//   php test-mail.php derniere
//   test-mail.php?cle=CLE_ANIMATEUR&derniere=1
//
// Ce fichier n'est utile qu'à la mise en place : il peut être supprimé ensuite.

require __DIR__ . '/eval-froide-notification.php';

$cli = (PHP_SAPI === 'cli');

if (!$cli) {
    header('Content-Type: text/plain; charset=utf-8');
    if (!defined('CLE_ANIMATEUR') || (string)($_GET['cle'] ?? '') !== (string)CLE_ANIMATEUR) {
        http_response_code(403);
        exit("Accès refusé.\n");
    }
}

$derniere = $cli
    ? in_array('derniere', array_slice($argv, 1), true)
    : isset($_GET['derniere']);

echo "Configuration\n";
echo "-------------\n";
printf("  MAIL_ACTIF       : %s\n", (defined('MAIL_ACTIF') && MAIL_ACTIF) ? 'oui' : 'non');
printf("  Destinataires    : %s\n", implode(', ', ef_notif_destinataires()) ?: '(aucun valide)');
printf("  Serveur          : %s:%d (%s)\n",
    (string)ef_notif_conf('SMTP_HOTE', '—'),
    (int)ef_notif_conf('SMTP_PORT', 0),
    (string)ef_notif_conf('SMTP_SECURITE', '—'));
printf("  Compte SMTP      : %s\n", (string)ef_notif_conf('SMTP_USER', '(aucun)'));
printf("  Mot de passe     : %s\n",
    trim((string)ef_notif_conf('SMTP_PASS', '')) === '' ? '(vide)' : '(renseigné)');
printf("  Expéditeur       : %s\n", (string)ef_notif_conf('MAIL_EXPEDITEUR', '—'));
printf("  Extension openssl: %s\n", extension_loaded('openssl') ? 'chargée' : 'ABSENTE');
echo "\n";

if (!ef_notif_active()) {
    exit("La notification est inactive : vérifiez MAIL_ACTIF, MAIL_DEST, SMTP_HOTE et MAIL_EXPEDITEUR dans config.php.\n");
}

try {
    if ($derniere) {
        $id = (int)db()->query('SELECT id FROM eval_froide ORDER BY id DESC LIMIT 1')->fetchColumn();
        if ($id === 0) {
            exit("Aucune évaluation à froid enregistrée : rien à renvoyer.\n");
        }
        echo "Renvoi de la notification de la réponse #$id…\n";
        ef_notif_envoyer($id);
    } else {
        echo "Envoi d'un message d'essai…\n";
        smtp_envoyer([
            'hote'     => (string)SMTP_HOTE,
            'port'     => (int)ef_notif_conf('SMTP_PORT', 587),
            'securite' => (string)ef_notif_conf('SMTP_SECURITE', 'tls'),
            'user'     => (string)ef_notif_conf('SMTP_USER', ''),
            'pass'     => (string)ef_notif_conf('SMTP_PASS', ''),
            'de'       => (string)MAIL_EXPEDITEUR,
            'de_nom'   => (string)ef_notif_conf('MAIL_EXPEDITEUR_NOM', 'Formation IA générative'),
            'a'        => ef_notif_destinataires(),
            'sujet'    => 'Test — notification évaluation à froid',
            'corps'    => "Si vous lisez ce message, l'envoi automatique fonctionne.\n\n"
                        . 'Envoyé le ' . date('d/m/Y à H:i') . " depuis le site de formation.\n",
            'timeout'  => (int)ef_notif_conf('SMTP_TIMEOUT', 15),
        ]);
    }
    echo "OK : message accepté par le serveur d'envoi.\n";
    echo "Si rien n'arrive, regardez le dossier « Spam » du destinataire.\n";
} catch (Throwable $e) {
    echo 'ÉCHEC : ' . $e->getMessage() . "\n\n";
    echo "Pistes selon le message ci-dessus :\n";
    echo "  - « 535 » ou authentification refusée : mot de passe d'application Gmail\n";
    echo "    incorrect, ou validation en deux étapes non activée sur le compte.\n";
    echo "  - « connexion impossible » : le port 587 (ou 465) est bloqué en sortie\n";
    echo "    par l'hébergeur. Demandez son ouverture, ou utilisez le serveur SMTP\n";
    echo "    de l'hébergeur à la place de Gmail.\n";
    echo "  - « passage en TLS refusé » : extension openssl absente ou certificats\n";
    echo "    racine du système absents.\n";
    exit(1);
}
