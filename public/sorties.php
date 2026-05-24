<?php
declare(strict_types=1);
/**
 * Page /sorties — liste publique des 5 prochains evenements Toulouse
 *
 * Lecture seule (pas de mutation), accessible sans auth (parcours decouverte Adrien).
 * Style coherent avec public/index.html (memes vars CSS, mobile-first).
 */

require_once __DIR__ . '/../src/Core/Bootstrap.php';
\App\Core\Bootstrap::init();

use App\Core\Pdo;

$pdo = Pdo::instance();

// Query : tous les events publies a venir, avec RSVP count, organisateur, quartier
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
    $months = ['01' => 'janvier', '02' => 'fevrier', '03' => 'mars', '04' => 'avril', '05' => 'mai', '06' => 'juin', '07' => 'juillet', '08' => 'aout', '09' => 'septembre', '10' => 'octobre', '11' => 'novembre', '12' => 'decembre'];
    $ts = strtotime($datetime);
    $day = $days[date('l', $ts)];
    $dayNum = date('j', $ts);
    $month = $months[date('m', $ts)];
    $time = date('H\hi', $ts);
    return ucfirst($day) . " {$dayNum} {$month} - {$time}";
}

$categoryLabels = [
    'aperitif' => 'Apero',
    'sport' => 'Sport',
    'jeux' => 'Jeux',
    'culturel' => 'Culturel',
    'musique' => 'Musique',
    'rando' => 'Rando',
];

