<?php
declare(strict_types=1);
/**
 * _ttt_layout.php — Partiel de mise en page partage (design "Node TrouveTaTeam").
 *
 * Re-skin PROD 2026-10 (dig-holding : Mehdi Benali archi / Lukas Brennan front / Clara Music UX).
 * Reprend l'identite visuelle de la version Node (orange #ff5a36, Bricolage Grotesque +
 * Plus Jakarta Sans, cartes .sortie-card, hero, chips) servie par les CSS copies dans
 * /assets/style.css + /assets/index.css + /assets/sortie.css.
 *
 * Les pages PHP (home.php, sorties.php, membres.php) restent rendues cote serveur et
 * alimentees par la base MySQL reelle. Ce fichier ne fait AUCUNE requete : helpers purs.
 *
 * MAJ PROD 2026-10 : en-tete rendu "auth-aware" (Connexion / Mon compte / Creer / Deconnexion)
 * via _ttt_auth.php (lecture session resiliente, ne casse jamais une page publique).
 */

require_once __DIR__ . '/_ttt_auth.php';
require_once __DIR__ . '/_ttt_themes.php';

/** Mappe une categorie de la base (aperitif/sport/jeux/culturel/musique/rando) vers
 *  le style visuel Node : [emoji, couleur de theme, libelle]. */
function ttt_category(?string $cat): array
{
    // Source unique de verite : _ttt_themes.php (taxonomie enrichie 2026-10).
    return ttt_theme_meta($cat);
}

/** Liste ordonnee des themes pour les chips de filtre (taxonomie complete). */
function ttt_categories(): array
{
    return ttt_theme_slugs();
}

function ttt_e($v): string
{
    return htmlspecialchars((string) ($v ?? ''), ENT_QUOTES, 'UTF-8');
}

/** Initiales (2 lettres max) pour un avatar texte. */
function ttt_initials(?string $name): string
{
    $parts = preg_split('/\s+/', trim((string) $name)) ?: [];
    $out = '';
    foreach ($parts as $p) {
        if ($p !== '') {
            $out .= mb_strtoupper(mb_substr($p, 0, 1));
        }
        if (mb_strlen($out) >= 2) {
            break;
        }
    }
    return $out !== '' ? $out : '?';
}

/** Couleur d'avatar stable deterministe a partir d'un nom. */
function ttt_avatar_color(?string $name): string
{
    $palette = ['#ff5a36', '#6c5ce7', '#12a372', '#0ea5e9', '#e11d48', '#d97706', '#2563eb', '#a855f7'];
    $h = 0;
    foreach (str_split((string) $name) as $c) {
        $h = ($h * 31 + ord($c)) & 0xFFFFFFFF;
    }
    return $palette[abs($h) % count($palette)];
}

/** Date FR longue : « Samedi 18 octobre · 19h30 ». */
function ttt_date_fr(string $datetime): string
{
    $days = ['Sunday' => 'dimanche', 'Monday' => 'lundi', 'Tuesday' => 'mardi', 'Wednesday' => 'mercredi',
        'Thursday' => 'jeudi', 'Friday' => 'vendredi', 'Saturday' => 'samedi'];
    $months = ['01' => 'janvier', '02' => 'février', '03' => 'mars', '04' => 'avril', '05' => 'mai', '06' => 'juin',
        '07' => 'juillet', '08' => 'août', '09' => 'septembre', '10' => 'octobre', '11' => 'novembre', '12' => 'décembre'];
    $ts = strtotime($datetime);
    if ($ts === false) {
        return ttt_e($datetime);
    }
    $today = strtotime('today');
    $diffDays = (int) floor(($ts - $today) / 86400);
    $time = date('H\hi', $ts);
    if ($diffDays === 0) {
        return "Aujourd'hui · {$time}";
    }
    if ($diffDays === 1) {
        return "Demain · {$time}";
    }
    $day = ucfirst($days[date('l', $ts)] ?? '');
    return "{$day} " . date('j', $ts) . ' ' . ($months[date('m', $ts)] ?? '') . " · {$time}";
}

/**
 * Carte de sortie au format Node (.card.sortie-card). Rendue en <a> cliquable vers
 * la page de detail /sortie/{id}. $ev attend les colonnes de la requete events.
 */
