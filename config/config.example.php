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
    // Générez avec : php tools/hash-password.php "VotreMotDePasse"
    'admin_password_hash' => '',

    /**
     * Sync manuelle GitHub → serveur (sync-from-github.php?token=…).
     * Générez une chaîne longue aléatoire ; ne commitez pas la vraie valeur dans config.php sur Git.
     */
    'github_sync_token' => '',

    /**
     * Notification à chaque nouvelle commande.
     * 1) EmailJS (recommandé) : Public Key + service_id + template_id (tableau EmailJS) + owner_email.
     * 2) Sinon SMTP Hostinger, puis repli PHP mail().
     * SMS Twilio : optionnel.
     */
    'notify' => [
        'owner_email' => 'vous@exemple.com',
        'emailjs_public_key' => '',
        'emailjs_service_id' => '',
        'emailjs_template_id' => '',
        'from_email' => 'contact@casadessert.fr',
        'from_name' => 'Casa Dessert',
        'smtp_host' => 'smtp.hostinger.com',
        'smtp_port' => 465,
        'smtp_user' => 'contact@casadessert.fr',
        'smtp_pass' => 'mot_de_passe_boite_mail',
        'sms_twilio_account_sid' => '',
        'sms_twilio_auth_token' => '',
        'sms_twilio_from' => '',
        'sms_owner_phone' => '',
        // Optionnel : dupliquer un template EmailJS et mapper pro_restaurant, pro_contact, pro_email, pro_phone, pro_city, pro_intent, pro_message, pro_body
        'emailjs_template_pro_lead_id' => '',
    ],

    /**
     * Livraison : point de référence (lat/lon) pour le calcul « hors 13e + 10 km ».
     * Optionnel — défaut ~ place d’Italie (75013). Ajustez si l’atelier est ailleurs.
     */
    'delivery' => [
        'shop_lat' => 48.8232,
        'shop_lon' => 2.3601,
        'remote_threshold_km' => 10,
        'remote_min_eur' => 15,
    ],
];