header('Content-Type: text/html; charset=utf-8');
?><!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="description" content="Trouvetateam : decouvre les prochaines sorties pres de chez toi a Toulouse.">
    <meta name="robots" content="noindex, follow">
    <meta name="theme-color" content="#0f172a">
    <title>Sorties a Toulouse - trouvetateam</title>
    <link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'%3E%3Crect width='100' height='100' rx='22' fill='%230f172a'/%3E%3Ctext x='50' y='62' font-family='-apple-system,Inter,sans-serif' font-size='44' font-weight='700' fill='%23ffffff' text-anchor='middle'%3Ett%3C/text%3E%3C/svg%3E">
    <style>
        :root {
            --bg: #ffffff;
            --bg-soft: #f8fafc;
            --text: #0f172a;
            --text-muted: #475569;
            --text-soft: #64748b;
            --border: #e2e8f0;
            --accent: #6366f1;
            --accent-soft: #eef2ff;
            --radius: 12px;
            --max-w: 960px;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        html { -webkit-text-size-adjust: 100%; scroll-behavior: smooth; }
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', system-ui, sans-serif;
            background: var(--bg);
            color: var(--text);
            line-height: 1.6;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }
        .container { max-width: var(--max-w); margin: 0 auto; padding: 0 24px; }

        header.site {
            border-bottom: 1px solid var(--border);
            padding: 20px 0;
            position: sticky;
            top: 0;
            background: rgba(255, 255, 255, 0.85);
            backdrop-filter: saturate(180%) blur(10px);
            -webkit-backdrop-filter: saturate(180%) blur(10px);
            z-index: 10;
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
            gap: 10px;
            font-weight: 700;
            font-size: 18px;
            letter-spacing: -0.02em;
            color: var(--text);
            text-decoration: none;
        }
        .logo-mark {
            width: 32px;
            height: 32px;
            border-radius: 8px;
            background: var(--text);
            color: #ffffff;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
            font-weight: 700;
            letter-spacing: -0.04em;
        }
        .back-link {
            font-size: 13.5px;
            color: var(--text-muted);
            text-decoration: none;
            border-bottom: 1px solid transparent;
            transition: border-color .15s ease;
        }
        .back-link:hover { border-color: var(--text-soft); }

        .page-head {
            padding: 48px 0 32px;
        }
        .page-head h1 {
            font-size: clamp(28px, 4vw, 38px);
            line-height: 1.15;
            letter-spacing: -0.025em;
            font-weight: 700;
            margin-bottom: 8px;
        }
        .page-head .sub {
            font-size: 15.5px;
            color: var(--text-muted);
        }

        .events-list {
            display: grid;
            grid-template-columns: 1fr;
            gap: 16px;
            padding-bottom: 64px;
        }
        @media (min-width: 720px) {
            .events-list {
                grid-template-columns: repeat(2, 1fr);
                gap: 20px;
            }
        }

        .event-card {
            background: #ffffff;
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: 22px 22px 20px;
            display: flex;
            flex-direction: column;
            gap: 14px;
            transition: border-color .15s ease, transform .15s ease;
        }
        .event-card:hover {
            border-color: var(--text-soft);
        }

        .event-head {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 12px;
        }
        .event-cat {
            display: inline-block;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: var(--accent);
            background: var(--accent-soft);
            padding: 4px 10px;
            border-radius: 6px;
        }
        .event-sponsored {
            font-size: 11px;
            font-weight: 600;
            color: #92400e;
            background: #fef3c7;
            padding: 4px 10px;
            border-radius: 6px;
        }

        .event-title {
            font-size: 18px;
            font-weight: 600;
            letter-spacing: -0.015em;
            line-height: 1.3;
            color: var(--text);
        }
        .event-desc {
            font-size: 14px;
            color: var(--text-muted);
            line-height: 1.55;
            display: -webkit-box;
            -webkit-line-clamp: 3;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .event-meta {
            display: flex;
            flex-direction: column;
            gap: 6px;
            font-size: 13.5px;
            color: var(--text-soft);
            padding-top: 10px;
            border-top: 1px solid var(--border);
        }
        .event-meta-row {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .event-meta-row strong {
            color: var(--text-muted);
            font-weight: 600;
        }

        .event-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 4px;
        }
        .event-host {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 13px;
            color: var(--text-muted);
        }
        .event-host img {
            width: 26px;
            height: 26px;
            border-radius: 50%;
            object-fit: cover;
            background: var(--accent-soft);
        }
        .btn-decoratif {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 16px;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 600;
            background: var(--text);
            color: #ffffff;
            text-decoration: none;
            transition: background-color .15s ease;
        }
        .btn-decoratif:hover { background: #1e293b; }

        .empty {
            padding: 48px 24px;
            text-align: center;
            color: var(--text-soft);
            background: var(--bg-soft);
            border: 1px dashed var(--border);
            border-radius: var(--radius);
        }

        footer.site {
            border-top: 1px solid var(--border);
            padding: 32px 0;
            color: var(--text-soft);
            font-size: 13.5px;
        }
        footer.site .inner {
            display: flex;
            flex-direction: column;
            gap: 12px;
            align-items: flex-start;
            justify-content: space-between;
        }
        @media (min-width: 640px) {
            footer.site .inner {
                flex-direction: row;
                align-items: center;
            }
        }
        footer.site a {
            color: var(--text-muted);
            text-decoration: none;
            border-bottom: 1px solid transparent;
            transition: border-color .15s ease;
        }
        footer.site a:hover { border-color: var(--text-soft); }
        .footer-links { display: flex; gap: 18px; flex-wrap: wrap; }
    </style>
</head>
<body>
    <header class="site">
        <div class="container inner">
            <a href="/" class="logo">
                <span class="logo-mark">tt</span>
                <span>trouvetateam</span>
            </a>
            <a href="/" class="back-link">&larr; Retour</a>
        </div>
    </header>

    <main>
        <section class="container">
            <div class="page-head">
                <h1>Prochaines sorties</h1>
                <p class="sub"><?= count($events) ?> sortie<?= count($events) > 1 ? 's' : '' ?> a Toulouse, ouvert<?= count($events) > 1 ? 'es' : 'e' ?> aux membres.</p>
            </div>

            <?php if (empty($events)): ?>
                <div class="empty">
                    Aucune sortie prevue pour le moment. Reviens bientot.
                </div>
            <?php else: ?>
                <div class="events-list">
                    <?php foreach ($events as $e):
                        $catLabel = $categoryLabels[$e['category']] ?? ucfirst($e['category'] ?? 'Sortie');
                        $hostAvatar = $e['host_avatar'] ?: 'https://ui-avatars.com/api/?name=' . urlencode($e['host_name'] ?? 'X') . '&background=6366f1&color=ffffff&size=200&bold=true';
                    ?>
                        <article class="event-card">
                            <div class="event-head">
                                <span class="event-cat"><?= htmlspecialchars($catLabel, ENT_QUOTES, 'UTF-8') ?></span>
                                <?php if (!empty($e['is_sponsored'])): ?>
                                    <span class="event-sponsored">Sponsorise</span>
                                <?php endif; ?>
                            </div>
                            <h2 class="event-title"><?= htmlspecialchars($e['title'], ENT_QUOTES, 'UTF-8') ?></h2>
                            <p class="event-desc"><?= htmlspecialchars($e['description'] ?? '', ENT_QUOTES, 'UTF-8') ?></p>
                            <div class="event-meta">
                                <div class="event-meta-row">
                                    <strong>Quand</strong>
                                    <span><?= formatFrenchDate($e['starts_at']) ?></span>
                                </div>
                                <div class="event-meta-row">
                                    <strong>Ou</strong>
                                    <span><?= htmlspecialchars(($e['neighborhood'] ?? '-') . ', ' . ($e['city'] ?? 'Toulouse'), ENT_QUOTES, 'UTF-8') ?></span>
                                </div>
                                <div class="event-meta-row">
                                    <strong>Places</strong>
                                    <span><?= (int) $e['rsvps_count'] ?> / <?= $e['capacity'] !== null ? (int) $e['capacity'] : '&infin;' ?></span>
                                </div>
                            </div>
                            <div class="event-footer">
                                <div class="event-host">
                                    <img src="<?= htmlspecialchars($hostAvatar, ENT_QUOTES, 'UTF-8') ?>" alt="" loading="lazy">
                                    <span>par <?= htmlspecialchars($e['host_name'] ?? 'Anonyme', ENT_QUOTES, 'UTF-8') ?></span>
                                </div>
                                <a href="#bientot" class="btn-decoratif">Voir</a>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
    </main>

    <footer class="site">
        <div class="container inner">
            <div>(c) 2026 trouvetateam - DIG</div>
            <div class="footer-links">
                <a href="/sorties">Sorties</a>
                <a href="/membres">Membres</a>
                <a href="mailto:contact@trouvetateam.fr">Contact</a>
            </div>
        </div>
    </footer>
</body>
</html>
