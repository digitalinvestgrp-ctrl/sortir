<?php
declare(strict_types=1);
/**
 * Page /membres — liste publique des membres (Toulouse).
 *
 * Lecture seule, accessible sans auth (parcours decouverte).
 * V3.0 — Re-skin design Node "TrouveTaTeam" (orange, Bricolage Grotesque / Plus Jakarta Sans,
 *        cartes .card) — PROD 2026-10, dig-holding (Mehdi Benali / Lukas Brennan / Clara Music).
 *        Remplace la refonte "Toulouse vivant" terracotta (V2.0, Julie Vasseur, 2026-05-25).
 *        La page /membres n'a pas d'equivalent dans le front Node : re-skinnee dans la meme
 *        identite visuelle, sa fonction (annuaire des membres) est conservee telle quelle.
 * Rendu cote serveur, alimente par la base MySQL reelle (users + profiles). Robuste aux erreurs base.
 */

require_once __DIR__ . '/../src/Core/Bootstrap.php';
\App\Core\Bootstrap::init();
require_once __DIR__ . '/_ttt_layout.php';

use App\Core\Pdo;

$members = [];
$dbOk = true;
try {
    $pdo = Pdo::instance();
    $sql = "
        SELECT u.id, u.name, u.birthdate,
               p.display_name, p.bio, p.avatar_url, p.reputation_score, p.attended_count,
               n.name AS neighborhood, c.name AS city,
               TIMESTAMPDIFF(YEAR, u.birthdate, CURDATE()) AS age
        FROM users u
        INNER JOIN profiles p ON p.user_id = u.id
        LEFT JOIN neighborhoods n ON n.id = p.neighborhood_id
        LEFT JOIN cities c ON c.id = n.city_id
        WHERE u.is_blocked = 0 AND p.bio IS NOT NULL AND p.bio != ''
        ORDER BY p.reputation_score DESC, u.id ASC
        LIMIT 50
    ";
    $members = $pdo->query($sql)->fetchAll();
} catch (\Throwable $e) {
    error_log('[membres] DB error: ' . $e->getMessage());
    $dbOk = false;
    $members = [];
}
$count = count($members);

