<?php
/**
 * Rendu HTML de la section avis (photos + prénom + initiale du nom).
 */
declare(strict_types=1);

/**
 * @param list<array{first_name: string, last_name: string, text: string, photo?: string}> $reviews
 */
function tiramii_review_public_name(string $first, string $last): string
{
    $first = trim($first);
    $last = trim($last);
    $ini = $last !== '' ? mb_strtoupper(mb_substr($last, 0, 1, 'UTF-8'), 'UTF-8') : '';

    return $first . ($ini !== '' ? ' ' . $ini . '.' : '');
}

/**
 * @param list<array{first_name: string, last_name: string, text: string, photo?: string}> $reviews
 */
function tiramii_render_reviews_section(array $reviews): string
{
    if ($reviews === []) {
        return '';
    }

    $baseFs = dirname(__DIR__) . '/assets/img/avis';
    $baseUrl = 'assets/img/avis/';

    $cards = '';
    foreach ($reviews as $r) {
        $first = (string) ($r['first_name'] ?? '');
        $last = (string) ($r['last_name'] ?? '');
        $text = (string) ($r['text'] ?? '');
        $photoFile = isset($r['photo']) ? basename((string) $r['photo']) : '';
        $publicName = tiramii_review_public_name($first, $last);
        $initials = mb_strtoupper(mb_substr(trim($first), 0, 1, 'UTF-8'), 'UTF-8');
        $iniLast = $last !== '' ? mb_strtoupper(mb_substr(trim($last), 0, 1, 'UTF-8'), 'UTF-8') : '';
        if ($iniLast !== '') {
            $initials .= $iniLast;
        }

        $imgTag = '';
        if ($photoFile !== '' && is_readable($baseFs . '/' . $photoFile)) {
            $src = h($baseUrl . $photoFile);
            $imgTag = '<img class="review-photo" src="' . $src . '" width="320" height="320" alt="Photo de ' . h($publicName) . '" loading="lazy">';
        } else {
            $imgTag = '<div class="review-photo-fallback" aria-hidden="true"><span>' . h($initials !== '' ? $initials : '?') . '</span></div>';
        }

        $cards .= '<article class="review-card reveal">';
        $cards .= '<div class="review-media">' . $imgTag . '</div>';
        $cards .= '<p class="review-name">' . h($publicName) . '</p>';
        $cards .= '<p class="review-text">« ' . h($text) . ' »</p>';
        $cards .= '</article>';
    }

    return '<section id="avis" class="reviews-section">'
        . '<div class="section-title reveal"><div class="eyebrow">Ils ont testé</div><h2>Avis clients</h2></div>'
        . '<div class="reviews-wrap"><div class="reviews-grid">' . $cards . '</div></div>'
        . '</section>';
}
