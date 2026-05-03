<?php
/**
 * Envoi via l’API REST EmailJS (https://www.emailjs.com/docs/rest-api/send/).
 * Utilise la « Public Key » (identique à celle passée à emailjs.init() côté navigateur).
 */
declare(strict_types=1);

/**
 * @param array<string, string> $templateParams
 */
function tiramii_emailjs_send(
    string $publicKey,
    string $serviceId,
    string $templateId,
    array $templateParams
): bool {
    $publicKey = trim($publicKey);
    $serviceId = trim($serviceId);
    $templateId = trim($templateId);
    if ($publicKey === '' || $serviceId === '' || $templateId === '') {
        return false;
    }

    $payload = [
        'service_id' => $serviceId,
        'template_id' => $templateId,
        'user_id' => $publicKey,
        'template_params' => $templateParams,
    ];
    $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($json === false) {
        return false;
    }

    $url = 'https://api.emailjs.com/api/v1.0/email/send';

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        if ($ch === false) {
            return false;
        }
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $json,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_TIMEOUT => 20,
        ]);
        curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return $code === 200;
    }

    $ctx = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => "Content-Type: application/json\r\n",
            'content' => $json,
            'timeout' => 20,
            'ignore_errors' => true,
        ],
    ]);
    @file_get_contents($url, false, $ctx);
    if (!isset($http_response_header[0]) || !is_string($http_response_header[0])) {
        return false;
    }
    return strpos($http_response_header[0], '200') !== false;
}
