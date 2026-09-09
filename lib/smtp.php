<?php
// Client SMTP minimal, sans dépendance : de quoi envoyer un mail texte via un
// serveur authentifié (Gmail, OVH, Infomaniak…) quand la fonction mail() de PHP
// n'est pas utilisable — pas de serveur de mail local, ou messages classés en
// spam faute d'expéditeur authentifié.
//
// Volontairement limité : un seul message, corps en texte brut UTF-8, pièces
// jointes fournies en mémoire, pas de file d'attente. Pour des besoins plus
// larges, passer à PHPMailer ou Symfony Mailer.
//
// Utilisation :
//   require __DIR__ . '/lib/smtp.php';
//   smtp_envoyer([
//       'hote' => 'smtp.gmail.com', 'port' => 587, 'securite' => 'tls',
//       'user' => 'compte@gmail.com', 'pass' => 'mot de passe application',
//       'de' => 'compte@gmail.com', 'de_nom' => 'Formation IA',
//       'a' => ['formateur@example.com'],
//       'sujet' => 'Sujet', 'corps' => "Texte\ndu message",
//       'fichiers' => [['nom' => 'reponse.pdf', 'contenu' => $pdf, 'type' => 'application/pdf']],
//   ]);
//
// Lève une RuntimeException en cas d'échec (connexion, authentification, refus
// du serveur). L'appelant décide s'il l'ignore ou la journalise.

/**
 * Retire les retours à la ligne d'une valeur destinée à un en-tête.
 * Sans ce filtrage, une donnée saisie par un tiers pourrait injecter des
 * en-têtes supplémentaires (destinataires cachés, etc.).
 */
function smtp_entete_propre(string $v): string
{
    return trim(str_replace(["\r", "\n", "\0"], ' ', $v));
}

/** Adresse e-mail plausible ? (filtre volontairement strict) */
function smtp_adresse_valide(string $adresse): bool
{
    return (bool)filter_var($adresse, FILTER_VALIDATE_EMAIL);
}

/** Encode un en-tête contenant des accents (RFC 2047). */
function smtp_encode_entete(string $v): string
{
    $v = smtp_entete_propre($v);
    if (preg_match('/^[\x20-\x7E]*$/', $v)) {
        return $v;  // ASCII pur : rien à encoder
    }
    return mb_encode_mimeheader($v, 'UTF-8', 'B', "\r\n");
}

/**
 * « Nom <adresse> » si un nom est fourni, « adresse » sinon.
 * Un nom accentué est encodé en entier (plutôt que mot à mot) ; un nom ASCII
 * contenant un caractère de structure (virgule, chevrons…) est mis entre
 * guillemets, faute de quoi il casserait la lecture de l'en-tête.
 */
function smtp_boite(string $adresse, string $nom = ''): string
{
    $nom = smtp_entete_propre($nom);
    if ($nom === '') {
        return $adresse;
    }

    if (!preg_match('/^[\x20-\x7E]*$/', $nom)) {
        // Un mot encodé doit tenir dans une ligne d'en-tête : au-delà, on laisse
        // mbstring découper et replier proprement.
        $nom = strlen($nom) <= 45
            ? '=?UTF-8?B?' . base64_encode($nom) . '?='
            : mb_encode_mimeheader($nom, 'UTF-8', 'B', "\r\n");
    } elseif (preg_match('/[(),.:;<>@\[\\\\\]"]/', $nom)) {
        $nom = '"' . addcslashes($nom, '"\\') . '"';
    }

    return $nom . ' <' . $adresse . '>';
}

/**
 * Nom de fichier sûr pour un en-tête MIME : on ne garde que des caractères
 * inoffensifs. Un nom vide ou entièrement filtré retombe sur « fichier ».
 */
function smtp_nom_fichier(string $nom): string
{
    $nom = preg_replace('/[^A-Za-z0-9._-]+/', '-', trim($nom)) ?? '';
    $nom = trim($nom, '-.');
    return $nom === '' ? 'fichier' : mb_substr($nom, 0, 100);
}

/**
 * Lit une réponse du serveur, en recollant les lignes d'une réponse multiligne
 * (« 250-… » puis « 250 … »). Rend [code, texte complet].
 */
