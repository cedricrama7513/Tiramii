<?php
/**
 * Rendu HTML de la section avis (photo optionnelle + prénom + initiale du nom si renseignée).
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

    $starsFive = '<span class="review-stars-side" role="img" aria-label="Note : 5 sur 5">'
        . '<span class="review-star" aria-hidden="true">★</span>'
        . '<span class="review-star" aria-hidden="true">★</span>'
        . '<span class="review-star" aria-hidden="true">★</span>'
        . '<span class="review-star" aria-hidden="true">★</span>'
        . '<span class="review-star" aria-hidden="true">★</span>'
        . '</span>';

    $cards = '';
    $idx = 0;
    foreach ($reviews as $r) {
        $first = (string) ($r['first_name'] ?? '');
        $last = (string) ($r['last_name'] ?? '');
        $text = (string) ($r['text'] ?? '');
        $photoFile = isset($r['photo']) ? basename((string) $r['photo']) : '';
        $publicName = tiramii_review_public_name($first, $last);
        $sortKey = mb_strtolower($publicName, 'UTF-8');

        $mediaHtml = '';
        if ($photoFile !== '' && is_readable($baseFs . '/' . $photoFile)) {
            $src = h($baseUrl . $photoFile);
            $mediaHtml = '<div class="review-media"><img class="review-photo" src="' . $src . '" width="320" height="320" alt="Photo de ' . h($publicName) . '" loading="lazy"></div>';
        }

        $cards .= '<article class="review-card reveal" data-review-idx="' . h((string) $idx) . '" data-review-name="' . h($sortKey) . '">';
        $cards .= $mediaHtml;
        $cards .= '<p class="review-name">' . h($publicName) . '</p>';
        $cards .= '<div class="review-quote-row">';
        $cards .= '<p class="review-text">« ' . h($text) . ' »</p>';
        $cards .= $starsFive;
        $cards .= '</div>';
        $cards .= '</article>';
        ++$idx;
    }

    $toolbar = '<div class="reviews-toolbar reveal">'
        . '<div class="reviews-rating-block">'
        . '<span class="review-stars-inline" role="img" aria-label="Note moyenne : 5 sur 5">'
        . '<span aria-hidden="true">★★★★★</span></span>'
        . '<span class="reviews-rating-caption">5 / 5</span>'
        . '</div>'
        . '<div class="reviews-sort">'
        . '<label for="reviewsSort">Trier par</label>'
        . '<select id="reviewsSort" class="reviews-select">'
        . '<option value="default" selected>Ordre d’origine</option>'
        . '<option value="name-asc">Prénom (A → Z)</option>'
        . '<option value="name-desc">Prénom (Z → A)</option>'
        . '</select>'
        . '</div>'
        . '</div>';

    $marquee = '<div class="reviews-marquee-outer">'
        . '<div class="reviews-marquee" role="region" aria-label="Avis clients en défilement">'
        . '<div class="reviews-track" id="reviewsTrack">'
        . '<div class="reviews-chunk" id="reviewsChunkA">' . $cards . '</div>'
        . '<div class="reviews-chunk reviews-chunk--clone" id="reviewsChunkB" aria-hidden="true">' . $cards . '</div>'
        . '</div></div></div>';

    return '<section id="avis" class="reviews-section">'
        . '<div class="section-title reveal"><div class="eyebrow">Ils ont testé</div><h2>Avis clients</h2></div>'
        . $toolbar
        . '<div class="reviews-wrap">' . $marquee . '</div>'
        . '</section>';
}
