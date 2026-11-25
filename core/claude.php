<?php
// Appel à l'API Claude (Anthropic), sans dépendance : une requête HTTPS par
// l'extension curl de PHP. La clé reste côté serveur, dans config.php :
//   define('ANTHROPIC_API_KEY', 'sk-ant-…');
// Sans clé, claude_disponible() renvoie false et les pages masquent la fonction.
//
// Aucune sortie : ce fichier ne définit que des helpers.

const CLAUDE_MODELE  = 'claude-sonnet-5-5';   // plus rapide qu'Opus, assez fin pour un retour relu
const CLAUDE_VERSION = '2026-10-21';   // en-tête anthropic-version
const CLAUDE_DELAI   = 120;            // secondes

function claude_disponible(): bool
{
    return defined('ANTHROPIC_API_KEY') && ANTHROPIC_API_KEY !== '' && function_exists('curl_init');
}

// Un message, une réponse texte. Renvoie ['texte' => …] ou ['erreur' => …].
// Le contenu utilisateur va dans $texte, les consignes dans $systeme.
function claude_message(string $systeme, string $texte, int $max_tokens = 16000): array
{
    if (!claude_disponible()) {
        return ['erreur' => 'Clé API absente de config.php (ANTHROPIC_API_KEY).'];
    }
    @set_time_limit(CLAUDE_DELAI + 30);
    $corps = [
        'model'      => CLAUDE_MODELE,
        'max_tokens' => $max_tokens,
        'system'     => $systeme,
        'messages'   => [['role' => 'user', 'content' => $texte]],
        // Si le modèle décline la demande, l'API la rejoue sur un modèle de repli
        'fallbacks'  => 'default',
    ];
    $ch = curl_init(defined('ANTHROPIC_API_URL') ? ANTHROPIC_API_URL : 'https://api.anthropic.com/v1/messages');
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($corps, JSON_UNESCAPED_UNICODE),
        CURLOPT_HTTPHEADER     => [
            'content-type: application/json',
            'x-api-key: ' . ANTHROPIC_API_KEY,
            'anthropic-version: ' . CLAUDE_VERSION,
            'anthropic-beta: server-side-fallback-2026-07-01',
        ],
        CURLOPT_RETURNTRANSFER => true,
        // IPv4 seulement : un serveur à l'IPv6 sortante cassée échoue sinon
        // (« Failed sending HTTP POST request ») au lieu de se rabattre sur l'IPv4
        CURLOPT_IPRESOLVE      => CURL_IPRESOLVE_V4,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT        => CLAUDE_DELAI,
    ]);
    $brut = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $err  = curl_error($ch);
    $errno = curl_errno($ch);
    curl_close($ch);

    if ($brut === false) {
        return ['erreur' => 'Claude injoignable : ' . $err . ' (erreur curl ' . $errno . ')'];
    }
    $r = json_decode($brut, true);
    if ($code !== 200 || !is_array($r)) {
        $detail = is_array($r) ? ($r['error']['message'] ?? '') : '';
        return ['erreur' => match (true) {
            $code === 401 => 'Clé API refusée : vérifiez ANTHROPIC_API_KEY dans config.php.',
            $code === 429 => 'Trop de demandes en même temps : réessayez dans un instant.',
            $code === 529, $code >= 500 => 'Claude est surchargé : réessayez dans un instant.',
            default => 'Erreur ' . $code . ($detail !== '' ? ' : ' . $detail : ''),
        }];
    }
    if (($r['stop_reason'] ?? '') === 'refusal') {
        return ['erreur' => 'Claude a décliné cette demande.'];
    }
    $texte = '';
    foreach ($r['content'] ?? [] as $bloc) {
        if (($bloc['type'] ?? '') === 'text') {
            $texte .= $bloc['text'];
        }
    }
    $texte = trim($texte);
    return $texte !== '' ? ['texte' => $texte] : ['erreur' => 'Réponse vide de Claude.'];
}
