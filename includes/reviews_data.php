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
 */
declare(strict_types=1);

return [
    [
        'first_name' => 'Marie',
        'last_name' => 'Dupont',
        'text' => 'Les barquettes sont généreuses et vraiment bonnes — la saveur Oreo est mon coup de cœur !',
        'photo' => '',
    ],
    [
        'first_name' => 'Karim',
        'last_name' => 'Benali',
        'text' => 'Livraison nickel après 22h, tout était encore bien frais. Je recommande la box.',
        'photo' => '',
    ],
];
