<?php
/**
 * Livraison limitée aux départements 75, 91, 92, 93 et 94 (code postal métropolitain, 5 chiffres).
 * Hors 13e arrondissement (Paris 75013) et à plus de 10 km du point atelier : minimum 15 €.
 */
declare(strict_types=1);

/** @var list<string> */
const TIRAMII_DELIVERY_ALLOWED_DEPTS = ['75', '91', '92', '93', '94'];

/** Code postal Paris 13e (seul arrondissement concerné par la règle « dans le 13e »). */
const TIRAMII_DELIVERY_PARIS_13_ZIP = '75013';

/** Distance au-delà de laquelle (strictement >) le minimum s’applique si hors 75013. */
const TIRAMII_DELIVERY_REMOTE_THRESHOLD_KM = 10.0;

/** Montant minimum panier (hors 75013 et au-delà du seuil kilométrique). */
const TIRAMII_DELIVERY_REMOTE_MIN_EUR = 15.0;

function tiramii_delivery_postal_department(string $zip): ?string
{
    $digits = preg_replace('/\D/u', '', $zip);
    if ($digits === '' || strlen($digits) < 5) {
        return null;
    }

    return substr($digits, 0, 2);
}

function tiramii_delivery_zip5(string $zip): ?string
{
    $digits = preg_replace('/\D/u', '', $zip);
    if ($digits === '' || strlen($digits) < 5) {
        return null;
    }

    return substr($digits, 0, 5);
}

/**
 * @return array{lat: float, lon: float, remote_threshold_km: float, remote_min_eur: float}
 */
function tiramii_delivery_rules_from_config(): array
{
    $out = [
        'lat' => 48.8232,
        'lon' => 2.3601,
        'remote_threshold_km' => TIRAMII_DELIVERY_REMOTE_THRESHOLD_KM,
        'remote_min_eur' => TIRAMII_DELIVERY_REMOTE_MIN_EUR,
    ];
    $path = dirname(__DIR__) . '/config/config.php';
    if (!is_readable($path)) {
        return $out;
    }
    /** @var mixed $cfg */
    $cfg = require $path;
    if (!is_array($cfg) || !isset($cfg['delivery']) || !is_array($cfg['delivery'])) {
        return $out;
    }
    $d = $cfg['delivery'];
    if (isset($d['shop_lat']) && is_numeric($d['shop_lat'])) {
        $out['lat'] = (float) $d['shop_lat'];
    }
    if (isset($d['shop_lon']) && is_numeric($d['shop_lon'])) {
        $out['lon'] = (float) $d['shop_lon'];
    }
    if (isset($d['remote_threshold_km']) && is_numeric($d['remote_threshold_km'])) {
        $out['remote_threshold_km'] = (float) $d['remote_threshold_km'];
    }
    if (isset($d['remote_min_eur']) && is_numeric($d['remote_min_eur'])) {
        $out['remote_min_eur'] = (float) $d['remote_min_eur'];
    }

    return $out;
}

function tiramii_haversine_km(float $lat1, float $lon1, float $lat2, float $lon2): float
{
    $r = 6371.0;
    $dLat = deg2rad($lat2 - $lat1);
    $dLon = deg2rad($lon2 - $lon1);
    $a = sin($dLat / 2) ** 2
        + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) ** 2;

    return $r * 2 * atan2(sqrt($a), sqrt(1 - $a));
}

/**
 * Géocodage ponctuel (Nominatim OSM) — une requête par validation de commande.
 *
 * @return array{lat: float, lon: float}|null
 */
