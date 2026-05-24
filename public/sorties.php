<?php
declare(strict_types=1);
/**
 * Page /sorties — liste publique des prochains événements Toulouse
 *
 * Lecture seule (pas de mutation), accessible sans auth (parcours découverte Adrien).
 * V2.0 — Refonte UI Julie Vasseur (UX Lead dig-holding) 2026-05-25
 * Identité visuelle "Toulouse vivant" : terracotta + crème + Fraunces/Inter.
 */

require_once __DIR__ . '/../src/Core/Bootstrap.php';
\App\Core\Bootstrap::init();

use App\Core\Pdo;

$pdo = Pdo::instance();

$sql = "
    SELECT
        e.id, e.title, e.description, e.category, e.starts_at, e.capacity, e.is_sponsored,
        n.name AS neighborhood,
        c.name AS city,
        u.name AS host_name,
        p.avatar_url AS host_avatar,
        (SELECT COUNT(*) FROM rsvps r WHERE r.event_id = e.id AND r.status = 'going') AS rsvps_count
    FROM events e
    LEFT JOIN neighborhoods n ON n.id = e.neighborhood_id
    LEFT JOIN cities c ON c.id = e.city_id
    LEFT JOIN users u ON u.id = e.host_user_id
    LEFT JOIN profiles p ON p.user_id = u.id
    WHERE e.status = 'published'
      AND e.starts_at >= NOW()
    ORDER BY e.is_sponsored DESC, e.starts_at ASC
    LIMIT 50
";

$events = $pdo->query($sql)->fetchAll();

function formatFrenchDate(string $datetime): string {
    $days = ['Sunday' => 'dimanche', 'Monday' => 'lundi', 'Tuesday' => 'mardi', 'Wednesday' => 'mercredi', 'Thursday' => 'jeudi', 'Friday' => 'vendredi', 'Saturday' => 'samedi'];
    $months = ['01' => 'janvier', '02' => 'février', '03' => 'mars', '04' => 'avril', '05' => 'mai', '06' => 'juin', '07' => 'juillet', '08' => 'août', '09' => 'septembre', '10' => 'octobre', '11' => 'novembre', '12' => 'décembre'];
    $ts = strtotime($datetime);
    $day = $days[date('l', $ts)];
    $dayNum = date('j', $ts);
    $month = $months[date('m', $ts)];
    $time = date('H\hi', $ts);
    return ucfirst($day) . " {$dayNum} {$month} · {$time}";
}

function shortFrenchDate(string $datetime): array {
    $months = ['01' => 'JAN', '02' => 'FÉV', '03' => 'MAR', '04' => 'AVR', '05' => 'MAI', '06' => 'JUIN', '07' => 'JUIL', '08' => 'AOÛT', '09' => 'SEPT', '10' => 'OCT', '11' => 'NOV', '12' => 'DÉC'];
    $ts = strtotime($datetime);
    return [
        'day' => date('j', $ts),
        'month' => $months[date('m', $ts)],
        'time' => date('H\hi', $ts),
    ];
}

$categoryLabels = [
    'aperitif' => 'Apéro',
    'sport' => 'Sport',
    'jeux' => 'Jeux',
    'culturel' => 'Culturel',
    'musique' => 'Musique',
    'rando' => 'Rando',
];

// Photo Unsplash CC0 par catégorie (mood spécifique)
$categoryPhotos = [
    'aperitif' => 'https://images.unsplash.com/photo-1543007630-9710e4a00a20?auto=format&fit=crop&w=900&q=80', // apéro terrasse
    'sport'    => 'https://images.unsplash.com/photo-1486218119243-13883505764c?auto=format&fit=crop&w=900&q=80', // rando montagne / sport
    'jeux'     => 'https://images.unsplash.com/photo-1610890716171-6b1bb98ffd09?auto=format&fit=crop&w=900&q=80', // jeux de société
    'culturel' => 'https://images.unsplash.com/photo-1577720580479-7d839d829c73?auto=format&fit=crop&w=900&q=80', // expo musée
    'musique'  => 'https://images.unsplash.com/photo-1493225457124-a3eb161ffa5f?auto=format&fit=crop&w=900&q=80', // concert
    'rando'    => 'https://images.unsplash.com/photo-1551632811-561732d1e306?auto=format&fit=crop&w=900&q=80', // rando pyrénées
];