ttt_head(
    'Membres à Toulouse | TrouveTaTeam',
    'Découvre les membres de TrouveTaTeam autour de chez toi à Toulouse : adultes vérifiés, profils authentiques, communauté locale sport & loisirs.',
    'membres',
    'index'
);
?>
  <style>
    /* Annuaire des membres — carte dediee (ne reutilise plus la tuile .emoji des sorties
       qui, en desktop, devenait une banniere 96px/2.8rem surdimensionnee). PROD 2026-10. */
    .member-grid { display: grid; gap: 14px; grid-template-columns: 1fr; }
    @media (min-width: 620px) { .member-grid { grid-template-columns: repeat(2, 1fr); gap: 16px; } }
    @media (min-width: 1040px) { .member-grid { grid-template-columns: repeat(3, 1fr); } }
    .member-card { display: flex; gap: 14px; align-items: flex-start; padding: 16px; }
    .member-ava {
      flex: none; width: 60px; height: 60px; border-radius: 50%;
      display: grid; place-items: center; color: #fff; font-weight: 800; font-size: 1.25rem;
      line-height: 1; overflow: hidden; box-shadow: 0 4px 12px -4px rgba(20,23,41,.35);
    }
    .member-body { min-width: 0; flex: 1; }
    .member-name { font-family: var(--font-display); font-weight: 700; font-size: 1.1rem; line-height: 1.2; margin: 0 0 3px; letter-spacing: -.01em; }
    .member-loc { font-size: .76rem; font-weight: 800; text-transform: uppercase; letter-spacing: .04em; color: var(--ink-3); margin: 0 0 8px; }
    .member-bio {
      font-size: .9rem; color: var(--ink-2); line-height: 1.45; margin: 0 0 12px;
      display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden;
    }
    .member-tags { display: flex; flex-wrap: wrap; gap: 6px; }
  </style>
  <main id="main">
    <section class="hero" style="padding-bottom:0">
      <div class="hero-deco" aria-hidden="true"><span class="blob blob-1"></span><span class="blob blob-2"></span></div>
      <div class="container">
        <p class="hero-kicker"><span class="pulse" aria-hidden="true"></span>Toulouse · la communauté</p>
        <h1 class="hero-title" style="font-size:clamp(1.8rem,6vw,2.8rem);max-width:none;margin-bottom:14px">
          Rencontre <span class="text-grad">ta future team</span>.
        </h1>
      </div>
    </section>

    <section class="section results-section" aria-labelledby="results-title">
      <div class="container">
        <div class="results-head">
          <div>
            <h2 id="results-title">Les membres</h2>
            <p class="results-sub">
              <?php if ($count > 0): ?>
                <span class="count"><?= $count ?></span><span class="sub-rest">membre<?= $count > 1 ? 's' : '' ?> actif<?= $count > 1 ? 's' : '' ?></span>
              <?php else: ?>
                <span class="sub-rest">Les profils arrivent bientôt.</span>
              <?php endif; ?>
            </p>
          </div>
        </div>

        <?php if (!$dbOk): ?>
          <p class="notice">Impossible de charger les membres pour le moment. Réessaie dans un instant.</p>
        <?php endif; ?>

        <?php if ($count > 0): ?>
          <div class="member-grid" id="results">
            <?php foreach ($members as $m):
                $name = (string) ($m['display_name'] ?: $m['name']);
                $color = ttt_avatar_color($name);
                $age = (int) ($m['age'] ?? 0);
                $hood = trim((string) ($m['neighborhood'] ?? ''));
                $city = trim((string) ($m['city'] ?? 'Toulouse'));
                $where = $hood !== '' ? "{$hood} · {$city}" : $city;
                $rep = (int) ($m['reputation_score'] ?? 0);
                $attended = (int) ($m['attended_count'] ?? 0);
            ?>
              <article class="member-card card">
                <span class="member-ava" style="background:<?= ttt_e($color) ?>"><?= ttt_e(ttt_initials($name)) ?></span>
                <div class="member-body">
                  <p class="member-name"><?= ttt_e($name) ?><?= $age > 0 ? ', ' . $age . ' ans' : '' ?></p>
                  <p class="member-loc">📍 <?= $where !== '' ? ttt_e($where) : 'Toulouse' ?></p>
                  <p class="member-bio"><?= ttt_e((string) $m['bio']) ?></p>
                  <div class="member-tags">
                    <span class="badge badge-ok">✓ Vérifié</span>
                    <?php if ($rep > 0): ?><span class="badge badge-brand">⭐ <?= $rep ?></span><?php endif; ?>
                    <?php if ($attended > 0): ?><span class="badge"><?= $attended ?> sortie<?= $attended > 1 ? 's' : '' ?></span><?php endif; ?>
                  </div>
                </div>
              </article>
            <?php endforeach; ?>
          </div>
        <?php elseif ($dbOk): ?>
          <div class="empty card" id="empty">
            <div class="empty-big" aria-hidden="true">👥</div>
            <p>Aucun membre à afficher pour l'instant. La communauté se construit.</p>
            <div class="empty-actions"><a class="btn btn-primary" href="/sorties">Voir les sorties</a></div>
          </div>
        <?php endif; ?>
      </div>
    </section>

    <section class="container">
      <a class="organize card" href="/sorties">
        <span class="organize-art" aria-hidden="true"><span>🏓</span><span>🍻</span><span>🥾</span></span>
        <span class="organize-txt"><strong>Envie de les rencontrer ?</strong> Rejoins une sortie près de chez toi et fais connaissance en vrai.</span>
        <span class="organize-cta"><span class="organize-cta-txt">Voir les sorties</span><span class="organize-arrow" aria-hidden="true">→</span></span>
      </a>
    </section>
  </main>
<?php ttt_footer(); ?>