function tiramii_geocode_delivery_address(string $address, string $zip5, string $city): ?array
{
    $address = trim($address);
    $city = trim($city);
    if ($address === '' || $zip5 === '') {
        return null;
    }
    $query = $address . ', ' . $zip5 . ' ' . $city . ', France';
    $url = 'https://nominatim.openstreetmap.org/search?' . http_build_query(
        [
            'format' => 'json',
            'limit' => '1',
            'addressdetails' => '0',
            'q' => $query,
        ],
        '',
        '&',
        PHP_QUERY_RFC3986
    );

    $ua = 'CasaDessertOrderValidator/1.0 (livraison; +https://tiramii.fr)';
    $raw = tiramii_http_get($url, $ua);
    if ($raw === null || $raw === '') {
        return null;
    }
    $json = json_decode($raw, true);
    if (!is_array($json) || $json === []) {
        usleep(1000000);
        $fallback = $zip5 . ' ' . $city . ', France';
        $url2 = 'https://nominatim.openstreetmap.org/search?' . http_build_query(
            [
                'format' => 'json',
                'limit' => '1',
                'addressdetails' => '0',
                'q' => $fallback,
            ],
            '',
            '&',
            PHP_QUERY_RFC3986
        );
        $raw2 = tiramii_http_get($url2, $ua);
        $json = is_string($raw2) && $raw2 !== '' ? json_decode($raw2, true) : null;
        if (!is_array($json) || $json === []) {
            return null;
        }
    }
    $first = $json[0];
    if (!is_array($first)) {
        return null;
    }
    if (!isset($first['lat'], $first['lon']) || !is_numeric($first['lat']) || !is_numeric($first['lon'])) {
        return null;
    }

    return ['lat' => (float) $first['lat'], 'lon' => (float) $first['lon']];
}

function tiramii_http_get(string $url, string $userAgent): ?string
{
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        if ($ch === false) {
            return null;
        }
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_CONNECTTIMEOUT => 6,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_HTTPHEADER => [
                'User-Agent: ' . $userAgent,
                'Accept: application/json',
                'Accept-Language: fr-FR,fr;q=0.9',
            ],
        ]);
        $body = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if (!is_string($body) || $code !== 200) {
            return null;
        }

        return $body;
    }

    $ctx = stream_context_create([
        'http' => [
            'method' => 'GET',
            'header' => "User-Agent: {$userAgent}\r\nAccept: application/json\r\nAccept-Language: fr-FR,fr;q=0.9\r\n",
            'timeout' => 10,
        ],
    ]);
    $body = @file_get_contents($url, false, $ctx);
    if (!is_string($body) || $body === '') {
        return null;
    }

    return $body;
}

/**
 * Retourne null si la livraison est acceptée, sinon un message d’erreur affichable.
 */
function tiramii_validate_delivery_order(float $totalEur, string $address, string $zip, string $city): ?string
{
    $dept = tiramii_delivery_postal_department($zip);
    if ($dept === null) {
        return 'Code postal invalide. Indiquez un code postal français à 5 chiffres.';
    }
    if (!in_array($dept, TIRAMII_DELIVERY_ALLOWED_DEPTS, true)) {
        return 'La livraison est limitée aux départements 75, 91, 92, 93 et 94. Votre commande ne peut pas être livrée à cette adresse.';
    }

    $zip5 = tiramii_delivery_zip5($zip);
    if ($zip5 === null) {
        return 'Code postal invalide. Indiquez un code postal français à 5 chiffres.';
    }

    if ($zip5 === TIRAMII_DELIVERY_PARIS_13_ZIP) {
        return null;
    }

    $rules = tiramii_delivery_rules_from_config();
    $coords = tiramii_geocode_delivery_address($address, $zip5, $city);
    if ($coords === null) {
        return 'Impossible de vérifier la position de l’adresse. Vérifiez l’adresse, le code postal et la ville, puis réessayez.';
    }

    $km = tiramii_haversine_km($rules['lat'], $rules['lon'], $coords['lat'], $coords['lon']);
    if ($km > $rules['remote_threshold_km'] && round($totalEur, 2) + 1e-6 < $rules['remote_min_eur']) {
        return sprintf(
            'Pour une livraison hors Paris 13e à plus de %.0f km, le montant minimum de commande est de %.0f € (panier actuel : %.2f €).',
            $rules['remote_threshold_km'],
            $rules['remote_min_eur'],
            round($totalEur, 2)
        );
    }

    return null;
}
