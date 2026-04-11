<?php
/**
 * Livraison limitée aux départements 91, 92, 93 et 94 (code postal métropolitain, 5 chiffres).
 */
declare(strict_types=1);

/** @var list<string> */
const TIRAMII_DELIVERY_ALLOWED_DEPTS = ['91', '92', '93', '94'];

function tiramii_delivery_postal_department(string $zip): ?string
{
    $digits = preg_replace('/\D/u', '', $zip);
    if ($digits === '' || strlen($digits) < 5) {
        return null;
    }

    return substr($digits, 0, 2);
}

/**
 * Retourne null si la livraison est acceptée, sinon un message d’erreur affichable.
 * $totalEur est conservé pour compatibilité avec l’API ; la zone ne dépend que du code postal.
 */
function tiramii_validate_delivery_order(float $totalEur, string $address, string $zip, string $city): ?string
{
    $dept = tiramii_delivery_postal_department($zip);
    if ($dept === null) {
        return 'Code postal invalide. Indiquez un code postal français à 5 chiffres.';
    }
    if (!in_array($dept, TIRAMII_DELIVERY_ALLOWED_DEPTS, true)) {
        return 'La livraison est limitée aux départements 91, 92, 93 et 94. Votre commande ne peut pas être livrée à cette adresse.';
    }

    return null;
}
