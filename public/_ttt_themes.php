<?php
declare(strict_types=1);
/**
 * _ttt_themes.php — Taxonomie des themes de sorties (source unique de verite).
 *
 * PROD 2026-10 — dig-holding : Mehdi Benali (archi) / Clara Dupont (back) / Lukas Brennan (front).
 *
 * 30 themes organises en 5 familles (cf. entreprises/trouvetateam/_themes_concurrence_2026_10/
 * ANALYSE_THEMES.md). Les 6 slugs historiques (aperitif, sport, jeux, culturel, musique, rando)
 * sont conserves pour ne casser aucune sortie en base ni les chips existantes.
 *
 * Chaque theme : slug => [emoji, couleur, libelle, famille, image_og].
 * L'image OG reutilise les visuels deja presents dans /assets/og/ (sinon default).
 */

/** @return array<string,array{0:string,1:string,2:string,3:string,4:string}> */
function ttt_themes(): array
{
    static $t = null;
    if ($t !== null) {
        return $t;
    }
    $t = [
        // --- Sport & plein air ---
        'sport'     => ['🤸', '#16a34a', 'Sport (autre)', 'Sport & plein air', 'default'],
        'foot'      => ['⚽', '#16a34a', 'Foot', 'Sport & plein air', 'foot'],
        'running'   => ['🏃', '#0ea5e9', 'Running', 'Sport & plein air', 'running'],
        'velo'      => ['🚴', '#65a30d', 'Vélo', 'Sport & plein air', 'velo'],
        'padel'     => ['🥎', '#15803d', 'Padel', 'Sport & plein air', 'padel'],
        'tennis'    => ['🎾', '#22c55e', 'Tennis', 'Sport & plein air', 'tennis'],
        'basket'    => ['🏀', '#ea580c', 'Basket', 'Sport & plein air', 'basket'],
        'volley'    => ['🏐', '#0891b2', 'Volley', 'Sport & plein air', 'volley'],
        'muscu'     => ['🏋️', '#dc2626', 'Muscu / Fitness', 'Sport & plein air', 'default'],
        'yoga'      => ['🧘', '#7c3aed', 'Yoga', 'Sport & plein air', 'yoga'],
        'rando'     => ['🥾', '#65a30d', 'Rando / Plein air', 'Sport & plein air', 'rando'],
        // --- Jeux ---
        'jeux'      => ['🎲', '#e11d48', 'Jeux de société', 'Jeux', 'jeux'],
        'jeuxvideo' => ['🎮', '#6366f1', 'Jeux vidéo / Esport', 'Jeux', 'default'],
        // --- Sorties & convivialité ---
        'aperitif'  => ['🍻', '#d97706', 'Apéro', 'Sorties & convivialité', 'afterwork'],
        'bar'       => ['🍸', '#b45309', 'Bar', 'Sorties & convivialité', 'afterwork'],
        'cafe'      => ['☕', '#92400e', 'Café', 'Sorties & convivialité', 'default'],
        'resto'     => ['🍽️', '#ea580c', 'Resto', 'Sorties & convivialité', 'default'],
        'soiree'    => ['🎉', '#db2777', 'Soirée', 'Sorties & convivialité', 'afterwork'],
        // --- Culture & musique ---
        'musique'   => ['🎵', '#2563eb', 'Concert / Musique', 'Culture & musique', 'default'],
        'culturel'  => ['🎭', '#7c3aed', 'Culture (autre)', 'Culture & musique', 'culture'],
        'cinema'    => ['🎬', '#4f46e5', 'Ciné', 'Culture & musique', 'culture'],
        'expo'      => ['🖼️', '#7c3aed', 'Expo / Musée', 'Culture & musique', 'culture'],
        'theatre'   => ['🎟️', '#9333ea', 'Théâtre / Spectacle', 'Culture & musique', 'culture'],
        'danse'     => ['💃', '#db2777', 'Danse', 'Culture & musique', 'default'],
        // --- Découverte & lien ---
        'photo'     => ['📷', '#0ea5e9', 'Photo', 'Découverte & lien', 'default'],
        'cuisine'   => ['👨‍🍳', '#f59e0b', 'Cuisine', 'Découverte & lien', 'default'],
        'langues'   => ['🗣️', '#0d9488', 'Langues / Tandem', 'Découverte & lien', 'default'],
        'bienetre'  => ['🧖', '#14b8a6', 'Bien-être', 'Découverte & lien', 'default'],
        'voyage'    => ['✈️', '#0284c7', 'Voyage / Week-end', 'Découverte & lien', 'default'],
        'networking' => ['💼', '#475569', 'Networking / Pro', 'Découverte & lien', 'default'],
    ];
    return $t;
}

/** Alias de slugs historiques / variantes -> slug canonique. */
function ttt_theme_alias(string $slug): string
{
    $map = ['apero' => 'aperitif', 'culture' => 'culturel'];
    $slug = strtolower(trim($slug));
    return $map[$slug] ?? $slug;
}

/** Tous les slugs valides, dans l'ordre d'affichage. */
function ttt_theme_slugs(): array
{
    return array_keys(ttt_themes());
}

/** Vrai si le slug (ou son alias) est un theme connu. */
function ttt_theme_valid(string $slug): bool
{
    return isset(ttt_themes()[ttt_theme_alias($slug)]);
}

/** [emoji, couleur, libelle] pour un slug (fallback neutre si inconnu). */
function ttt_theme_meta(?string $slug): array
{
    $k = ttt_theme_alias((string) $slug);
    $t = ttt_themes();
    if (isset($t[$k])) {
        return [$t[$k][0], $t[$k][1], $t[$k][2]];
    }
    return ['📍', '#ff5a36', ucfirst($k !== '' ? $k : 'Sortie')];
}

/** Libelle d'un theme. */
function ttt_theme_label(?string $slug): string
{
    return ttt_theme_meta($slug)[2];
}

/** Chemin de l'image Open Graph pour un theme. */
function ttt_theme_og(?string $slug): string
{
    $k = ttt_theme_alias((string) $slug);
    $t = ttt_themes();
    $file = isset($t[$k]) ? $t[$k][4] : 'default';
    return '/assets/og/' . $file . '.png';
}

/** Themes groupes par famille (pour les <optgroup>). famille => [slug => [emoji,couleur,libelle,...]] */
function ttt_themes_grouped(): array
{
    $out = [];
    foreach (ttt_themes() as $slug => $meta) {
        $out[$meta[3]][$slug] = $meta;
    }
    return $out;
}
