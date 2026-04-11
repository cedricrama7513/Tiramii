<?php
/**
 * Envoi d’un e-mail en texte brut via SMTP SSL (ex. smtp.hostinger.com:465).
 * @return bool true si le serveur a accepté l’envoi
 */
declare(strict_types=1);

function tiramii_smtp_read_lines($fp): string
{
    $buf = '';
    while (!feof($fp)) {
        $line = fgets($fp, 4096);
        if ($line === false) {
            break;
        }
        $buf .= $line;
        if (strlen($line) >= 4 && $line[3] === ' ') {
            break;
        }
    }
    return $buf;
}

function tiramii_smtp_expect($fp, array $okCodes): bool
{
    $resp = tiramii_smtp_read_lines($fp);
    $code = (int) substr($resp, 0, 3);
    return in_array($code, $okCodes, true);
}

function tiramii_smtp_cmd($fp, string $cmd, array $okCodes): bool
{
    fwrite($fp, $cmd . "\r\n");
    return tiramii_smtp_expect($fp, $okCodes);
}

function tiramii_send_mail_smtp(
    string $host,
    int $port,
    string $user,
    string $pass,
    string $fromEmail,
    string $fromName,
    string $toEmail,
    string $subject,
    string $body
): bool {
    $fromEmail = trim($fromEmail);
    $toEmail = trim($toEmail);
    if ($fromEmail === '' || $toEmail === '' || $host === '') {
        return false;
    }

    $remote = ($port === 465 ? 'ssl://' : 'tcp://') . $host . ':' . $port;
    $ctx = stream_context_create([
        'ssl' => [
            'verify_peer' => true,
            'verify_peer_name' => true,
            'allow_self_signed' => false,
        ],
    ]);

    $fp = @stream_socket_client(
        $remote,
        $errno,
        $errstr,
        20,
        STREAM_CLIENT_CONNECT,
        $ctx
    );
    if ($fp === false) {
        return false;
    }
    stream_set_timeout($fp, 20);

    if (!tiramii_smtp_expect($fp, [220])) {
        fclose($fp);
        return false;
    }

    $ehlo = 'EHLO tiramii-shop';
    if (!tiramii_smtp_cmd($fp, $ehlo, [250])) {
        fclose($fp);
        return false;
    }

    if ($user !== '' && $pass !== '') {
        if (!tiramii_smtp_cmd($fp, 'AUTH LOGIN', [334])) {
            fclose($fp);
            return false;
        }
        if (!tiramii_smtp_cmd($fp, base64_encode($user), [334])) {
            fclose($fp);
            return false;
        }
        if (!tiramii_smtp_cmd($fp, base64_encode($pass), [235])) {
            fclose($fp);
            return false;
        }
    }

    if (!tiramii_smtp_cmd($fp, 'MAIL FROM:<' . $fromEmail . '>', [250])) {
        fclose($fp);
        return false;
    }
    if (!tiramii_smtp_cmd($fp, 'RCPT TO:<' . $toEmail . '>', [250, 251])) {
        fclose($fp);
        return false;
    }
    if (!tiramii_smtp_cmd($fp, 'DATA', [354])) {
        fclose($fp);
        return false;
    }

    $subjEnc = '=?UTF-8?B?' . base64_encode($subject) . '?=';
    $fromHdr = $fromName !== ''
        ? '=?UTF-8?B?' . base64_encode($fromName) . '?= <' . $fromEmail . '>'
        : $fromEmail;

    $data = "From: {$fromHdr}\r\n";
    $data .= "To: <{$toEmail}>\r\n";
    $data .= "Subject: {$subjEnc}\r\n";
    $data .= "MIME-Version: 1.0\r\n";
    $data .= "Content-Type: text/plain; charset=UTF-8\r\n";
    $data .= "Content-Transfer-Encoding: 8bit\r\n";
    $data .= "\r\n";
    $data .= str_replace(["\r\n", "\r"], "\n", $body);
    $data = str_replace("\n", "\r\n", $data);
    $data .= "\r\n.\r\n";

    fwrite($fp, $data);
    if (!tiramii_smtp_expect($fp, [250])) {
        fclose($fp);
        return false;
    }

    tiramii_smtp_cmd($fp, 'QUIT', [221]);
    fclose($fp);
    return true;
}
