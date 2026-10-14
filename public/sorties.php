<?php
declare(strict_types=1);
/**
 * Page /sorties — liste publique des prochaines sorties (Toulouse).
 *
 * Lecture seule, accessible sans auth (parcours decouverte).
 * V3.0 — Re-skin design Node "TrouveTaTeam" (orange, Bricolage Grotesque / Plus Jakarta Sans,
 *        cartes .sortie-card) — PROD 2026-10, dig-holding (Mehdi Benali / Lukas Brennan / Clara Music).
 *        Remplace la refonte "Toulouse vivant" terracotta (V2.0, Julie Vasseur, 2026-05-25).
 * Rendu cote serveur, alimente par la base MySQL reelle (table events). Robuste aux erreurs base.
 */

require_once __DIR__ . '/../src/Core/Bootstrap.php';
\App\Core\Bootstrap::init();
require_once __DIR__ . '/_ttt_layout.php';
require_once __DIR__ . '/_ttt_events.php';

use App\Core\Pdo;

// Filtres (depuis les chips + la barre de recherche de l'accueil).
$cat = isset($_GET['cat']) ? ttt_theme_alias(strtolower(trim((string) $_GET['cat']))) : '';
if ($cat !== '' && !ttt_theme_valid($cat)) {
    $cat = '';
}
$qNbhd = (isset($_GET['q']) && ctype_digit((string) $_GET['q'])) ? (int) $_GET['q'] : 0;
$dFrom = isset($_GET['d']) ? trim((string) $_GET['d']) : '';
if ($dFrom !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dFrom)) {
    $dFrom = '';
}
$kw = isset($_GET['kw']) ? trim((string) $_GET['kw']) : '';
if (mb_strlen($kw) > 60) {
    $kw = mb_substr($kw, 0, 60);
}
// Nom du quartier filtre (pour l'affichage).
$qName = '';
if ($qNbhd > 0) {
    foreach (ttt_neighborhoods() as $n) {
        if ((int) $n['id'] === $qNbhd) {
            $qName = (string) $n['name'];
            break;
        }
    }
    if ($qName === '') {
        $qNbhd = 0; // id inconnu -> on ignore
    }
}

$events = [];
$dbOk = true;
try {
    $pdo = Pdo::instance();
    $where = "e.status = 'published' AND e.visibility = 'public' AND e.starts_at >= NOW()";
    $params = [];
    if ($cat !== '') {
        $where .= ' AND e.category = :cat';
        $params[':cat'] = $cat;
    }
    if ($qNbhd > 0) {
        $where .= ' AND e.neighborhood_id = :q';
        $params[':q'] = $qNbhd;
    }
    if ($dFrom !== '') {
        $where .= ' AND e.starts_at >= :dfrom';
        $params[':dfrom'] = $dFrom . ' 00:00:00';
    }
    if ($kw !== '') {
        $where .= ' AND e.title LIKE :kw';
        $params[':kw'] = '%' . $kw . '%';
    }
    $sql = "
        SELECT e.id, e.title, e.description, e.category, e.starts_at, e.capacity, e.is_sponsored,
               n.name AS neighborhood, c.name AS city, u.name AS host_name,
               (SELECT COUNT(*) FROM rsvps r WHERE r.event_id = e.id AND r.status = 'going') AS rsvps_count
        FROM events e
        LEFT JOIN neighborhoods n ON n.id = e.neighborhood_id
        LEFT JOIN cities c ON c.id = e.city_id
        LEFT JOIN users u ON u.id = e.host_user_id
        WHERE {$where}
        ORDER BY e.is_sponsored DESC, e.starts_at ASC
        LIMIT 50
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $events = $stmt->fetchAll();
} catch (\Throwable $e) {
    error_log('[sorties] DB error: ' . $e->getMessage());
    $dbOk = false;
    $events = [];
}
$count = count($events);
$catLabel = $cat !== '' ? ttt_category($cat)[2] : null;

