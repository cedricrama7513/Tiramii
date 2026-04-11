<?php
/**
 * Règles livraison : offerte ≤ 10 km du point de départ ; au-delà, minimum de commande 15 €.
 * Géocodage via Nominatim (même logique que le JS boutique).
 */
declare(strict_types=1);

const TIRAMII_DELIVERY_ORIGIN_QUERY = 'Paris 13, France';
const TIRAMII_DELIVERY_FREE_RADIUS_KM = 10.0;
const TIRAMII_DELIVERY_MIN_BEYOND_FREE_EUR = 15.0;

/**
 * @return array{lat: float, lon: float}|null
 */
function tiramii_nominatim_geocode(string $query): ?array
{
    $query = trim($query);
    if ($query === '') {
        return null;
    }
    $url = 'https://nominatim.openstreetmap.org/search?' . http_build_query([
        'format' => 'jsonv2',
        'limit' => 1,
        'countrycodes' => 'fr',
        'q' => $query,
    ], '', '&', PHP_QUERY_RFC3986);
    $headers = [
        'Accept: application/json',
        'Accept-Language: fr',
        'User-Agent: Tiramii/1.0 (https://tiramii.fr)',
    ];
    $raw = null;
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        if ($ch !== false) {
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_TIMEOUT => 10,
                CURLOPT_HTTPHEADER => $headers,
            ]);
            $raw = curl_exec($ch);
            curl_close($ch);
        }
    }
    if ($raw === null || $raw === false || $raw === '') {
        $ctx = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => implode("\r\n", $headers) . "\r\n",
                'timeout' => 10,
            ],
        ]);
        $raw = @file_get_contents($url, false, $ctx);
    }
    if ($raw === false || $raw === '') {
        return null;
    }
    $data = json_decode($raw, true);
    if (!is_array($data) || $data === []) {
        return null;
    }
    $first = $data[0];
    if (!is_array($first) || !isset($first['lat'], $first['lon'])) {
        return null;
    }
    $lat = (float) $first['lat'];
    $lon = (float) $first['lon'];
    if ($lat < -90 || $lat > 90 || $lon < -180 || $lon > 180) {
        return null;
    }
    return ['lat' => $lat, 'lon' => $lon];
}

/**
 * @param array{lat: float, lon: float} $a
 * @param array{lat: float, lon: float} $b
 */
function tiramii_haversine_km(array $a, array $b): float
{
    $toRad = static fn (float $deg): float => $deg * M_PI / 180.0;
    $R = 6371.0;
    $dLat = $toRad($b['lat'] - $a['lat']);
    $dLon = $toRad($b['lon'] - $a['lon']);
    $lat1 = $toRad($a['lat']);
    $lat2 = $toRad($b['lat']);
    $h = sin($dLat / 2) ** 2 + cos($lat1) * cos($lat2) * sin($dLon / 2) ** 2;
    return 2 * $R * asin(min(1.0, sqrt($h)));
}

/**
 * @return array{lat: float, lon: float}|null
 */
function tiramii_delivery_origin_coords(): ?array
{
    static $cached = null;
    if ($cached !== null) {
        return $cached;
    }
    $cached = tiramii_nominatim_geocode(TIRAMII_DELIVERY_ORIGIN_QUERY);
    return $cached;
}

/**
 * Retourne null si la livraison est acceptée, sinon un message d’erreur affichable.
 */
function tiramii_validate_delivery_order(float $totalEur, string $address, string $zip, string $city): ?string
{
    $parts = array_filter([trim($address), trim($zip), trim($city), 'France']);
    $destQuery = implode(', ', $parts);
    $origin = tiramii_delivery_origin_coords();
    if ($origin === null) {
        return 'Impossible de vérifier la zone de livraison pour le moment. Réessayez plus tard.';
    }
    $dest = tiramii_nominatim_geocode($destQuery);
    if ($dest === null) {
        return 'Adresse introuvable. Vérifiez l’adresse, le code postal et la ville.';
    }
    $km = tiramii_haversine_km($origin, $dest);
    $total = round($totalEur, 2);
    if ($km > TIRAMII_DELIVERY_FREE_RADIUS_KM && $total < TIRAMII_DELIVERY_MIN_BEYOND_FREE_EUR) {
        $kmStr = str_replace('.', ',', sprintf('%.1f', $km));

        return 'Au-delà de 10 km, le minimum de commande est de 15 € (distance estimée : ' . $kmStr . ' km).';
    }

    return null;
}