// Couleur accent par catégorie (chip)
$categoryAccents = [
    'aperitif' => ['#E2725B', '#FAE0D7'],
    'sport'    => ['#1B7A4E', '#D7F0E2'],
    'jeux'     => ['#7B3FA7', '#EADDF5'],
    'culturel' => ['#B6864E', '#F5E8D2'],
    'musique'  => ['#1B2A4E', '#D7DDEA'],
    'rando'    => ['#3D7A1B', '#E0EFD2'],
];

header('Content-Type: text/html; charset=utf-8');
?><!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="description" content="Trouvetateam : découvre les prochaines sorties à Toulouse. Apéros, randos, jeux, sport, culture entre adultes vérifiés.">
    <meta name="robots" content="index, follow">
    <meta name="theme-color" content="#E2725B">
    <title>Sorties à Toulouse — trouvetateam</title>
    <link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'%3E%3Crect width='100' height='100' rx='24' fill='%23E2725B'/%3E%3Ctext x='50' y='66' font-family='Georgia,serif' font-size='52' font-weight='700' fill='%23FFF8F1' text-anchor='middle'%3Ett%3C/text%3E%3C/svg%3E">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,400;9..144,500;9..144,600;9..144,700;9..144,800&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg: #FFF8F1;
            --bg-cream: #FBF1E4;
            --bg-card: #FFFFFF;
            --ink: #1B2A4E;
            --ink-soft: #4A5878;
            --ink-mute: #7B86A2;
            --terracotta: #E2725B;
            --terracotta-deep: #C85B45;
            --terracotta-soft: #FAE0D7;
            --gold: #D4A574;
            --gold-deep: #B6864E;
            --border: #ECDFD0;
            --border-soft: #F4E9DA;
            --radius-s: 8px;
            --radius: 14px;
            --radius-l: 24px;
            --shadow-s: 0 1px 2px rgba(27, 42, 78, 0.06);
            --shadow: 0 4px 16px rgba(27, 42, 78, 0.08);
            --shadow-l: 0 20px 60px rgba(27, 42, 78, 0.15);
            --max-w: 1140px;
            --font-display: 'Fraunces', Georgia, 'Times New Roman', serif;
            --font-body: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', system-ui, sans-serif;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        html { -webkit-text-size-adjust: 100%; scroll-behavior: smooth; }
        body {
            font-family: var(--font-body);
            background: var(--bg);
            color: var(--ink);
            line-height: 1.6;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }
        img { max-width: 100%; display: block; }
        .container { max-width: var(--max-w); margin: 0 auto; padding: 0 22px; }

        /* HEADER (same as landing) */
        header.site {
            padding: 18px 0;
            position: sticky;
            top: 0;
            background: rgba(255, 248, 241, 0.92);
            backdrop-filter: saturate(160%) blur(12px);
            -webkit-backdrop-filter: saturate(160%) blur(12px);
            z-index: 50;
            border-bottom: 1px solid rgba(236, 223, 208, 0.6);
        }
        header.site .inner {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
        }
        .logo {
            display: inline-flex;
            align-items: center;
            gap: 11px;
            font-family: var(--font-display);
            font-weight: 700;
            font-size: 21px;
            letter-spacing: -0.02em;
            color: var(--ink);
            text-decoration: none;
        }
        .logo-mark {
            width: 38px;
            height: 38px;
            border-radius: 11px;
            background: linear-gradient(135deg, var(--terracotta) 0%, var(--terracotta-deep) 100%);
            color: #FFF8F1;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-family: var(--font-display);
            font-size: 18px;
            font-weight: 700;
            letter-spacing: -0.04em;
            box-shadow: 0 2px 8px rgba(226, 114, 91, 0.3);
        }
        .nav-links {
            display: none;
            gap: 28px;
            align-items: center;
        }
        @media (min-width: 720px) {
            .nav-links { display: flex; }
        }
        .nav-links a {
            font-size: 14.5px;
            font-weight: 500;
            color: var(--ink-soft);
            text-decoration: none;
            transition: color .15s ease;
        }
        .nav-links a:hover { color: var(--terracotta); }
        .nav-links a.active { color: var(--terracotta); }
        .nav-links .nav-cta {
            background: var(--ink);
            color: #FFF8F1;
            padding: 10px 18px;
            border-radius: 10px;
            font-weight: 600;
        }
        .nav-links .nav-cta:hover {
            background: var(--terracotta);
            color: #FFF8F1;
        }

        /* PAGE HEAD */
        .page-head {
            padding: 56px 0 40px;
            text-align: left;
        }
        @media (min-width: 720px) {
            .page-head { padding: 72px 0 48px; }
        }
        .page-head .eyebrow {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-size: 13px;
            font-weight: 600;
            color: var(--terracotta-deep);
            background: var(--terracotta-soft);
            padding: 7px 14px;
            border-radius: 100px;
            margin-bottom: 22px;
        }
        .page-head .eyebrow::before {
            content: '';
            width: 7px;
            height: 7px;
            border-radius: 50%;
            background: var(--terracotta);
            box-shadow: 0 0 0 4px rgba(226, 114, 91, 0.18);
        }
        .page-head h1 {
            font-family: var(--font-display);
            font-size: clamp(36px, 5.5vw, 56px);
            line-height: 1.05;
            letter-spacing: -0.03em;
            font-weight: 600;
            margin-bottom: 14px;
        }
        .page-head h1 em {
            font-style: italic;
            color: var(--terracotta-deep);
            font-weight: 500;
        }
        .page-head .sub {
            font-size: 17px;
            color: var(--ink-soft);
            max-width: 580px;
        }

        /* FILTER BAR */
        .filter-bar {
            display: flex;
            gap: 10px;
            margin-bottom: 36px;
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
            padding-bottom: 4px;
            scrollbar-width: none;
        }
        .filter-bar::-webkit-scrollbar { display: none; }
        .filter-chip {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            padding: 10px 18px;
            border-radius: 100px;
            background: #FFFFFF;
            border: 1px solid var(--border);
            color: var(--ink-soft);
            font-size: 14px;
            font-weight: 500;
            text-decoration: none;
            white-space: nowrap;
            cursor: pointer;
            transition: all .15s ease;
        }
        .filter-chip:hover {
            border-color: var(--terracotta);
            color: var(--terracotta-deep);
        }
        .filter-chip.active {
            background: var(--ink);
            color: #FFF8F1;
            border-color: var(--ink);
        }
        .filter-chip .chip-count {
            background: rgba(123, 134, 162, 0.15);
            padding: 2px 8px;
            border-radius: 100px;
            font-size: 12px;
            font-weight: 600;
        }
        .filter-chip.active .chip-count {
            background: rgba(255, 248, 241, 0.2);
            color: #FFF8F1;
        }

        /* EVENTS GRID */
        .events-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 24px;
            padding-bottom: 96px;
        }
        @media (min-width: 640px) {
            .events-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 26px;
            }
        }
        @media (min-width: 960px) {
            .events-grid {
                grid-template-columns: repeat(3, 1fr);
                gap: 28px;
            }
        }

        .event-card {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: var(--radius-l);
            overflow: hidden;
            display: flex;
            flex-direction: column;
            transition: transform .25s ease, box-shadow .25s ease, border-color .25s ease;
            text-decoration: none;
            color: inherit;
            cursor: pointer;
        }
        .event-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-l);
            border-color: var(--terracotta-soft);
        }

        .event-image {
            position: relative;
            aspect-ratio: 16/10;
            overflow: hidden;
            background: var(--bg-cream);
        }
        .event-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform .5s ease;
        }
        .event-card:hover .event-image img { transform: scale(1.06); }

        .event-image::after {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(180deg, rgba(27, 42, 78, 0) 50%, rgba(27, 42, 78, 0.6) 100%);
        }

        .event-cat-chip {
            position: absolute;
            top: 14px;
            left: 14px;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 12px;
            border-radius: 100px;
            font-size: 12px;
            font-weight: 600;
            letter-spacing: 0.01em;
            backdrop-filter: blur(8px);
            -webkit-backdrop-filter: blur(8px);
            z-index: 2;
        }

        .event-date-badge {
            position: absolute;
            top: 14px;
            right: 14px;
            background: #FFF8F1;
            border-radius: 12px;
            padding: 8px 12px;
            text-align: center;
            min-width: 58px;
            box-shadow: 0 4px 12px rgba(27, 42, 78, 0.2);
            z-index: 2;
        }
        .event-date-badge .day {
            font-family: var(--font-display);
            font-size: 22px;
            font-weight: 700;
            line-height: 1;
            color: var(--ink);
            display: block;
        }
        .event-date-badge .month {
            font-size: 10px;
            font-weight: 700;
            letter-spacing: 0.1em;
            color: var(--terracotta-deep);
            margin-top: 3px;
            display: block;
        }

        .event-sponsored-tag {
            position: absolute;
            bottom: 14px;
            right: 14px;
            background: var(--gold);
            color: var(--ink);
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            padding: 5px 10px;
            border-radius: 6px;
            z-index: 2;
        }

        .event-body {
            padding: 22px 22px 18px;
            display: flex;
            flex-direction: column;
            flex: 1;
            gap: 12px;
        }

        .event-title {
            font-family: var(--font-display);
            font-size: 20px;
            font-weight: 600;
            line-height: 1.25;
            letter-spacing: -0.015em;
            color: var(--ink);
        }
        .event-desc {
            font-size: 14px;
            color: var(--ink-soft);
            line-height: 1.55;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
            flex-grow: 1;
        }

        .event-meta {
            display: flex;
            flex-direction: column;
            gap: 6px;
            font-size: 13.5px;
            color: var(--ink-soft);
        }
        .event-meta-row {
            display: flex;
            align-items: center;
            gap: 9px;
        }
        .event-meta-row svg {
            color: var(--terracotta);
            flex-shrink: 0;
        }

        .event-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 14px 22px;
            border-top: 1px solid var(--border-soft);
            background: var(--bg-cream);
        }
        .event-host {
            display: flex;
            align-items: center;
            gap: 9px;
            font-size: 13px;
            color: var(--ink-soft);
            min-width: 0;
        }
        .event-host img {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            object-fit: cover;
            background: var(--terracotta-soft);
            flex-shrink: 0;
        }
        .event-host .host-name {
            font-weight: 600;
            color: var(--ink);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .event-places {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            font-size: 12.5px;
            font-weight: 600;
            color: var(--terracotta-deep);
            background: var(--terracotta-soft);
            padding: 5px 10px;
            border-radius: 100px;
            white-space: nowrap;
        }
        .event-places.full {
            background: rgba(123, 134, 162, 0.15);
            color: var(--ink-mute);
        }

        .empty {
            padding: 64px 24px;
            text-align: center;
            color: var(--ink-mute);
            background: var(--bg-cream);
            border: 1px dashed var(--border);
            border-radius: var(--radius-l);
        }
        .empty .empty-icon {
            font-size: 48px;
            margin-bottom: 16px;
            display: block;
        }
        .empty h3 {
            font-family: var(--font-display);
            font-size: 22px;
            color: var(--ink);
            margin-bottom: 8px;
        }

        /* FOOTER (compact version) */
        footer.site {
            background: #FFFFFF;
            border-top: 1px solid var(--border);
            padding: 48px 0 28px;
        }
        .footer-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 32px;
            margin-bottom: 32px;
        }
        @media (min-width: 720px) {
            .footer-grid {
                grid-template-columns: 1.4fr 1fr 1fr 1fr;
                gap: 48px;
            }
        }
        .footer-brand .logo { margin-bottom: 14px; }
        .footer-brand p {
            font-size: 14px;
            color: var(--ink-mute);
            max-width: 280px;
            line-height: 1.6;
        }
        .footer-col h4 {
            font-family: var(--font-body);
            font-size: 13px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            color: var(--ink);
            margin-bottom: 18px;
        }
        .footer-col ul { list-style: none; }
        .footer-col li { margin-bottom: 11px; }
        .footer-col a {
            color: var(--ink-soft);
            text-decoration: none;
            font-size: 14.5px;
            transition: color .15s ease;
        }
        .footer-col a:hover { color: var(--terracotta); }
        .footer-bottom {
            padding-top: 24px;
            border-top: 1px solid var(--border-soft);
            display: flex;
            flex-direction: column;
            gap: 10px;
            font-size: 13px;
            color: var(--ink-mute);
        }
        @media (min-width: 640px) {
            .footer-bottom {
                flex-direction: row;
                align-items: center;
                justify-content: space-between;
            }
        }
    </style>