ttt_head(
    ($catLabel ? "Sorties {$catLabel} à Toulouse" : 'Sorties à Toulouse') . ' | TrouveTaTeam',
    'Découvre les prochaines sorties à Toulouse : sport, apéro, rando, jeux, culture et musique entre adultes vérifiés. Inscription en 1 clic, gratuit.',
    'sorties',
    'index'
);
?>
  <main id="main">
    <section class="hero" style="padding-bottom:0">
      <div class="hero-deco" aria-hidden="true">
        <span class="blob blob-1"></span><span class="blob blob-2"></span>
      </div>
      <div class="container">
        <p class="hero-kicker"><span class="pulse" aria-hidden="true"></span>Toulouse · prochaines sorties</p>
        <h1 class="hero-title" style="font-size:clamp(1.8rem,6vw,2.8rem);max-width:none;margin-bottom:14px">
          Ta prochaine sortie <span class="text-grad">t'attend</span>.
        </h1>
        <div class="chips theme-chips" role="group" aria-label="Filtrer par thème">
          <a class="chip theme-chip<?= $cat === '' ? ' active' : '' ?>" href="/sorties">Tout</a>
          <?php foreach (ttt_categories() as $c): [$em, $col, $lab] = ttt_category($c); ?>
            <a class="chip theme-chip<?= $cat === $c ? ' active' : '' ?>" style="--tc:<?= ttt_e($col) ?>"
               <?= $cat === $c ? 'aria-pressed="true"' : '' ?> href="/sorties?cat=<?= ttt_e($c) ?>">
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
            <h2 id="results-title"><?= $catLabel ? ttt_e($catLabel) : 'Toutes les sorties' ?></h2>
            <p class="results-sub">
              <?php if ($count > 0): ?>
                <span class="count"><?= $count ?></span><span class="sub-rest">sortie<?= $count > 1 ? 's' : '' ?> à venir</span>
              <?php else: ?>
                <span class="sub-rest">Aucune sortie pour ce filtre.</span>
              <?php endif; ?>
            </p>
          </div>
        </div>

        <?php if (!$dbOk): ?>
          <p class="notice">Impossible de charger les sorties pour le moment. Réessaie dans un instant.</p>
        <?php endif; ?>

        <?php if ($count > 0): ?>
          <div class="sortie-grid" id="results">
            <?php foreach ($events as $ev) {
                echo ttt_sortie_card($ev);
            } ?>
          </div>
        <?php elseif ($dbOk): ?>
          <div class="empty card" id="empty">
            <div class="empty-big" aria-hidden="true">🗓️</div>
            <p>
              <?php if ($cat !== ''): ?>
                Pas de sortie <?= ttt_e(strtolower((string) $catLabel)) ?> prévue pour l'instant. Regarde les autres thèmes ou reviens bientôt.
              <?php else: ?>
                Pas de sortie prévue dans les prochains jours. L'agenda se remplit vite — reviens bientôt.
              <?php endif; ?>
            </p>
            <div class="empty-actions">
              <?php if ($cat !== ''): ?><a class="btn btn-primary" href="/sorties">Voir toutes les sorties</a><?php endif; ?>
              <a class="btn" href="/membres">Voir les membres</a>
            </div>
          </div>
        <?php endif; ?>
      </div>
    </section>

    <section class="container">
      <a class="organize card" href="/">
        <span class="organize-art" aria-hidden="true"><span>🏓</span><span>🍻</span><span>🥾</span></span>
        <span class="organize-txt"><strong>Rien à ton goût ?</strong> Explore tous les thèmes et trouve la sortie qui te ressemble, près de chez toi.</span>
        <span class="organize-cta"><span class="organize-cta-txt">Retour à l'accueil</span><span class="organize-arrow" aria-hidden="true">→</span></span>
      </a>
    </section>
  </main>
<?php ttt_footer(); ?>
