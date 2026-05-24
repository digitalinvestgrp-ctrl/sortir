<?php
declare(strict_types=1);
/**
 * Page /membres — liste publique des membres demo Toulouse
 *
 * Lecture seule, accessible sans auth (parcours decouverte Adrien).
 * Style coherent avec public/index.html.
 */

require_once __DIR__ . '/../src/Core/Bootstrap.php';
\App\Core\Bootstrap::init();

use App\Core\Pdo;

$pdo = Pdo::instance();

// Query : tous les membres avec profil + quartier + age calcule
$sql = "
    SELECT
        u.id, u.name, u.birthdate,
        p.display_name, p.bio, p.avatar_url, p.reputation_score, p.attended_count,
        n.name AS neighborhood,
        c.name AS city,
        TIMESTAMPDIFF(YEAR, u.birthdate, CURDATE()) AS age
    FROM users u
    INNER JOIN profiles p ON p.user_id = u.id
    LEFT JOIN neighborhoods n ON n.id = p.neighborhood_id
    LEFT JOIN cities c ON c.id = n.city_id
    WHERE u.is_blocked = 0
      AND p.bio IS NOT NULL
      AND p.bio != ''
    ORDER BY p.reputation_score DESC, u.id ASC
    LIMIT 50
";

$members = $pdo->query($sql)->fetchAll();

header('Content-Type: text/html; charset=utf-8');
?><!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="description" content="Trouvetateam : decouvre les membres pres de chez toi a Toulouse.">
    <meta name="robots" content="noindex, follow">
    <meta name="theme-color" content="#0f172a">
    <title>Membres a Toulouse - trouvetateam</title>
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

        .members-list {
            display: grid;
            grid-template-columns: 1fr;
            gap: 16px;
            padding-bottom: 64px;
        }
        @media (min-width: 640px) {
            .members-list {
                grid-template-columns: repeat(2, 1fr);
                gap: 18px;
            }
        }
        @media (min-width: 960px) {
            .members-list {
                grid-template-columns: repeat(3, 1fr);
            }
        }

        .member-card {
            background: #ffffff;
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: 22px;
            display: flex;
            flex-direction: column;
            gap: 14px;
            transition: border-color .15s ease;
        }
        .member-card:hover { border-color: var(--text-soft); }

        .member-head {
            display: flex;
            align-items: center;
            gap: 14px;
        }
        .member-avatar {
            width: 54px;
            height: 54px;
            border-radius: 50%;
            object-fit: cover;
            background: var(--accent-soft);
            flex-shrink: 0;
        }
        .member-id {
            display: flex;
            flex-direction: column;
            gap: 2px;
            min-width: 0;
        }
        .member-name {
            font-size: 16px;
            font-weight: 600;
            letter-spacing: -0.015em;
            color: var(--text);
            line-height: 1.2;
        }
        .member-sub {
            font-size: 13px;
            color: var(--text-soft);
        }

        .member-bio {
            font-size: 14px;
            color: var(--text-muted);
            line-height: 1.55;
            display: -webkit-box;
            -webkit-line-clamp: 4;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .member-stats {
            display: flex;
            gap: 14px;
            padding-top: 10px;
            border-top: 1px solid var(--border);
            font-size: 12.5px;
            color: var(--text-soft);
        }
        .member-stats span strong {
            display: block;
            font-size: 15px;
            font-weight: 600;
            color: var(--text);
            margin-bottom: 2px;
        }

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
                <h1>Membres Toulouse</h1>
                <p class="sub"><?= count($members) ?> membre<?= count($members) > 1 ? 's' : '' ?> autour de chez toi.</p>
            </div>

            <?php if (empty($members)): ?>
                <div class="empty">
                    Aucun membre a afficher pour le moment.
                </div>
            <?php else: ?>
                <div class="members-list">
                    <?php foreach ($members as $m):
                        $name = $m['display_name'] ?: $m['name'];
                        $avatar = $m['avatar_url'] ?: 'https://ui-avatars.com/api/?name=' . urlencode($name) . '&background=6366f1&color=ffffff&size=200&bold=true';
                    ?>
                        <article class="member-card">
                            <div class="member-head">
                                <img src="<?= htmlspecialchars($avatar, ENT_QUOTES, 'UTF-8') ?>" alt="" class="member-avatar" loading="lazy">
                                <div class="member-id">
                                    <span class="member-name"><?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?>, <?= (int) $m['age'] ?> ans</span>
                                    <span class="member-sub"><?= htmlspecialchars($m['neighborhood'] ?? 'Toulouse', ENT_QUOTES, 'UTF-8') ?></span>
                                </div>
                            </div>
                            <p class="member-bio"><?= htmlspecialchars($m['bio'] ?? '', ENT_QUOTES, 'UTF-8') ?></p>
                            <div class="member-stats">
                                <span><strong><?= (int) $m['reputation_score'] ?></strong>Reputation</span>
                                <span><strong><?= (int) $m['attended_count'] ?></strong>Sorties</span>
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
