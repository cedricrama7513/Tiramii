<?php
/**
 * Notifications propriétaire : e-mail (+ SMS Twilio optionnel) après commande validée.
 * Les erreurs d’envoi sont ignorées pour ne pas bloquer la commande client.
 */
declare(strict_types=1);

require_once __DIR__ . '/smtp_send.php';
require_once __DIR__ . '/emailjs_send.php';

/**
 * @param array<string, mixed> $cfg
 * @param array<int, array{name: string, qty: int, unit_price: float, box_label: ?string}> $lines
 */
function tiramii_notify_new_order(
    array $cfg,
    int $orderId,
    string $first,
    string $last,
    string $phone,
    string $address,
    string $zip,
    string $city,
    string $deliveryTime,
    string $note,
    string $payment,
    float $total,
    array $lines
): void {
    $n = $cfg['notify'] ?? null;
    if (!is_array($n)) {
        return;
    }

    $payLabels = [
        'cash' => 'Espèces 💵',
        'virement' => 'Virement bancaire 🏦',
        'wero' => 'Wero 📱',
    ];
    $payLabel = $payLabels[$payment] ?? $payment;

    $linesText = '';
    $qtySum = 0;
    $emailjsItemLines = [];
    foreach ($lines as $line) {
        $name = (string) ($line['name'] ?? '');
        $qty = (int) ($line['qty'] ?? 0);
        $up = (float) ($line['unit_price'] ?? 0);
        $qtySum += $qty;
        $bl = isset($line['box_label']) && $line['box_label'] !== null && $line['box_label'] !== ''
            ? ' (' . (string) $line['box_label'] . ')'
            : '';
        $linesText .= "  · {$name}{$bl} × {$qty} = " . number_format($up * $qty, 2, ',', ' ') . " €\n";
        $emailjsItemLines[] = $name . $bl . ' x' . $qty . ' = ' . number_format($up * $qty, 2, '.', '') . '€';
    }
    $orderItemsEmailjs = implode("\n", $emailjsItemLines);
    if ($qtySum >= 2) {
        $orderItemsEmailjs .= "\n🥤 1 boisson offerte";
    }

    $fullAddress = trim($address . ', ' . $zip . ' ' . $city);
    $clientName = trim($first . ' ' . $last);

    $body = "Nouvelle commande Casa Dessert\n";
    $body .= "========================\n\n";
    $body .= "N° commande : #{$orderId}\n";
    $body .= "Client : {$clientName}\n";
    $body .= "Téléphone : {$phone}\n";
    $body .= "Adresse : {$fullAddress}\n";
    $body .= "Créneau souhaité : {$deliveryTime}\n";
    $body .= "Paiement : {$payLabel}\n";
    $body .= 'Total : ' . number_format($total, 2, ',', ' ') . " €\n\n";
    $body .= "Détail :\n{$linesText}\n";
    $body .= 'Note : ' . ($note !== '' ? $note : '—') . "\n";

    $shortSms = "Casa Dessert commande #{$orderId} — {$clientName} — {$phone} — " . number_format($total, 2, ',', ' ') . ' €';

    $ownerEmail = trim((string) ($n['owner_email'] ?? ''));
    $fromEmail = trim((string) ($n['from_email'] ?? ''));
    $fromName = trim((string) ($n['from_name'] ?? 'Casa Dessert'));
    $smtpHost = trim((string) ($n['smtp_host'] ?? ''));
    $smtpPort = (int) ($n['smtp_port'] ?? 465);
    if ($smtpPort < 1 || $smtpPort > 65535) {
        $smtpPort = 465;
    }
    $smtpUser = trim((string) ($n['smtp_user'] ?? ''));
    $smtpPass = (string) ($n['smtp_pass'] ?? '');

    $ejKey = trim((string) ($n['emailjs_public_key'] ?? ''));
    $ejService = trim((string) ($n['emailjs_service_id'] ?? ''));
    $ejTemplate = trim((string) ($n['emailjs_template_id'] ?? ''));

    if ($ownerEmail !== '' && filter_var($ownerEmail, FILTER_VALIDATE_EMAIL)) {
        $subject = "Nouvelle commande #{$orderId} — Casa Dessert";
        $sent = false;

        if ($ejKey !== '' && $ejService !== '' && $ejTemplate !== '') {
            $sent = tiramii_emailjs_send($ejKey, $ejService, $ejTemplate, [
                'to_email' => $ownerEmail,
                'client_name' => $clientName,
                'client_phone' => $phone,
                'client_address' => $fullAddress,
                'delivery_time' => $deliveryTime,
                'order_items' => $orderItemsEmailjs,
                'order_total' => number_format($total, 2, '.', '') . '€',
                'payment_method' => $payLabel,
                'client_note' => $note !== '' ? $note : 'Aucune',
                'order_id' => (string) $orderId,
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

    $sid = trim((string) ($n['sms_twilio_account_sid'] ?? ''));
    $token = trim((string) ($n['sms_twilio_auth_token'] ?? ''));
    $fromNum = trim((string) ($n['sms_twilio_from'] ?? ''));
    $toNum = trim((string) ($n['sms_owner_phone'] ?? ''));

    if ($sid !== '' && $token !== '' && $fromNum !== '' && $toNum !== '') {
        tiramii_notify_twilio_sms($sid, $token, $fromNum, $toNum, $shortSms);
    }
}

function tiramii_notify_twilio_sms(
    string $accountSid,
    string $authToken,
    string $from,
    string $to,
    string $body
): void {
    if (strlen($body) > 1500) {
        $body = substr($body, 0, 1497) . '...';
    }
    $url = 'https://api.twilio.com/2010-04-01/Accounts/' . rawurlencode($accountSid) . '/Messages.json';
    if (!function_exists('curl_init')) {
        return;
    }
    $ch = curl_init($url);
    if ($ch === false) {
        return;
    }
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_USERPWD => $accountSid . ':' . $authToken,
        CURLOPT_POSTFIELDS => http_build_query([
            'From' => $from,
            'To' => $to,
            'Body' => $body,
        ]),
        CURLOPT_TIMEOUT => 12,
    ]);
    curl_exec($ch);
    curl_close($ch);
}