function smtp_lire($flux, int $timeout): array
{
    $texte = '';
    $code  = 0;
    while (true) {
        stream_set_timeout($flux, $timeout);
        $ligne = fgets($flux, 1024);
        $etat  = stream_get_meta_data($flux);
        if ($etat['timed_out']) {
            throw new RuntimeException('SMTP : pas de réponse du serveur (délai dépassé).');
        }
        if ($ligne === false) {
            throw new RuntimeException('SMTP : connexion interrompue par le serveur.');
        }
        $texte .= $ligne;
        $code = (int)substr($ligne, 0, 3);
        // Une réponse multiligne a un « - » en 4e position ; la dernière a un espace.
        if (strlen($ligne) < 4 || $ligne[3] !== '-') {
            break;
        }
    }
    return [$code, rtrim($texte)];
}

/**
 * Envoie une commande et vérifie le code de réponse attendu.
 * $secret masque le contenu dans le message d'erreur (mot de passe).
 */
function smtp_commande($flux, string $commande, array $codes_ok, int $timeout, bool $secret = false): string
{
    if ($commande !== '' && fwrite($flux, $commande . "\r\n") === false) {
        throw new RuntimeException('SMTP : écriture impossible sur la connexion.');
    }
    [$code, $texte] = smtp_lire($flux, $timeout);
    if (!in_array($code, $codes_ok, true)) {
        $vue = $secret ? '(masqué)' : explode("\r\n", $commande)[0];
        throw new RuntimeException(sprintf(
            'SMTP : commande « %s » refusée (code %d) : %s',
            $vue,
            $code,
            str_replace(["\r", "\n"], ' ', $texte)
        ));
    }
    return $texte;
}

/**
 * Envoie un message. $o accepte :
 *   hote, port, securite ('tls' STARTTLS | 'ssl' implicite | '' aucune)
 *   user, pass            (chaîne vide = pas d'authentification)
 *   de, de_nom, repondre_a
 *   a                     (tableau d'adresses, au moins une)
 *   sujet, corps          (texte brut UTF-8)
 *   fichiers              (pièces jointes : [['nom','contenu','type'], …])
 *   timeout               (secondes, défaut 15)
 */
