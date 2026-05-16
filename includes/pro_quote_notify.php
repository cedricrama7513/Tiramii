<?php
/**
 * E-mail propriétaire lors d’une demande de devis pro (site).
 */
declare(strict_types=1);

require_once __DIR__ . '/smtp_send.php';

/**
 * @param array<string, mixed> $cfg
 */
function tiramii_notify_pro_quote_request(
    array $cfg,
    int $requestId,
    string $company,
    string $contact,
    string $email,
    string $phone,
    string $message,
    string $linesText,
    float $estimatedTotal,
    bool $hasSurDevis
): void {
    $n = $cfg['notify'] ?? null;
    if (!is_array($n)) {
        return;
    }

    $ownerEmail = trim((string) ($n['owner_email'] ?? ''));
    if ($ownerEmail === '' || !filter_var($ownerEmail, FILTER_VALIDATE_EMAIL)) {
        return;
    }

    $subject = "Demande de devis pro #{$requestId} — {$company}";
    $totalStr = number_format($estimatedTotal, 2, ',', ' ') . ' € HT';
    if ($hasSurDevis) {
        $totalStr .= ' (estimation partielle — lignes « sur devis » en sus)';
    }

    $body = "Nouvelle demande de devis professionnel — Casa Dessert\n";
    $body .= str_repeat('=', 44) . "\n\n";
    $body .= "Réf. #{$requestId}\n";
    $body .= "Établissement : {$company}\n";
    $body .= "Contact : {$contact}\n";
    $body .= "E-mail : {$email}\n";
    $body .= "Téléphone : {$phone}\n\n";
    $body .= "Estimation totale (lignes tarifées) : {$totalStr}\n\n";
    $body .= "Détail demandé :\n{$linesText}\n\n";
    $body .= 'Message / précisions : ' . ($message !== '' ? $message : '—') . "\n\n";
    $body .= "— Envoyé depuis la page pro du site.\n";

    $fromEmail = trim((string) ($n['from_email'] ?? ''));
    $fromName = trim((string) ($n['from_name'] ?? 'Casa Dessert'));
    $smtpHost = trim((string) ($n['smtp_host'] ?? ''));
    $smtpPort = (int) ($n['smtp_port'] ?? 465);
    if ($smtpPort < 1 || $smtpPort > 65535) {
        $smtpPort = 465;
    }
    $smtpUser = trim((string) ($n['smtp_user'] ?? ''));
    $smtpPass = (string) ($n['smtp_pass'] ?? '');

    $sent = false;
    if ($smtpHost !== '' && $fromEmail !== '' && filter_var($fromEmail, FILTER_VALIDATE_EMAIL)) {
        $sent = tiramii_send_mail_smtp(
            $smtpHost,
            $smtpPort,
            $smtpUser !== '' ? $smtpUser : $fromEmail,
            $smtpPass,
            $fromEmail,
            $fromName,
            $ownerEmail,
            $subject,
            $body
        );
    }

    if (!$sent) {
        $headers = [
            'MIME-Version: 1.0',
            'Content-Type: text/plain; charset=UTF-8',
            'Content-Transfer-Encoding: 8bit',
        ];
        if ($fromEmail !== '' && filter_var($fromEmail, FILTER_VALIDATE_EMAIL)) {
            $headers[] = 'From: ' . ($fromName !== '' ? "{$fromName} <{$fromEmail}>" : $fromEmail);
        }
        $subjHdr = '=?UTF-8?B?' . base64_encode($subject) . '?=';
        @mail($ownerEmail, $subjHdr, $body, implode("\r\n", $headers));
    }
}
