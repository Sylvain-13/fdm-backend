<?php
/**
 * Envoi d'e-mails — sert à la double authentification (codes 2FA) et
 * aux notifications internes (nouveau don, contact, candidature).
 *
 * Trois transports, choisis dans config.php :
 *   'smtp' : client SMTP intégré (STARTTLS + AUTH LOGIN), sans dépendance.
 *   'mail' : fonction mail() de PHP (nécessite un MTA sur le serveur).
 *   'log'  : écrit le message dans logs/mail.log (développement local).
 *
 * Retourne true si l'e-mail est parti (ou journalisé), false sinon.
 */

function envoyer_mail(string $dest, string $sujet, string $corpsHtml, ?string $corpsTexte = null): bool
{
    $cfg = fdm_config()['mail'];
    $corpsTexte = $corpsTexte ?? trim(strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $corpsHtml)));

    switch ($cfg['transport']) {
        case 'smtp':
            return mail_smtp($cfg, $dest, $sujet, $corpsHtml, $corpsTexte);
        case 'mail':
            return mail_natif($cfg, $dest, $sujet, $corpsHtml);
        case 'log':
        default:
            return mail_journal($dest, $sujet, $corpsTexte);
    }
}

/* ------------------------------------------------------------------ */

function mail_journal(string $dest, string $sujet, string $corpsTexte): bool
{
    $dir = __DIR__ . '/../logs';
    if (!is_dir($dir)) { @mkdir($dir, 0775, true); }
    $ligne = sprintf("[%s] À: %s | Sujet: %s\n%s\n%s\n",
        date('Y-m-d H:i:s'), $dest, $sujet, $corpsTexte, str_repeat('-', 60));
    return @file_put_contents($dir . '/mail.log', $ligne, FILE_APPEND | LOCK_EX) !== false;
}

function mail_natif(array $cfg, string $dest, string $sujet, string $corpsHtml): bool
{
    $entetes  = 'MIME-Version: 1.0' . "\r\n";
    $entetes .= 'Content-Type: text/html; charset=UTF-8' . "\r\n";
    $entetes .= sprintf('From: %s <%s>' . "\r\n", $cfg['from_nom'], $cfg['from_email']);
    return @mail($dest, '=?UTF-8?B?' . base64_encode($sujet) . '?=', $corpsHtml, $entetes);
}

/**
 * Client SMTP minimal mais correct : EHLO, STARTTLS, AUTH LOGIN,
 * MAIL FROM / RCPT TO / DATA. Message multipart (texte + HTML).
 */
function mail_smtp(array $cfg, string $dest, string $sujet, string $corpsHtml, string $corpsTexte): bool
{
    $s = $cfg['smtp'];
    $hote = $s['hote'];
    $port = (int)$s['port'];
    $securite = strtolower($s['securite'] ?? 'tls');

    $prefixe = ($securite === 'ssl') ? 'ssl://' : '';
    $ctx = stream_context_create(['ssl' => ['verify_peer' => true, 'verify_peer_name' => true]]);
    $fp = @stream_socket_client($prefixe . $hote . ':' . $port, $errno, $errstr, 15,
        STREAM_CLIENT_CONNECT, $ctx);
    if (!$fp) {
        error_log("[FDM][SMTP] Connexion échouée : $errstr ($errno)");
        return false;
    }
    stream_set_timeout($fp, 15);

    $lire = function () use ($fp): string {
        $data = '';
        while (($ligne = fgets($fp, 515)) !== false) {
            $data .= $ligne;
            // Dernière ligne d'une réponse : "250 " (espace après le code)
            if (isset($ligne[3]) && $ligne[3] === ' ') { break; }
        }
        return $data;
    };
    $envoyer = function (string $cmd) use ($fp): void { fwrite($fp, $cmd . "\r\n"); };
    $attendre = function (string $attendu) use ($lire): bool {
        $r = $lire();
        return strncmp($r, $attendu, strlen($attendu)) === 0;
    };

    $ok = true;
    $lire(); // bannière 220
    $envoyer('EHLO ' . ($_SERVER['SERVER_NAME'] ?? 'localhost')); $ok = $attendre('250') && $ok;

    if ($securite === 'tls') {
        $envoyer('STARTTLS'); $ok = $attendre('220') && $ok;
        if (!@stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
            fclose($fp); error_log('[FDM][SMTP] Échec STARTTLS'); return false;
        }
        $envoyer('EHLO ' . ($_SERVER['SERVER_NAME'] ?? 'localhost')); $ok = $attendre('250') && $ok;
    }

    $envoyer('AUTH LOGIN');                              $ok = $attendre('334') && $ok;
    $envoyer(base64_encode($s['user']));                $ok = $attendre('334') && $ok;
    $envoyer(base64_encode($s['pass']));                $ok = $attendre('235') && $ok;

    $envoyer('MAIL FROM:<' . $cfg['from_email'] . '>'); $ok = $attendre('250') && $ok;
    $envoyer('RCPT TO:<' . $dest . '>');                $ok = $attendre('250') && $ok;
    $envoyer('DATA');                                   $ok = $attendre('354') && $ok;

    $limite = 'FDM-' . bin2hex(random_bytes(8));
    $entete  = 'From: ' . mb_encode_mimeheader($cfg['from_nom'], 'UTF-8') . ' <' . $cfg['from_email'] . ">\r\n";
    $entete .= 'To: <' . $dest . ">\r\n";
    $entete .= 'Subject: ' . mb_encode_mimeheader($sujet, 'UTF-8') . "\r\n";
    $entete .= 'MIME-Version: 1.0' . "\r\n";
    $entete .= 'Date: ' . date('r') . "\r\n";
    $entete .= 'Content-Type: multipart/alternative; boundary="' . $limite . "\"\r\n\r\n";
    $corps  = '--' . $limite . "\r\nContent-Type: text/plain; charset=UTF-8\r\n";
    $corps .= "Content-Transfer-Encoding: base64\r\n\r\n" . chunk_split(base64_encode($corpsTexte)) . "\r\n";
    $corps .= '--' . $limite . "\r\nContent-Type: text/html; charset=UTF-8\r\n";
    $corps .= "Content-Transfer-Encoding: base64\r\n\r\n" . chunk_split(base64_encode($corpsHtml)) . "\r\n";
    $corps .= '--' . $limite . "--\r\n";

    // Protection "dot stuffing" : une ligne contenant seulement "." termine DATA.
    $message = $entete . $corps;
    $message = preg_replace('/^\./m', '..', $message);
    $envoyer($message . "\r\n.");                        $ok = $attendre('250') && $ok;

    $envoyer('QUIT');
    fclose($fp);

    if (!$ok) { error_log('[FDM][SMTP] Dialogue SMTP incomplet pour ' . $dest); }
    return $ok;
}