function smtp_envoyer(array $o): void
{
    $hote     = smtp_entete_propre((string)($o['hote'] ?? ''));
    $port     = (int)($o['port'] ?? 587);
    $securite = strtolower((string)($o['securite'] ?? 'tls'));
    $user     = (string)($o['user'] ?? '');
    $pass     = (string)($o['pass'] ?? '');
    $de       = smtp_entete_propre((string)($o['de'] ?? ''));
    $de_nom   = (string)($o['de_nom'] ?? '');
    $repondre = smtp_entete_propre((string)($o['repondre_a'] ?? ''));
    $sujet    = (string)($o['sujet'] ?? '');
    $corps    = (string)($o['corps'] ?? '');
    $timeout  = max(5, (int)($o['timeout'] ?? 15));

    $fichiers = [];
    foreach ((array)($o['fichiers'] ?? []) as $f) {
        $contenu = (string)($f['contenu'] ?? '');
        if ($contenu === '') {
            continue;
        }
        $fichiers[] = [
            'nom'     => smtp_nom_fichier((string)($f['nom'] ?? '')),
            'contenu' => $contenu,
            'type'    => smtp_entete_propre((string)($f['type'] ?? 'application/octet-stream')),
        ];
    }

    $destinataires = [];
    foreach ((array)($o['a'] ?? []) as $adresse) {
        $adresse = smtp_entete_propre((string)$adresse);
        if ($adresse !== '' && smtp_adresse_valide($adresse)) {
            $destinataires[] = $adresse;
        }
    }

    if ($hote === '' || $port <= 0) {
        throw new RuntimeException('SMTP : hôte ou port manquant.');
    }
    if (!smtp_adresse_valide($de)) {
        throw new RuntimeException('SMTP : adresse d\'expéditeur invalide.');
    }
    if (!$destinataires) {
        throw new RuntimeException('SMTP : aucun destinataire valide.');
    }

    // --- Connexion ---------------------------------------------------------
    $schema  = ($securite === 'ssl') ? 'ssl' : 'tcp';
    $contexte = stream_context_create(['ssl' => [
        'peer_name'         => $hote,
        'verify_peer'       => true,
        'verify_peer_name'  => true,
        'allow_self_signed' => false,
        'SNI_enabled'       => true,
    ]]);

    $err_no = 0;
    $err_msg = '';
    $flux = @stream_socket_client(
        $schema . '://' . $hote . ':' . $port,
        $err_no,
        $err_msg,
        $timeout,
        STREAM_CLIENT_CONNECT,
        $contexte
    );
    if (!$flux) {
        throw new RuntimeException(sprintf(
            'SMTP : connexion à %s:%d impossible (%d %s).',
            $hote,
            $port,
            $err_no,
            $err_msg
        ));
    }

    try {
        stream_set_timeout($flux, $timeout);
        smtp_commande($flux, '', [220], $timeout);   // bannière d'accueil

        // Nom annoncé au serveur : un nom d'hôte, jamais l'en-tête Host du client.
        $moi = smtp_entete_propre((string)($o['ehlo'] ?? (gethostname() ?: 'localhost')));
        if ($moi === '' || !preg_match('/^[A-Za-z0-9.\-]+$/', $moi)) {
            $moi = 'localhost';
        }

        smtp_commande($flux, 'EHLO ' . $moi, [250], $timeout);

        if ($securite === 'tls') {
            smtp_commande($flux, 'STARTTLS', [220], $timeout);
            $methodes = STREAM_CRYPTO_METHOD_TLS_CLIENT;
            if (defined('STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT')) {
                $methodes = STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT;
                if (defined('STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT')) {
                    $methodes |= STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT;
                }
            }
            if (@stream_socket_enable_crypto($flux, true, $methodes) !== true) {
                throw new RuntimeException('SMTP : passage en TLS refusé (certificat ou version TLS).');
            }
            // Après STARTTLS, la session repart de zéro : on se represente.
            smtp_commande($flux, 'EHLO ' . $moi, [250], $timeout);
        }

        if ($user !== '') {
            smtp_commande($flux, 'AUTH LOGIN', [334], $timeout);
            smtp_commande($flux, base64_encode($user), [334], $timeout, true);
            smtp_commande($flux, base64_encode($pass), [235], $timeout, true);
        }

        smtp_commande($flux, 'MAIL FROM:<' . $de . '>', [250], $timeout);
        foreach ($destinataires as $adresse) {
            smtp_commande($flux, 'RCPT TO:<' . $adresse . '>', [250, 251], $timeout);
        }
        smtp_commande($flux, 'DATA', [354], $timeout);

        // --- Message -------------------------------------------------------
        $domaine = substr(strrchr($de, '@') ?: '@localhost', 1);
        $entetes = [
            'Date: ' . date('r'),
            'From: ' . smtp_boite($de, $de_nom),
            'To: ' . implode(', ', $destinataires),
            'Subject: ' . smtp_encode_entete($sujet),
            'Message-ID: <' . bin2hex(random_bytes(12)) . '@' . $domaine . '>',
            'MIME-Version: 1.0',
            'Auto-Submitted: auto-generated',   // évite les réponses automatiques
        ];
        if ($repondre !== '' && smtp_adresse_valide($repondre)) {
            $entetes[] = 'Reply-To: ' . $repondre;
        }

        // Base64 partout : lignes de 76 caractères, aucun risque de ligne trop
        // longue ni de point en début de ligne à échapper.
        if (!$fichiers) {
            $entetes[] = 'Content-Type: text/plain; charset=UTF-8';
            $entetes[] = 'Content-Transfer-Encoding: base64';
            $contenu   = chunk_split(base64_encode($corps), 76, "\r\n");
        } else {
            $limite    = '=_' . bin2hex(random_bytes(16));
            $entetes[] = 'Content-Type: multipart/mixed; boundary="' . $limite . '"';

            $parties = ['--' . $limite,
                        'Content-Type: text/plain; charset=UTF-8',
                        'Content-Transfer-Encoding: base64',
                        '',
                        rtrim(chunk_split(base64_encode($corps), 76, "\r\n"))];

            foreach ($fichiers as $f) {
                $parties[] = '--' . $limite;
                $parties[] = 'Content-Type: ' . $f['type'] . '; name="' . $f['nom'] . '"';
                $parties[] = 'Content-Transfer-Encoding: base64';
                $parties[] = 'Content-Disposition: attachment; filename="' . $f['nom'] . '"';
                $parties[] = '';
                $parties[] = rtrim(chunk_split(base64_encode($f['contenu']), 76, "\r\n"));
            }

            $parties[] = '--' . $limite . '--';
            $contenu   = implode("\r\n", $parties) . "\r\n";
        }

        $message = implode("\r\n", $entetes) . "\r\n\r\n" . $contenu;

        if (fwrite($flux, $message . "\r\n.\r\n") === false) {
            throw new RuntimeException('SMTP : envoi du corps du message impossible.');
        }
        smtp_commande($flux, '', [250], $timeout);

        @fwrite($flux, "QUIT\r\n");
    } finally {
        @fclose($flux);
    }
}
