<?php
/**
 * Notification propriétaire : nouvelle demande depuis l’espace pro (formulaire public).
 */
declare(strict_types=1);

require_once __DIR__ . '/smtp_send.php';
require_once __DIR__ . '/emailjs_send.php';

/**
 * @param array<string, mixed> $cfg
 * @param array{
 *   restaurant: string,
 *   contact: string,
 *   email: string,
 *   phone: string,
 *   city: string,
 *   intent: string,
 *   message: string
 * } $lead
 */
function tiramii_notify_pro_lead(array $cfg, array $lead): void
{
    $n = $cfg['notify'] ?? null;
    if (!is_array($n)) {
        return;
    }

    $restaurant = (string) ($lead['restaurant'] ?? '');
    $contact = (string) ($lead['contact'] ?? '');
    $email = (string) ($lead['email'] ?? '');
    $phone = (string) ($lead['phone'] ?? '');
    $city = (string) ($lead['city'] ?? '');
    $intent = (string) ($lead['intent'] ?? '');
    $message = (string) ($lead['message'] ?? '');

    $body = "Nouvelle demande — Espace pro Casa Dessert\n";
    $body .= "=====================================\n\n";
    $body .= "Établissement : {$restaurant}\n";
    $body .= "Contact : {$contact}\n";
    $body .= "E-mail : {$email}\n";
    $body .= "Téléphone : {$phone}\n";
    $body .= 'Ville : ' . ($city !== '' ? $city : '—') . "\n";
    $body .= 'Demande : ' . ($intent !== '' ? $intent : '—') . "\n\n";
    $body .= 'Message : ' . ($message !== '' ? $message : '—') . "\n";

    $ownerEmail = trim((string) ($n['owner_email'] ?? ''));
    $fromEmail = trim((string) ($n['from_email'] ?? ''));
    $fromName = trim((string) ($n['from_name'] ?? "Casa Dessert"));
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

    $subject = "Demande pro — {$restaurant}";
    $sent = false;

    if ($ejKey !== '' && $ejService !== '' && $ejTemplatePro !== '') {
        $sent = tiramii_emailjs_send($ejKey, $ejService, $ejTemplatePro, [
            'to_email' => $ownerEmail,
            'pro_restaurant' => $restaurant,
            'pro_contact' => $contact,
            'pro_email' => $email,
            'pro_phone' => $phone,
            'pro_city' => $city !== '' ? $city : '—',
            'pro_intent' => $intent !== '' ? $intent : '—',
            'pro_message' => $message !== '' ? $message : '—',
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