</head>
<body>
    <header class="site">
        <div class="container inner">
            <a href="/" class="logo">
                <span class="logo-mark">tt</span>
                <span>trouvetateam</span>
            </a>
            <nav class="nav-links">
                <a href="/sorties" class="active">Sorties</a>
                <a href="/membres">Membres</a>
                <a href="/#comment">Comment ça marche</a>
                <a href="/#rejoindre" class="nav-cta">Rejoindre</a>
            </nav>
        </div>
    </header>

    <main>
        <section class="container">
            <div class="page-head">
                <div class="eyebrow">Toulouse · prochaines sorties</div>
                <h1>Ta prochaine <em>sortie t'attend</em>.</h1>
                <p class="sub"><?= count($events) ?> sortie<?= count($events) > 1 ? 's' : '' ?> ouverte<?= count($events) > 1 ? 's' : '' ?> aux membres, du verre en terrasse à la rando dans les Pyrénées. Filtre par catégorie et trouve la tienne.</p>
            </div>

            <?php
            // Count par catégorie pour les chips
            $catCounts = [];
            foreach ($events as $e) {
                $c = $e['category'] ?? 'other';
                $catCounts[$c] = ($catCounts[$c] ?? 0) + 1;
            }
            ?>
            <div class="filter-bar" role="tablist">
                <a href="#" class="filter-chip active" data-cat="all">
                    Toutes
                    <span class="chip-count"><?= count($events) ?></span>
                </a>
                <?php foreach ($categoryLabels as $catKey => $catLabel):
                    if (!isset($catCounts[$catKey])) continue;
                ?>
                    <a href="#" class="filter-chip" data-cat="<?= htmlspecialchars($catKey, ENT_QUOTES, 'UTF-8') ?>">
                        <?= htmlspecialchars($catLabel, ENT_QUOTES, 'UTF-8') ?>
                        <span class="chip-count"><?= $catCounts[$catKey] ?></span>
                    </a>
                <?php endforeach; ?>
            </div>

            <?php if (empty($events)): ?>
                <div class="empty">
                    <span class="empty-icon">🥂</span>
                    <h3>Aucune sortie prévue</h3>
                    <p>Reviens bientôt, la communauté toulousaine s'organise.</p>
                </div>
            <?php else: ?>
                <div class="events-grid" id="eventsGrid">
                    <?php foreach ($events as $e):
                        $cat = $e['category'] ?? 'aperitif';
                        $catLabel = $categoryLabels[$cat] ?? ucfirst($cat);
                        $photo = $categoryPhotos[$cat] ?? $categoryPhotos['aperitif'];
                        [$catColor, $catBg] = $categoryAccents[$cat] ?? $categoryAccents['aperitif'];
                        $hostName = $e['host_name'] ?? 'Anonyme';
                        $hostAvatar = $e['host_avatar'] ?: 'https://api.dicebear.com/7.x/lorelei/svg?seed=' . urlencode($hostName) . '&backgroundColor=fae0d7,d4a574,e2725b';
                        $date = shortFrenchDate($e['starts_at']);
                        $rsvps = (int) $e['rsvps_count'];
                        $cap = $e['capacity'] !== null ? (int) $e['capacity'] : null;
                        $isFull = $cap !== null && $rsvps >= $cap;
                        $placesText = $cap !== null ? ($cap - $rsvps) . ' place' . ($cap - $rsvps > 1 ? 's' : '') . ' restante' . ($cap - $rsvps > 1 ? 's' : '') : 'Places illimitées';
                        if ($isFull) $placesText = 'Complet';
                    ?>
                        <a href="#bientot" class="event-card" data-cat="<?= htmlspecialchars($cat, ENT_QUOTES, 'UTF-8') ?>">
                            <div class="event-image">
                                <img src="<?= htmlspecialchars($photo, ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($catLabel, ENT_QUOTES, 'UTF-8') ?>" loading="lazy">
                                <span class="event-cat-chip" style="background: <?= $catBg ?>; color: <?= $catColor ?>;">
                                    <?= htmlspecialchars($catLabel, ENT_QUOTES, 'UTF-8') ?>
                                </span>
                                <div class="event-date-badge">
                                    <span class="day"><?= $date['day'] ?></span>
                                    <span class="month"><?= $date['month'] ?></span>
                                </div>
                                <?php if (!empty($e['is_sponsored'])): ?>
                                    <span class="event-sponsored-tag">Sponsorisé</span>
                                <?php endif; ?>
                            </div>
                            <div class="event-body">
                                <h2 class="event-title"><?= htmlspecialchars($e['title'], ENT_QUOTES, 'UTF-8') ?></h2>
                                <p class="event-desc"><?= htmlspecialchars($e['description'] ?? '', ENT_QUOTES, 'UTF-8') ?></p>
                                <div class="event-meta">
                                    <div class="event-meta-row">
                                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                            <circle cx="12" cy="12" r="10"/>
                                            <polyline points="12 6 12 12 16 14"/>
                                        </svg>
                                        <span><?= formatFrenchDate($e['starts_at']) ?></span>
                                    </div>
                                    <div class="event-meta-row">
                                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                            <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/>
                                            <circle cx="12" cy="10" r="3"/>
                                        </svg>
                                        <span><?= htmlspecialchars(($e['neighborhood'] ?? '—') . ', ' . ($e['city'] ?? 'Toulouse'), ENT_QUOTES, 'UTF-8') ?></span>
                                    </div>
                                </div>
                            </div>
                            <div class="event-footer">
                                <div class="event-host">
                                    <img src="<?= htmlspecialchars($hostAvatar, ENT_QUOTES, 'UTF-8') ?>" alt="" loading="lazy">
                                    <span>par <span class="host-name"><?= htmlspecialchars($hostName, ENT_QUOTES, 'UTF-8') ?></span></span>
                                </div>
                                <span class="event-places <?= $isFull ? 'full' : '' ?>">
                                    <?php if (!$isFull): ?>
                                        <svg width="11" height="11" viewBox="0 0 24 24" fill="currentColor"><circle cx="12" cy="12" r="6"/></svg>
                                    <?php endif; ?>
                                    <?= htmlspecialchars($placesText, ENT_QUOTES, 'UTF-8') ?>
                                </span>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
    </main>

    <footer class="site">
        <div class="container">
            <div class="footer-grid">
                <div class="footer-brand">
                    <a href="/" class="logo">
                        <span class="logo-mark">tt</span>
                        <span>trouvetateam</span>
                    </a>
                    <p>La communauté toulousaine pour sortir entre adultes. Sans bots, sans pub, sans GPS partagé.</p>
                </div>
                <div class="footer-col">
                    <h4>Produit</h4>
                    <ul>
                        <li><a href="/sorties">Sorties</a></li>
                        <li><a href="/membres">Membres</a></li>
                        <li><a href="/#comment">Comment ça marche</a></li>
                        <li><a href="/#rejoindre">Rejoindre</a></li>
                    </ul>
                </div>
                <div class="footer-col">
                    <h4>Communauté</h4>
                    <ul>
                        <li><a href="mailto:contact@trouvetateam.fr">Nous écrire</a></li>
                        <li><a href="mailto:safety@trouvetateam.fr">Signalement</a></li>
                        <li><a href="#">Charte</a></li>
                        <li><a href="#">Modération</a></li>
                    </ul>
                </div>
                <div class="footer-col">
                    <h4>Légal</h4>
                    <ul>
                        <li><a href="#">CGU</a></li>
                        <li><a href="#">Confidentialité</a></li>
                        <li><a href="#">Cookies</a></li>
                        <li><a href="#">Mentions légales</a></li>
                    </ul>
                </div>
            </div>
            <div class="footer-bottom">
                <div>© 2026 trouvetateam — édité par Digital Invest Group</div>
                <div>Fait à Toulouse, avec du soleil et beaucoup de café.</div>
            </div>
        </div>
    </footer>

    <script>
    // Filter chips: filtrage client léger (le serveur renvoie déjà tout)
    (function() {
        var chips = document.querySelectorAll('.filter-chip');
        var grid = document.getElementById('eventsGrid');
        if (!grid) return;
        var cards = grid.querySelectorAll('.event-card');

        chips.forEach(function(chip) {
            chip.addEventListener('click', function(ev) {
                ev.preventDefault();
                chips.forEach(function(c) { c.classList.remove('active'); });
                chip.classList.add('active');
                var cat = chip.getAttribute('data-cat');
                cards.forEach(function(card) {
                    if (cat === 'all' || card.getAttribute('data-cat') === cat) {
                        card.style.display = '';
                    } else {
                        card.style.display = 'none';
                    }
                });
            });
        });
    })();
    </script>
</body>
</html>
