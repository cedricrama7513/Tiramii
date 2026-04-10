<?php
/**
 * Copiez ce fichier en config.php et renseignez les valeurs.
 * Ne commitez jamais config.php (mots de passe).
 */
return [
    'db' => [
        'host' => 'localhost',
        'name' => 'votre_base_mysql',
        // Hostinger : l’utilisateur affiché dans « Bases de données » (ex. u123_nomquevousavezchoisi), pas seulement le préfixe du compte.
        'user' => 'votre_utilisateur',
        'pass' => 'votre_mot_de_passe',
        'charset' => 'utf8mb4',
    ],
    // Générez avec : php -r "echo password_hash('VotreMotDePasse', PASSWORD_DEFAULT);"
    'admin_password_hash' => '',
];
