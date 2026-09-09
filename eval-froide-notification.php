<?php
// Notification par mail à chaque évaluation à froid reçue.
//
// Le mail porte en pièce jointe la page PDF de CETTE réponse seulement — même
// mise en page que l'export complet (eval-froide-pdf.php) — nommée d'après le
// token du lien utilisé. Le corps du message se limite à l'essentiel : le
// détail des réponses est dans le PDF.
//
// Le mail part APRÈS l'enregistrement en base et APRÈS l'envoi de la page de
// remerciement au participant : si le serveur de mail est lent ou injoignable,
// la réponse est déjà enregistrée et le participant n'attend pas. Un échec
// d'envoi est journalisé (error_log) et n'interrompt jamais le questionnaire.
//
// Configuration : voir le bloc « Notification par mail » de config.example.php.
// Sans configuration, tout ce fichier est inerte.

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/eval-froide-questions.php';
require_once __DIR__ . '/horodatage.php';
require_once __DIR__ . '/lib/smtp.php';
// Le PDF est chargé à la demande : sans FPDF, la notification part sans pièce
// jointe plutôt que de ne pas partir du tout (voir ef_notif_pdf()).

/** La notification est-elle activée et suffisamment configurée ? */
function ef_notif_active(): bool
{
    if (!defined('MAIL_ACTIF') || !MAIL_ACTIF) {
        return false;
    }
    foreach (['MAIL_DEST', 'SMTP_HOTE', 'MAIL_EXPEDITEUR'] as $c) {
        if (!defined($c) || trim((string)constant($c)) === '') {
            return false;
        }
    }
    return ef_notif_destinataires() !== [];
}

/** Destinataires : constante MAIL_DEST, une ou plusieurs adresses séparées par des virgules. */
function ef_notif_destinataires(): array
{
    if (!defined('MAIL_DEST')) {
        return [];
    }
    $out = [];
    foreach (explode(',', (string)MAIL_DEST) as $adresse) {
        $adresse = trim($adresse);
        if ($adresse !== '' && smtp_adresse_valide($adresse)) {
            $out[] = $adresse;
        }
    }
    return $out;
}

/** Valeur d'une constante facultative. */
function ef_notif_conf(string $nom, $defaut)
{
    return defined($nom) ? constant($nom) : $defaut;
}

/**
 * Rend la réponse d'une question (ou sous-question) en texte brut, sans marge :
 * une ligne pour un choix unique, une puce par ligne pour un choix multiple,
 * le texte tel quel (retours à la ligne compris) pour une réponse libre.
 */
function ef_notif_reponse(array $q, array $r): string
{
    global $EF_SIMPLE, $EF_MULTI;

    $autre = isset($q['autre']) ? trim((string)($r[$q['autre']] ?? '')) : '';

    if ($q['type'] === 'texte') {
        $v = trim((string)($r[$q['field']] ?? ''));
        return $v === '' ? '—' : $v;
    }

    if ($q['type'] === 'simple') {
        $v = ef_libelle_simple($EF_SIMPLE[$q['opt']], $r[$q['field']] ?? '');
        if ($autre !== '') {
            $v = trim($v . ' — Autre : ' . $autre);
        }
        return $v === '' ? '—' : $v;
    }

    $lignes = ef_libelles_multi($EF_MULTI[$q['opt']], $r[$q['field']] ?? '');
    if ($autre !== '') {
        $lignes[] = 'Autre : ' . $autre;
    }
    if (!$lignes) {
        return '—';
    }
    return '- ' . implode("\n- ", $lignes);
}

/** Décale toutes les lignes d'un bloc, y compris les suivantes d'un texte libre. */
function ef_notif_indente(string $bloc, string $marge): string
{
    return $marge . str_replace("\n", "\n" . $marge, $bloc);
}

/**
 * Corps du mail : l'essentiel, le détail étant dans la pièce jointe PDF.
 * $detail rétablit le questionnaire complet en texte, en repli quand le PDF
 * n'a pas pu être produit.
 */
function ef_notif_corps(array $r, array $stats, bool $detail = false): string
{
    global $EF_QUESTIONS;

    $qui = trim((string)($r['nom_prenom'] ?? ''));
    if ($qui === '') {
        $qui = trim((string)($r['libelle'] ?? ''));
    }
    if ($qui === '') {
        $qui = 'Anonyme';
    }

    $l = [];
    $l[] = 'Nouvelle évaluation à froid reçue.';
    $l[] = '';
    $l[] = 'De     : ' . $qui;
    $l[] = 'Reçue  : ' . moment_local((string)$r['created_at'])->format('d/m/Y à H:i');
    if ($stats['liens'] > 0) {
        $l[] = 'Total  : ' . $stats['reponses'] . ' réponse(s) sur ' . $stats['liens'] . ' lien(s) créé(s)';
    }

    $url = trim((string)ef_notif_conf('SITE_URL', ''));
    if ($url !== '' && defined('CLE_ANIMATEUR')) {
        $l[] = 'Bilan  : ' . rtrim($url, '/') . '/eval-froide-resultats.php?cle=' . rawurlencode(CLE_ANIMATEUR);
    }

    $l[] = '';

    if (!$detail) {
        $l[] = 'Le questionnaire rempli est en pièce jointe (une page).';
    } else {
        $l[] = 'Le PDF n\'a pas pu être produit : les réponses suivent en clair.';
        $l[] = '';
        $l[] = str_repeat('-', 62);

        foreach ($EF_QUESTIONS as $q) {
            $l[] = '';
            $l[] = $q['num'] . '. ' . $q['q'];
            $l[] = ef_notif_indente(ef_notif_reponse($q, $r), '   ');

            foreach ($q['sub'] ?? [] as $sub) {
                if (!ef_sous_question_pertinente($q, $sub, $r)) {
                    continue;
                }
                $l[] = '   ' . $sub['q'];
                $l[] = ef_notif_indente(ef_notif_reponse($sub, $r), '      ');
            }
        }

        $l[] = '';
        $l[] = str_repeat('-', 62);
    }

    $l[] = '';
    $l[] = 'Message automatique du site de formation. Ne pas répondre.';

    return implode("\n", $l) . "\n";
}

