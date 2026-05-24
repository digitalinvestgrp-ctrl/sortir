<?php
declare(strict_types=1);
/**
 * Page /membres — liste publique des membres demo Toulouse
 *
 * Lecture seule, accessible sans auth (parcours découverte Adrien).
 * V2.0 — Refonte UI Julie Vasseur (UX Lead dig-holding) 2026-05-25
 * Identité visuelle "Toulouse vivant" : terracotta + crème + Fraunces/Inter.
 * Avatars DiceBear lorelei (illustrations humaines stylées, chaleureuses).
 */

require_once __DIR__ . '/../src/Core/Bootstrap.php';
\App\Core\Bootstrap::init();

use App\Core\Pdo;

$pdo = Pdo::instance();

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

// Background DiceBear par "ton" du quartier (variation chromatique)
$avatarBgPalette = ['fae0d7', 'd4a574', 'e2725b', 'f5e8d2', 'd7ddea', 'd7f0e2'];

header('Content-Type: text/html; charset=utf-8');
?><!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="description" content="Trouvetateam : découvre les membres autour de chez toi à Toulouse. Adultes vérifiés, profils authentiques, communauté locale.">
    <meta name="robots" content="index, follow">
    <meta name="theme-color" content="#E2725B">
    <title>Membres à Toulouse — trouvetateam</title>
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

        /* HEADER */
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

        /* MEMBERS GRID */
        .members-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 22px;
            padding-bottom: 96px;
        }
        @media (min-width: 560px) {
            .members-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 24px;
            }
        }
        @media (min-width: 960px) {
            .members-grid {
                grid-template-columns: repeat(3, 1fr);
                gap: 26px;
            }
        }

        .member-card {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: var(--radius-l);
            overflow: hidden;
            display: flex;
            flex-direction: column;
            transition: transform .25s ease, box-shadow .25s ease, border-color .25s ease;
            position: relative;
        }
        .member-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-l);
            border-color: var(--terracotta-soft);
        }

        .member-cover {
            height: 88px;
            position: relative;
            background: linear-gradient(135deg, var(--terracotta-soft) 0%, var(--gold) 100%);
        }
        .member-card:nth-child(3n+1) .member-cover {
            background: linear-gradient(135deg, var(--terracotta-soft) 0%, var(--gold) 100%);
        }
        .member-card:nth-child(3n+2) .member-cover {
            background: linear-gradient(135deg, #E4E0F5 0%, #B8A7E0 100%);
        }
        .member-card:nth-child(3n+3) .member-cover {
            background: linear-gradient(135deg, #D7F0E2 0%, #7DBF99 100%);
        }
        .member-card:nth-child(4n) .member-cover {
            background: linear-gradient(135deg, #F5E8D2 0%, var(--gold-deep) 100%);
        }

        .member-cover::after {
            content: '';
            position: absolute;
            inset: 0;
            background: radial-gradient(circle at 30% 30%, rgba(255, 248, 241, 0.4) 0%, transparent 50%);
        }

        .member-body {
            padding: 0 22px 22px;
            position: relative;
            display: flex;
            flex-direction: column;
            gap: 12px;
            flex: 1;
        }

        .member-avatar-wrap {
            margin-top: -44px;
            margin-bottom: 6px;
            position: relative;
            z-index: 1;
        }
        .member-avatar {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            object-fit: cover;
            background: var(--bg-cream);
            border: 4px solid var(--bg-card);
            box-shadow: 0 4px 12px rgba(27, 42, 78, 0.1);
        }
        .member-verified {
            position: absolute;
            bottom: 4px;
            left: 60px;
            width: 24px;
            height: 24px;
            border-radius: 50%;
            background: var(--terracotta);
            color: #FFF8F1;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border: 2.5px solid var(--bg-card);
        }

        .member-id {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }
        .member-name {
            font-family: var(--font-display);
            font-size: 20px;
            font-weight: 600;
            letter-spacing: -0.015em;
            color: var(--ink);
            line-height: 1.2;
        }
        .member-meta {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 13.5px;
            color: var(--ink-mute);
            flex-wrap: wrap;
        }
        .member-meta .dot {
            width: 3px;
            height: 3px;
            border-radius: 50%;
            background: var(--ink-mute);
        }
        .member-quartier {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            color: var(--terracotta-deep);
            font-weight: 500;
        }
        .member-quartier svg { color: var(--terracotta); }

        .member-bio {
            font-size: 14.5px;
            color: var(--ink-soft);
            line-height: 1.55;
            display: -webkit-box;
            -webkit-line-clamp: 4;
            -webkit-box-orient: vertical;
            overflow: hidden;
            flex-grow: 1;
        }

        .member-stats {
            display: flex;
            gap: 8px;
            padding-top: 14px;
            border-top: 1px solid var(--border-soft);
            margin-top: 4px;
        }
        .stat-item {
            flex: 1;
            text-align: center;
            padding: 8px 6px;
            background: var(--bg-cream);
            border-radius: 10px;
        }
        .stat-item .val {
            font-family: var(--font-display);
            font-size: 18px;
            font-weight: 700;
            color: var(--ink);
            line-height: 1;
            display: block;
            margin-bottom: 4px;
        }
        .stat-item .lbl {
            font-size: 11.5px;
            font-weight: 500;
            color: var(--ink-mute);
            text-transform: uppercase;
            letter-spacing: 0.05em;
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

        /* FOOTER */
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
                <a href="/sorties">Sorties</a>
                <a href="/membres" class="active">Membres</a>
                <a href="/#comment">Comment ça marche</a>
                <a href="/#rejoindre" class="nav-cta">Rejoindre</a>
            </nav>
        </div>
    </header>

    <main>
        <section class="container">
            <div class="page-head">
                <div class="eyebrow">Toulouse · membres vérifiés</div>
                <h1>La communauté qui te <em>ressemble</em>.</h1>
                <p class="sub"><?= count($members) ?> membre<?= count($members) > 1 ? 's' : '' ?> autour de chez toi, du Capitole à Rangueil. Téléphone vérifié, profil authentique, pas de bot ni de compte fantôme.</p>
            </div>

            <?php if (empty($members)): ?>
                <div class="empty">
                    <span class="empty-icon">👋</span>
                    <h3>Aucun membre à afficher</h3>
                    <p>Sois le premier de ton quartier à rejoindre la communauté.</p>
                </div>
            <?php else: ?>
                <div class="members-grid">
                    <?php foreach ($members as $idx => $m):
                        $name = $m['display_name'] ?: $m['name'];
                        $bgIdx = $idx % count($avatarBgPalette);
                        $avatarBg = $avatarBgPalette[$bgIdx];
                        // DiceBear lorelei : illustrations humaines stylées, chaleureuses
                        $avatar = $m['avatar_url'] && !str_contains($m['avatar_url'], 'ui-avatars.com')
                            ? $m['avatar_url']
                            : 'https://api.dicebear.com/7.x/lorelei/svg?seed=' . urlencode($name) . '&backgroundColor=' . $avatarBg;
                        $rep = (int) $m['reputation_score'];
                        $sorties = (int) $m['attended_count'];
                        $hood = $m['neighborhood'] ?? 'Toulouse';
                    ?>
                        <article class="member-card">
                            <div class="member-cover"></div>
                            <div class="member-body">
                                <div class="member-avatar-wrap">
                                    <img src="<?= htmlspecialchars($avatar, ENT_QUOTES, 'UTF-8') ?>" alt="" class="member-avatar" loading="lazy">
                                    <span class="member-verified" title="Téléphone vérifié">
                                        <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round">
                                            <polyline points="20 6 9 17 4 12"/>
                                        </svg>
                                    </span>
                                </div>
                                <div class="member-id">
                                    <span class="member-name"><?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?>, <?= (int) $m['age'] ?> ans</span>
                                    <div class="member-meta">
                                        <span class="member-quartier">
                                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                                <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/>
                                                <circle cx="12" cy="10" r="3"/>
                                            </svg>
                                            <?= htmlspecialchars($hood, ENT_QUOTES, 'UTF-8') ?>
                                        </span>
                                    </div>
                                </div>
                                <p class="member-bio"><?= htmlspecialchars($m['bio'] ?? '', ENT_QUOTES, 'UTF-8') ?></p>
                                <div class="member-stats">
                                    <div class="stat-item">
                                        <span class="val"><?= $rep ?></span>
                                        <span class="lbl">Réputation</span>
                                    </div>
                                    <div class="stat-item">
                                        <span class="val"><?= $sorties ?></span>
                                        <span class="lbl">Sorties</span>
                                    </div>
                                </div>
                            </div>
                        </article>
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
</body>
</html>
