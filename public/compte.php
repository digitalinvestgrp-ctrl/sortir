<?php
declare(strict_types=1);
/**
 * compte.php — Espace membre : /compte (mon compte + mes sorties).
 *
 * PROD 2026-10 — dig-holding : Mehdi Benali (archi) / Clara Dupont (back) / Lukas Brennan (front).
 * Affiche les sorties que j'organise et celles ou je suis inscrit (relie MySQL : events + rsvps).
 */

require_once __DIR__ . '/_ttt_events.php';
require_once __DIR__ . '/_ttt_layout.php';

$user = ttt_require_login('/compte');
$uid = (int) $user['id'];

$hosted = ttt_my_hosted($uid);
$joined = ttt_my_joined($uid);
$name = (string) ($user['name'] ?? '');
$contact = (string) ($user['email'] ?? '');
if ($contact === '' || substr($contact, -24) === '@phone.trouvetateam.local') {
    $contact = (string) ($user['phone'] ?? '');
}

/** Petit rendu de carte sortie + badge de statut + lien modifier (pour l'organisateur). */
function ttt_account_card(array $ev, bool $owner): string
{
    $status = (string) ($ev['status'] ?? 'published');
    $past = strtotime((string) $ev['starts_at']) < time();
    $extra = '';
    if ($status === 'cancelled') {
        $extra = '<span class="badge badge-dark" style="margin-left:6px">Annulee</span>';
    } elseif ($past) {
        $extra = '<span class="badge" style="margin-left:6px">Passee</span>';
    }
    $html = '<div style="position:relative">' . ttt_sortie_card($ev);
    if ($extra !== '' || $owner) {
        $html .= '<div style="margin:-6px 0 14px;padding-left:4px;display:flex;gap:10px;align-items:center">' . $extra;
        if ($owner && $status !== 'cancelled') {
            $html .= '<a class="btn btn-sm" href="/sortie/' . (int) $ev['id'] . '/modifier">Modifier / annuler</a>';
        }
        $html .= '</div>';
    }
    $html .= '</div>';
    return $html;
}

ttt_head('Mon compte | TrouveTaTeam', 'Ton espace membre TrouveTaTeam : tes sorties organisees et tes inscriptions.', 'compte');
?>
  <main id="main">
    <section class="section" style="max-width:860px;margin:0 auto">
      <div class="card card-pad" style="display:flex;align-items:center;gap:16px;margin-bottom:8px">
        <span style="width:58px;height:58px;border-radius:50%;display:grid;place-items:center;color:#fff;font-size:1.3rem;font-weight:800;flex:none;background:<?= ttt_e(ttt_avatar_color($name)) ?>"><?= ttt_e(ttt_initials($name)) ?></span>
        <div style="flex:1;min-width:0">
          <h1 style="margin:0;font-size:1.5rem"><?= ttt_e($name) ?></h1>
          <p style="margin:2px 0 0;color:var(--ink-2)"><?= ttt_e($contact) ?></p>
        </div>
        <a class="btn btn-primary" href="/creer">+ Créer une sortie</a>
      </div>

      <h2 style="font-size:1.2rem;margin:28px 0 14px">Les sorties que j'organise (<?= count($hosted) ?>)</h2>
      <?php if ($hosted): ?>
        <div class="sortie-grid">
          <?php foreach ($hosted as $ev) {
              echo ttt_account_card($ev, true);
          } ?>
        </div>
      <?php else: ?>
        <div class="empty card"><div class="empty-big" aria-hidden="true">📅</div>
          <p>Tu n'organises aucune sortie pour l'instant.</p>
          <div class="empty-actions"><a class="btn btn-primary" href="/creer">Créer ma première sortie</a></div>
        </div>
      <?php endif; ?>

      <h2 style="font-size:1.2rem;margin:34px 0 14px">Les sorties où je suis inscrit (<?= count($joined) ?>)</h2>
      <?php if ($joined): ?>
        <div class="sortie-grid">
          <?php foreach ($joined as $ev) {
              echo ttt_account_card($ev, false);
          } ?>
        </div>
      <?php else: ?>
        <div class="empty card"><div class="empty-big" aria-hidden="true">🔎</div>
          <p>Tu n'es inscrit à aucune sortie.</p>
          <div class="empty-actions"><a class="btn btn-primary" href="/sorties">Explorer les sorties</a></div>
        </div>
      <?php endif; ?>

      <p style="margin-top:30px;text-align:center">
        <form method="post" action="/deconnexion" style="display:inline">
          <?= ttt_csrf_field() ?>
          <button type="submit" class="btn btn-ghost">Se déconnecter</button>
        </form>
      </p>
    </section>
  </main>
<?php ttt_footer(); ?>
