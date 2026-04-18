<?php
/**
 * Avis clients affichés sur la page d’accueil.
 *
 * Pour chaque avis :
 * - first_name, last_name : le site affiche « Prénom » + première lettre du nom + « . » (ex. Marie D.)
 * - text : court témoignage
 * - photo : nom de fichier uniquement (ex. marie-dupont.jpg) déposé dans assets/img/avis/
 *   Si le fichier est absent, un avatar avec initiales (ex. MD) est affiché à la place.
 *   Affichage public : « Marie D. » (prénom + 1re lettre du nom + point).
 *
 * Pseudonymes Snapchat : Marzou → Omar ; Trixma → Marvin ; Reevihnoo → Ryan R.
 * Sans nom de famille visible : initiale « X » (ex. Abdel X.) pour garder le format Prénom L.
 */
declare(strict_types=1);

return [
    [
        'first_name' => 'Omar',
        'last_name' => '',
        'text' => 'Salam, carré de fou les tiramisus !!!',
        'photo' => '',
    ],
    [
        'first_name' => 'Marvin',
        'last_name' => '',
        'text' => 'C’est le meilleur tiramisu que j’ai mangé.',
        'photo' => '',
    ],
    [
        'first_name' => 'Ryan',
        'last_name' => 'R',
        'text' => 'Il était pas mal le Kinder Bueno.',
        'photo' => '',
    ],
    [
        'first_name' => 'Lila',
        'last_name' => 'X',
        'text' => 'Excellent le tiramisu spéculos. J’ai vraiment kiffé. Obligé de goûter les autres.',
        'photo' => '',
    ],
    [
        'first_name' => 'Maxime',
        'last_name' => 'X',
        'text' => 'On a mangé un tiramisu hier avec ma go, on a kiffé de fou. Bien chargé et bien bon, pas trop sucré.',
        'photo' => '',
    ],
    [
        'first_name' => 'Yassine',
        'last_name' => 'X',
        'text' => 'Énervé ouais. Le Daim et le Bueno Nutella surtout.',
        'photo' => '',
    ],
    [
        'first_name' => 'Usser',
        'last_name' => 'X',
        'text' => 'Salam frérot, très très bon tes tiramisus, dinguerie. Carrément envoie ton flyer.',
        'photo' => '',
    ],
    [
        'first_name' => 'Abdel',
        'last_name' => 'X',
        'text' => 'C’est fort c’est bon. Je vais partager ton snap.',
        'photo' => '',
    ],
    [
        'first_name' => 'Rayane',
        'last_name' => 'H',
        'text' => 'Ct vraiment bon frérot',
        'photo' => '',
    ],
];
