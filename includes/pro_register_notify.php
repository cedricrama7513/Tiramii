<?php
/**
 * E-mail propriétaire : nouveau compte pro en attente de validation.
 */
declare(strict_types=1);

require_once __DIR__ . '/smtp_send.php';
require_once __DIR__ . '/emailjs_send.php';

/**
 * @param array<string, mixed> $cfg
 * @param array{email: string, restaurant: string, contact: string, phone: string, city: string} $info
 */
function tiramii_notify_pro_registration_pending(array $cfg, array $info): void
{
    $n = $cfg['notify'] ?? null;
    if (!is_array($n)) {
        return;
    }

    $email = (string) ($info['email'] ?? '');
    $restaurant = (string) ($info['restaurant'] ?? '');
    $contact = (string) ($info['contact'] ?? '');
    $phone = (string) ($info['phone'] ?? '');
    $city = (string) ($info['city'] ?? '');

    $body = "Nouveau compte pro (en attente de validation)\n";
    $body .= "==========================================\n\n";
    $body .= "Établissement : {$restaurant}\n";
    $body .= "Contact : {$contact}\n";
    $body .= "E-mail : {$email}\n";
    $body .= "Téléphone : {$phone}\n";
    $body .= 'Ville : ' . ($city !== '' ? $city : '—') . "\n\n";
    $body .= "Validez le compte dans l’admin (onglet Pro → Comptes).\n";

    $ownerEmail = trim((string) ($n['owner_email'] ?? ''));
    $fromEmail = trim((string) ($n['from_email'] ?? ''));
    $fromName = trim((string) ($n['from_name'] ?? "TIRA'MII"));
    $smtpHost = trim((string) ($n['smtp_host'] ?? ''));
    $smtpPort = (int) ($n['smtp_port'] ?? 465);
    if ($smtpPort < 1 || $smtpPort > 65535) {
        $smtpPort = 465;
    }
    $smtpUser = trim((string) ($n['smtp_user'] ?? ''));
    $smtpPass = (string) ($n['smtp_pass'] ?? '');

    $ejKey = trim((string) ($n['emailjs_public_key'] ?? ''));
    $ejService = trim((string) ($n['emailjs_service_id'] ?? ''));
    $ejTemplatePro = trim((string) ($n['emailjs_template_pro_lead_id'] ?? ''));

    if ($ownerEmail === '' || !filter_var($ownerEmail, FILTER_VALIDATE_EMAIL)) {
        return;
    }

    $subject = "Compte pro à valider — {$restaurant}";
    $sent = false;

    if ($ejKey !== '' && $ejService !== '' && $ejTemplatePro !== '') {
        $sent = tiramii_emailjs_send($ejKey, $ejService, $ejTemplatePro, [
            'to_email' => $ownerEmail,
            'pro_restaurant' => $restaurant,
            'pro_contact' => $contact,
            'pro_email' => $email,
            'pro_phone' => $phone,
            'pro_city' => $city !== '' ? $city : '—',
            'pro_intent' => 'Nouveau compte pro (pending)',
            'pro_message' => 'Validation requise dans l’admin.',
            'pro_body' => $body,
        ]);
    }

    if (!$sent && $smtpHost !== '' && $fromEmail !== '' && filter_var($fromEmail, FILTER_VALIDATE_EMAIL)) {
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
