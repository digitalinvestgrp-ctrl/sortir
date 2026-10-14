<?php
declare(strict_types=1);
/**
 * home.php — Accueil / exploration (design Node "TrouveTaTeam").
 *
 * Rendu cote serveur, alimente par la base MySQL reelle (table events).
 * Re-skin PROD 2026-10 — dig-holding (Mehdi Benali / Lukas Brennan / Clara Music).
 * Robuste : toute erreur base est capturee, l'accueil reste affiche (hero + etat vide).
 */

require_once __DIR__ . '/../src/Core/Bootstrap.php';
\App\Core\Bootstrap::init();
require_once __DIR__ . '/_ttt_layout.php';

use App\Core\Pdo;

$events = [];
try {
    $pdo = Pdo::instance();
    $sql = "
        SELECT e.id, e.title, e.category, e.starts_at, e.capacity, e.is_sponsored,
               n.name AS neighborhood, c.name AS city, u.name AS host_name,
               (SELECT COUNT(*) FROM rsvps r WHERE r.event_id = e.id AND r.status = 'going') AS rsvps_count
        FROM events e
        LEFT JOIN neighborhoods n ON n.id = e.neighborhood_id
        LEFT JOIN cities c ON c.id = e.city_id
        LEFT JOIN users u ON u.id = e.host_user_id
        WHERE e.status = 'published' AND e.visibility = 'public' AND e.starts_at >= NOW()
        ORDER BY e.is_sponsored DESC, e.starts_at ASC
        LIMIT 6
    ";
    $events = $pdo->query($sql)->fetchAll();
} catch (\Throwable $e) {
    error_log('[home] DB error: ' . $e->getMessage());
    $events = [];
}
$count = count($events);

ttt_head(
    'Sorties sport & loisirs entre amis à Toulouse | TrouveTaTeam',
    'Trouve des partenaires de sport, apéro, rando, jeux ou sorties culture près de chez toi à Toulouse, ou organise ta sortie. Gratuit, inscription en 1 clic.',
    'index',
    'index'
);
?>
  <style>
    /* Micro-fix conserve du passage precedent : champs a 16px pour empecher le zoom
       auto iOS au focus (evite que la barre "saute"/semble disparaitre sur iPhone). */
    .search .input { font-size: 16px; }
  </style>
  <main id="main">
    <section class="hero">
      <div class="hero-deco" aria-hidden="true">
        <span class="blob blob-1"></span><span class="blob blob-2"></span>
        <span class="float f1">⚽</span><span class="float f2">🎾</span><span class="float f3">🚴</span><span class="float f4">🎲</span><span class="float f5">🥾</span>
      </div>
      <div class="container hero-inner">
        <p class="hero-kicker"><span class="pulse" aria-hidden="true"></span>Sport &amp; loisirs près de chez toi</p>
        <h1 class="hero-title">Trouve ta sortie. <span class="text-grad">Maintenant.</span></h1>

        <form class="search card" id="search" role="search" aria-label="Rechercher une sortie" method="get" action="/sorties">
          <div class="search-theme">
            <label for="q-theme" class="sr-only">Thème</label>
            <select id="q-theme" class="input" name="cat">
              <option value="">Tous les thèmes</option>
              <?php foreach (ttt_categories() as $c): [$em, $col, $lab] = ttt_category($c); ?>
                <option value="<?= ttt_e($c) ?>"><?= $em . ' ' . ttt_e($lab) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="search-ville">
            <label for="q-ville" class="sr-only">Ville</label>
            <input id="q-ville" class="input" name="ville" type="search"
                   placeholder="Quelle ville ?" autocomplete="off" enterkeyhint="search" value="Toulouse">
          </div>
          <button class="btn btn-primary search-btn" type="submit">
            <span aria-hidden="true">🔎</span><span class="search-btn-txt">Rechercher</span>
          </button>
        </form>

        <div class="chips theme-chips" id="theme-chips" role="group" aria-label="Filtrer par thème">
          <?php foreach (ttt_categories() as $c): [$em, $col, $lab] = ttt_category($c); ?>
            <a class="chip theme-chip" style="--tc:<?= ttt_e($col) ?>" href="/sorties?cat=<?= ttt_e($c) ?>">
              <span class="chip-emoji" aria-hidden="true"><?= $em ?></span><?= ttt_e($lab) ?>
            </a>
          <?php endforeach; ?>
        </div>
      </div>
    </section>

    <section class="section results-section" aria-labelledby="results-title">
      <div class="container">
        <div class="results-head">
          <div>
            <h2 id="results-title">Les prochaines sorties</h2>
            <p class="results-sub">
              <?php if ($count > 0): ?>
                <span class="count"><?= $count ?></span><span class="sub-rest">sortie<?= $count > 1 ? 's' : '' ?> à venir à Toulouse</span>
              <?php else: ?>
                <span class="sub-rest">Aucune sortie ouverte pour l'instant — lance la première.</span>
              <?php endif; ?>
            </p>
          </div>
        </div>

        <?php if ($count > 0): ?>
          <div class="sortie-grid" id="results">
            <?php foreach ($events as $ev) {
                echo ttt_sortie_card($ev);
            } ?>
          </div>
          <p style="margin:18px 0 0;text-align:center">
            <a class="btn" href="/sorties">Voir toutes les sorties →</a>
          </p>
        <?php else: ?>
          <div class="empty card" id="empty">
            <div class="empty-big" aria-hidden="true">🗓️</div>
            <p>Pas de sortie prévue dans les prochains jours. L'agenda se remplit vite : reviens bientôt ou propose la tienne.</p>
            <div class="empty-actions">
              <a class="btn" href="/membres">Voir les membres</a>
            </div>
          </div>
        <?php endif; ?>
      </div>
    </section>

    <section class="container">
      <a class="organize card" href="/sorties">
        <span class="organize-art" aria-hidden="true"><span>🏓</span><span>🍻</span><span>🥾</span></span>
        <span class="organize-txt"><strong>Tu organises ?</strong> Crée ta sortie et envoie le lien à tes potes. Sport, apéro, rando, jeux, culture — à toi de jouer.</span>
        <span class="organize-cta"><span class="organize-cta-txt">Explorer les sorties</span><span class="organize-arrow" aria-hidden="true">→</span></span>
      </a>
    </section>

    <section class="container seo-blurb" aria-labelledby="seo-blurb-title">
      <h2 id="seo-blurb-title">Des sorties sport &amp; loisirs entre amis, à Toulouse</h2>
      <p>Apéro en terrasse, running, randonnée dans les Pyrénées, basket, jeux de société, sorties culture ou concerts : trouve des partenaires près de chez toi et rejoins une sortie en un clic, ou organise la tienne. Communauté locale d'adultes vérifiés, sans bots et sans GPS partagé.</p>
    </section>
  </main>
<?php ttt_footer(); ?>