function ttt_sortie_card(array $ev): string
{
    $eventId = (int) ($ev['id'] ?? 0);
    [$emoji, $color, $label] = ttt_category($ev['category'] ?? null);
    $when = ttt_date_fr((string) ($ev['starts_at'] ?? ''));
    $place = trim((string) ($ev['neighborhood'] ?? ''));
    $city = trim((string) ($ev['city'] ?? 'Toulouse'));
    $where = $place !== '' ? "{$place} · {$city}" : $city;
    $host = (string) ($ev['host_name'] ?? 'un membre');
    $count = (int) ($ev['rsvps_count'] ?? 0);
    $cap = isset($ev['capacity']) ? (int) $ev['capacity'] : 0;

    $tags = [];
    $tags[] = '<span class="badge badge-brand">' . ttt_e($label) . '</span>';
    if (!empty($ev['is_sponsored'])) {
        $tags[] = '<span class="badge badge-dark">★ À la une</span>';
    }
    if ($cap > 0) {
        $left = max(0, $cap - $count);
        if ($left === 0) {
            $tags[] = '<span class="badge badge-dark">Complet</span>';
        } else {
            $tags[] = '<span class="badge ' . ($left <= 2 ? 'badge-brand' : '') . '">' . $left . ' place' . ($left > 1 ? 's' : '') . '</span>';
        }
    }

    // Avatars "faces" : l'hote + un remplissage neutre selon le nombre d'inscrits.
    $faces = '<i style="background:' . ttt_avatar_color($host) . '">' . ttt_e(ttt_initials($host)) . '</i>';
    $extra = min(max($count, 0), 3);
    for ($i = 0; $i < $extra; $i++) {
        $faces .= '<i style="background:#c9ccda">·</i>';
    }

    $people = $count === 0
        ? "Sois le premier · par " . ttt_e($host)
        : $count . ' inscrit' . ($count > 1 ? 's' : '') . ' · par ' . ttt_e($host);

    $href = $eventId > 0 ? '/sortie/' . $eventId : '/sorties';
    return '<a class="card sortie-card" href="' . ttt_e($href) . '" style="--tc:' . ttt_e($color) . '">'
        . '<div class="emoji" aria-hidden="true">' . $emoji . '</div>'
        . '<div class="body">'
        . '<span class="when">' . ttt_e($when) . '</span>'
        . '<p class="title">' . ttt_e($ev['title'] ?? 'Sortie') . '</p>'
        . '<div class="meta">📍 ' . ttt_e($where) . '</div>'
        . '<div class="people"><span class="faces" aria-hidden="true">' . $faces . '</span>' . $people . '</div>'
        . '<div class="tags">' . implode('', $tags) . '</div>'
        . '</div></a>';
}

/**
 * Ouvre le document : <head> complet + <body> + en-tete (design Node).
 * @param string      $title   titre de l'onglet
 * @param string      $desc    meta description
 * @param string      $active  page active : 'index' | 'sorties' | 'membres'
 * @param string|null $pageCss nom de CSS de page additionnel (ex : 'index', 'sortie')
 */