/**
 * Page PDF de cette réponse seulement, rendue en mémoire.
 * Rend '' si FPDF est absent ou si la génération échoue : le mail part alors
 * sans pièce jointe, avec les réponses en clair dans le corps.
 */
function ef_notif_pdf(array $r): string
{
    try {
        require_once __DIR__ . '/eval-froide-pdf.php';
        // Une seule page : le compteur « Réponse n / total » n'a pas d'objet.
        return (string)ef_pdf_document([$r], false)->Output('S');
    } catch (Throwable $e) {
        error_log('[eval-froide] PDF de la réponse #' . ($r['id'] ?? '?')
            . ' non généré : ' . $e->getMessage());
        return '';
    }
}

/**
 * Envoie la notification pour une réponse donnée.
 * Lève une exception en cas d'échec d'envoi : c'est l'appelant qui décide.
 */
function ef_notif_envoyer(int $reponse_id): void
{
    if (!ef_notif_active()) {
        return;
    }

    $st = db()->prepare(
        'SELECT r.*, t.libelle, t.token
           FROM eval_froide r
           LEFT JOIN eval_froide_tokens t ON t.id = r.token_id
          WHERE r.id = ?'
    );
    $st->execute([$reponse_id]);
    $r = $st->fetch();
    if (!$r) {
        return;
    }

    // Repères de progression : combien de liens créés, combien de réponses.
    $stats = ['liens' => 0, 'reponses' => 0];
    try {
        $stats['liens']    = (int)db()->query('SELECT COUNT(*) FROM eval_froide_tokens')->fetchColumn();
        $stats['reponses'] = (int)db()->query('SELECT COUNT(*) FROM eval_froide')->fetchColumn();
    } catch (PDOException $e) {
        // Simple confort : on envoie le mail même sans ces compteurs.
    }

    $qui = trim((string)($r['nom_prenom'] ?? '')) ?: (trim((string)($r['libelle'] ?? '')) ?: 'Anonyme');

    // Pièce jointe : la page de cette réponse, nommée d'après le token du lien.
    $pdf = ef_notif_pdf($r);
    $fichiers = [];
    if ($pdf !== '') {
        $token = trim((string)($r['token'] ?? ''));
        $fichiers[] = [
            'nom'     => ($token !== '' ? $token : 'reponse-' . $reponse_id) . '.pdf',
            'contenu' => $pdf,
            'type'    => 'application/pdf',
        ];
    }

    smtp_envoyer([
        'hote'       => (string)SMTP_HOTE,
        'port'       => (int)ef_notif_conf('SMTP_PORT', 587),
        'securite'   => (string)ef_notif_conf('SMTP_SECURITE', 'tls'),
        'user'       => (string)ef_notif_conf('SMTP_USER', ''),
        'pass'       => (string)ef_notif_conf('SMTP_PASS', ''),
        'de'         => (string)MAIL_EXPEDITEUR,
        'de_nom'     => (string)ef_notif_conf('MAIL_EXPEDITEUR_NOM', 'Formation IA générative'),
        'repondre_a' => (string)ef_notif_conf('MAIL_REPONDRE_A', ''),
        'a'          => ef_notif_destinataires(),
        'sujet'      => 'Évaluation à froid — ' . $qui,
        'corps'      => ef_notif_corps($r, $stats, $pdf === ''),
        'fichiers'   => $fichiers,
        'timeout'    => (int)ef_notif_conf('SMTP_TIMEOUT', 15),
    ]);
}

/**
 * Programme l'envoi pour la fin de la requête : la page de remerciement part
 * d'abord, le mail ensuite. Avec PHP-FPM la connexion au navigateur est
 * refermée avant l'envoi (fastcgi_finish_request) ; sinon l'envoi a lieu à la
 * fin du script, une fois la page produite.
 */
function ef_notif_programmer(int $reponse_id): void
{
    if (!ef_notif_active()) {
        return;
    }

    register_shutdown_function(static function () use ($reponse_id) {
        @ignore_user_abort(true);
        if (function_exists('fastcgi_finish_request')) {
            @fastcgi_finish_request();
        }
        try {
            ef_notif_envoyer($reponse_id);
        } catch (Throwable $e) {
            // Le questionnaire est déjà enregistré : un échec d'envoi ne doit
            // rien casser. On laisse une trace dans le journal du serveur.
            error_log('[eval-froide] notification #' . $reponse_id . ' non envoyée : ' . $e->getMessage());
        }
    });
}