function ttt_head(string $title, string $desc, string $active = 'index', ?string $pageCss = null, ?string $ogImage = null): void
{
    // Cookies a poser AVANT tout output (sinon setcookie echoue : headers deja envoyes).
    if (function_exists('ttt_csrf_token')) {
        ttt_csrf_token(); // garantit la presence du cookie CSRF pour les formulaires de la page
    }
    $flash = function_exists('ttt_flash_pull') ? ttt_flash_pull() : null;
    $v = '2026101402';
    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html>' . "\n";
    ?>
<html lang="fr">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <title><?= ttt_e($title) ?></title>
  <meta name="description" content="<?= ttt_e($desc) ?>">
  <meta name="robots" content="index, follow">
  <meta name="theme-color" content="#ff5a36">
  <meta property="og:site_name" content="TrouveTaTeam">
  <meta property="og:locale" content="fr_FR">
  <meta property="og:type" content="website">
  <meta property="og:title" content="<?= ttt_e($title) ?>">
  <meta property="og:description" content="<?= ttt_e($desc) ?>">
  <meta property="og:image" content="<?= ttt_e($ogImage ?: '/assets/og/default.png') ?>">
  <meta name="twitter:card" content="summary_large_image">
  <meta name="twitter:title" content="<?= ttt_e($title) ?>">
  <meta name="twitter:description" content="<?= ttt_e($desc) ?>">
  <meta name="twitter:image" content="<?= ttt_e($ogImage ?: '/assets/og/default.png') ?>">
  <link rel="icon" href="/favicon.ico" sizes="32x32">
  <link rel="icon" href="/icon.svg" type="image/svg+xml">
  <link rel="apple-touch-icon" href="/apple-touch-icon.png">
  <link rel="manifest" href="/site.webmanifest">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,600;12..96,700;12..96,800&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="/assets/style.css?v=<?= $v ?>">
  <?php if ($pageCss): ?><link rel="stylesheet" href="/assets/<?= ttt_e($pageCss) ?>.css?v=<?= $v ?>"><?php endif; ?>
  <style>.skip-link{position:absolute;left:12px;top:-80px;z-index:10000;padding:10px 16px;border-radius:12px;background:#141729;color:#fff;font-weight:700;text-decoration:none;transition:top .15s}.skip-link:focus{top:12px}.seo-blurb{margin-top:40px;margin-bottom:24px;color:#5f6478;font-size:.9rem;line-height:1.55}.seo-blurb h2{font-size:1rem;margin:0 0 6px;color:#4b5068}.seo-blurb p{margin:0;max-width:70ch}
  .site-header .container{display:flex;align-items:center;gap:18px}
  .nav-auth{margin-left:auto;display:flex;align-items:center;gap:10px}
  .nav-auth .nav-logout{margin:0}
  .nav-account{display:inline-flex;align-items:center;gap:8px;text-decoration:none;color:var(--ink);font-weight:700;padding:5px 12px 5px 5px;border-radius:999px;border:1px solid var(--line);background:var(--surface)}
  .nav-account:hover{border-color:#d9d1c9}
  .nav-account.active{border-color:var(--brand)}
  .nav-ava{width:30px;height:30px;border-radius:50%;display:grid;place-items:center;color:#fff;font-size:.78rem;font-weight:800}
  .nav-account-name{font-size:.9rem;max-width:120px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
  .ttt-flash{padding:13px 16px;border-radius:14px;font-weight:600;font-size:.94rem;border:1px solid}
  .ttt-flash-ok{background:var(--ok-soft,#e7f6ef);color:#0f7a54;border-color:#bfe6d4}
  .ttt-flash-info{background:#eef2ff;color:#3942a3;border-color:#d4daf7}
  .ttt-flash-error{background:#fdeaea;color:#b4231f;border-color:#f6c9c7}
  @media (max-width:760px){.nav-account-name{display:none}.nav-auth .nav-logout{display:none}.tabbar a.create .ico{font-size:1.6rem;line-height:1}}
  </style>
</head>
<body>
  <a class="skip-link" href="#main">Aller au contenu</a>
  <?php ttt_header($active); ?>
  <?php if ($flash): ?>
    <div class="container" style="margin-top:14px">
      <div class="ttt-flash ttt-flash-<?= ttt_e($flash['t']) ?>" role="status"><?= ttt_e($flash['m']) ?></div>
    </div>
  <?php endif; ?>
    <?php
}

/** En-tete sticky + barre d'onglets mobile (markup fidele au composant Node), auth-aware. */
function ttt_header(string $active = 'index'): void
{
    $links = [
        ['index', '/', 'Explorer', '🔎'],
        ['sorties', '/sorties', 'Sorties', '📅'],
        ['membres', '/membres', 'Membres', '👥'],
    ];
    $user = function_exists('ttt_current_user') ? ttt_current_user() : null;
    $firstName = '';
    if ($user) {
        $firstName = trim((string) ($user['name'] ?? ''));
        $sp = strpos($firstName, ' ');
        if ($sp !== false) {
            $firstName = substr($firstName, 0, $sp);
        }
    }
    ?>
<header class="site-header">
  <div class="container">
    <a class="logo" href="/">Trouve<span>Ta</span>Team</a>
    <nav class="nav">
      <?php foreach ($links as [$p, $h, $l, $i]): ?>
        <a href="<?= $h ?>" class="<?= $p === $active ? 'active' : '' ?>"><?= $l ?></a>
      <?php endforeach; ?>
    </nav>
    <div class="nav-auth">
      <?php if ($user): ?>
        <a href="/creer" class="btn btn-primary btn-sm">+ Créer</a>
        <a href="/compte" class="nav-account <?= $active === 'compte' ? 'active' : '' ?>" title="Mon compte">
          <span class="nav-ava" style="background:<?= ttt_e(ttt_avatar_color($user['name'] ?? '')) ?>"><?= ttt_e(ttt_initials($user['name'] ?? '')) ?></span>
          <span class="nav-account-name"><?= ttt_e($firstName !== '' ? $firstName : 'Mon compte') ?></span>
        </a>
        <form method="post" action="/deconnexion" class="nav-logout">
          <?= ttt_csrf_field() ?>
          <button type="submit" class="btn btn-ghost btn-sm" title="Se déconnecter">Déconnexion</button>
        </form>
      <?php else: ?>
        <a href="/connexion" class="btn btn-primary btn-sm nav-login">Connexion</a>
      <?php endif; ?>
    </div>
  </div>
</header>
<nav class="tabbar">
  <?php foreach ($links as [$p, $h, $l, $i]): ?>
    <a href="<?= $h ?>" class="<?= $p === $active ? 'active' : '' ?>"><span class="ico"><?= $i ?></span><?= $l ?></a>
  <?php endforeach; ?>
  <?php if ($user): ?>
    <a href="/creer" class="create <?= $active === 'creer' ? 'active' : '' ?>"><span class="ico">+</span>Créer</a>
    <a href="/compte" class="<?= $active === 'compte' ? 'active' : '' ?>"><span class="ico">👤</span>Compte</a>
  <?php else: ?>
    <a href="/connexion" class="<?= $active === 'connexion' ? 'active' : '' ?>"><span class="ico">🔑</span>Connexion</a>
  <?php endif; ?>
</nav>
    <?php
}

/** Pied de page + fermeture du document. */
function ttt_footer(): void
{
    $year = date('Y');
    ?>
  <footer class="site-footer">
    <div class="container">
      <p>TrouveTaTeam — trouve ou organise des sorties sport &amp; loisirs près de chez toi.</p>
      <p class="muted">© <?= $year ?> TrouveTaTeam · <a href="/sorties">Sorties</a> · <a href="/membres">Membres</a></p>
    </div>
  </footer>
</body>
</html>
    <?php
}
