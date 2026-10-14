<?php
declare(strict_types=1);
/**
 * sortie.php — Detail d'une sortie : /sortie/{id}
 *
 * PROD 2026-10 — dig-holding : Mehdi Benali (archi) / Clara Dupont (back) / Lukas Brennan (front).
 *
 * GET  : fiche complete (lieu, date, hote, description, participants, places restantes).
 * POST : action = join | leave (inscription / desinscription, protege CSRF).
 *        L'organisateur voit "Modifier" / "Annuler". Lui seul peut agir sur SA sortie.
 */

require_once __DIR__ . '/_ttt_events.php';
require_once __DIR__ . '/_ttt_layout.php';

$id = (int) ($_GET['id'] ?? 0);
if ($id <= 0) {
    http_response_code(404);
}

$user = ttt_current_user();
$viewerId = $user ? (int) $user['id'] : null;

// --- POST : inscription / desinscription ---
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    ttt_check_csrf();
    if (!$user) {
        ttt_redirect('/connexion?next=' . rawurlencode('/sortie/' . $id));
    }
    $action = (string) ($_POST['action'] ?? '');
    if ($action === 'join') {
        $r = ttt_rsvp_join($id, $viewerId);
        $map = [
            'joined' => ['ok', 'Tu es inscrit a cette sortie 🎉'],
            'already' => ['info', 'Tu etais deja inscrit.'],
            'full' => ['error', 'Cette sortie est complete.'],
            'cancelled' => ['error', 'Cette sortie a ete annulee.'],
            'error' => ['error', 'Inscription impossible. Reessaie.'],
        ];
        [$t, $msg] = $map[$r['reason']] ?? ['error', 'Erreur.'];
        ttt_flash_set($t, $msg);
    } elseif ($action === 'leave') {
        $r = ttt_rsvp_leave($id, $viewerId);
        $map = [
            'left' => ['ok', 'Tu t es desinscrit de cette sortie.'],
            'host' => ['error', 'Tu organises cette sortie : annule-la plutot que de te desinscrire.'],
            'error' => ['error', 'Operation impossible. Reessaie.'],
        ];
        [$t, $msg] = $map[$r['reason']] ?? ['error', 'Erreur.'];
        ttt_flash_set($t, $msg);
    }
    ttt_redirect('/sortie/' . $id);
}

$ev = ttt_event_full($id, $viewerId);

if (!$ev) {
    ttt_head('Sortie introuvable | TrouveTaTeam', 'Cette sortie n existe pas ou plus.', 'sorties');
    echo '<main id="main"><section class="section"><div class="container">';
    echo '<div class="empty card"><div class="empty-big" aria-hidden="true">🔍</div>';
    echo '<p>Cette sortie n\'existe pas ou a ete retiree.</p>';
    echo '<div class="empty-actions"><a class="btn btn-primary" href="/sorties">Voir les sorties</a></div></div>';
    echo '</div></section></main>';
    ttt_footer();
    return;
}

[$emoji, $color, $label] = ttt_category($ev['category'] ?? null);
$isHost = $user && (int) $ev['host_user_id'] === $viewerId;
$isPrivate = (($ev['visibility'] ?? 'public') === 'private');
$inviteCode = (string) ($ev['invite_code'] ?? '');
if ($isPrivate) {
    $providedCode = (string) ($_GET['code'] ?? '');
    $allowedView = $isHost || !empty($ev['viewer_going']) || ($inviteCode !== '' && hash_equals($inviteCode, $providedCode));
    if (!$allowedView) {
        ttt_head('Sortie privée | TrouveTaTeam', 'Cette sortie est privée.', 'sorties');
        echo '<main id="main"><section class="section"><div class="container" style="max-width:600px">'
           . '<div class="empty card"><div class="empty-big" aria-hidden="true">🔒</div>'
           . '<p>Cette sortie est <strong>privée</strong>. Demande le lien d\'invitation à l\'organisateur pour y accéder.</p>'
           . '<div class="empty-actions"><a class="btn btn-primary" href="/sorties">Voir les sorties publiques</a></div></div>'
           . '</div></section></main>';
        ttt_footer();
        return;
    }
}
$isCancelled = ($ev['status'] ?? '') === 'cancelled';
$isPast = strtotime((string) $ev['starts_at']) < time();
$going = (int) $ev['going_count'];
$cap = $ev['capacity'] !== null ? (int) $ev['capacity'] : 0;
$left = $cap > 0 ? max(0, $cap - $going) : null;
$isFull = $cap > 0 && $left === 0;
$place = trim((string) ($ev['neighborhood'] ?? ''));
$city = trim((string) ($ev['city'] ?? 'Toulouse'));
$where = $place !== '' ? "{$place} · {$city}" : $city;
$participants = ttt_event_participants($id);
$shareUrl = ttt_canonical_base() . '/sortie/' . $id . ($isPrivate && $inviteCode !== '' ? '?code=' . $inviteCode : '');
$justCreated = isset($_GET['created']);
$ogImg = ttt_theme_og($ev['category'] ?? null);
$ogDesc = $label . ' · ' . ttt_date_fr((string) $ev['starts_at']) . ' · ' . $where;
$waText = rawurlencode('Rejoins-moi : ' . $ev['title'] . ' — ' . $ogDesc . "\n" . $shareUrl);

ttt_head(
    $ev['title'] . ' | TrouveTaTeam',
    $ogDesc,
    'sorties',
    'sortie',
    $ogImg
);
?>
  <main id="main">
    <section class="section">
      <div class="container" style="max-width:720px">
        <p style="margin:0 0 14px"><a class="btn btn-ghost btn-sm" href="/sorties">← Toutes les sorties</a></p>

        <?php if ($isCancelled): ?>
          <div class="ttt-flash ttt-flash-error" style="margin-bottom:16px">Cette sortie a ete <strong>annulee</strong> par l'organisateur.</div>
        <?php elseif ($isPast): ?>
          <div class="ttt-flash ttt-flash-info" style="margin-bottom:16px">Cette sortie est deja passee.</div>
        <?php endif; ?>

        <article class="card card-pad" style="--tc:<?= ttt_e($color) ?>;border-left:5px solid <?= ttt_e($color) ?>">
          <div style="display:flex;gap:16px;align-items:flex-start">
            <div class="emoji" aria-hidden="true" style="font-size:2.4rem;flex:none"><?= $emoji ?></div>
            <div style="flex:1;min-width:0">
              <div class="tags" style="margin-bottom:8px">
                <span class="badge badge-brand"><?= ttt_e($label) ?></span>
                <?php if ($isCancelled): ?><span class="badge badge-dark">Annulee</span><?php endif; ?>
                <?php if ($isFull && !$isCancelled): ?><span class="badge badge-dark">Complet</span><?php endif; ?>
              </div>
              <h1 style="margin:0 0 6px;font-size:1.7rem;line-height:1.15"><?= ttt_e($ev['title']) ?></h1>
              <p style="margin:0;color:var(--ink-2);font-weight:600"><?= ttt_e(ttt_date_fr((string) $ev['starts_at'])) ?></p>
            </div>
          </div>

          <div style="margin-top:18px;display:grid;gap:10px;color:var(--ink);font-weight:600">
            <div>📍 <?= ttt_e($where) ?></div>
            <div>👤 Organise par <?= ttt_e($ev['host_name'] ?? 'un membre') ?></div>
            <div>👥 <?= $going ?> inscrit<?= $going > 1 ? 's' : '' ?><?php if ($cap > 0): ?> · <?= $left ?> place<?= $left > 1 ? 's' : '' ?> restante<?= $left > 1 ? 's' : '' ?> (max <?= $cap ?>)<?php else: ?> · places illimitees<?php endif; ?></div>
          </div>

          <?php if (!empty($ev['description'])): ?>
            <div style="margin-top:18px;line-height:1.6;white-space:pre-line;color:var(--ink)"><?= ttt_e($ev['description']) ?></div>
          <?php endif; ?>

          <div style="margin-top:22px;display:flex;flex-wrap:wrap;gap:10px">
            <?php if ($isHost): ?>
              <span class="badge badge-ok" style="align-self:center">★ Tu organises cette sortie</span>
              <?php if (!$isCancelled): ?>
                <a class="btn" href="/sortie/<?= $id ?>/modifier">Modifier</a>
                <form method="post" action="/sortie/<?= $id ?>/modifier" onsubmit="return confirm('Annuler cette sortie ? Les inscrits seront prevenus.');" style="margin:0">
                  <?= ttt_csrf_field() ?>
                  <input type="hidden" name="action" value="cancel">
                  <button type="submit" class="btn btn-ghost" style="color:#b4231f">Annuler la sortie</button>
                </form>
              <?php endif; ?>
            <?php elseif ($isCancelled || $isPast): ?>
              <span style="color:var(--ink-2)">Les inscriptions sont fermees.</span>
            <?php elseif (!$user): ?>
              <a class="btn btn-primary btn-lg" href="/connexion?next=<?= rawurlencode('/sortie/' . $id) ?>">Connecte-toi pour participer</a>
            <?php elseif ($ev['viewer_going']): ?>
              <span class="badge badge-ok" style="align-self:center">✓ Tu participes</span>
              <form method="post" action="/sortie/<?= $id ?>" style="margin:0">
                <?= ttt_csrf_field() ?>
                <input type="hidden" name="action" value="leave">
                <button type="submit" class="btn btn-ghost">Me desinscrire</button>
              </form>
            <?php elseif ($isFull): ?>
              <button class="btn btn-lg" disabled>Complet</button>
            <?php else: ?>
              <form method="post" action="/sortie/<?= $id ?>" style="margin:0">
                <?= ttt_csrf_field() ?>
                <input type="hidden" name="action" value="join">
                <button type="submit" class="btn btn-primary btn-lg">Je participe</button>
              </form>
            <?php endif; ?>
          </div>
        </article>

        <?php if (!$isCancelled): ?>
        <section class="card card-pad ttt-share" style="margin-top:18px" aria-label="Partager la sortie">
          <?php if ($justCreated): ?>
            <p style="font-weight:800;margin:0 0 6px;color:var(--brand);font-size:1.05rem">🎉 Ta sortie est en ligne !</p>
          <?php endif; ?>
          <p style="font-weight:800;margin:0 0 10px;font-size:1.1rem">Invite tes amis</p>
          <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:center">
            <input class="input" id="shareLink" readonly value="<?= ttt_e($shareUrl) ?>" style="flex:1;min-width:220px" onfocus="this.select()">
            <button type="button" class="btn" id="copyBtn" data-link="<?= ttt_e($shareUrl) ?>" onclick="tttCopy(this)">Copier le lien</button>
            <a class="btn btn-ok" target="_blank" rel="noopener" href="https://wa.me/?text=<?= $waText ?>">
              <span aria-hidden="true">🟢</span> Partager sur WhatsApp
            </a>
          </div>
          <?php if ($isPrivate): ?>
            <p style="color:var(--ink-2);font-size:.85rem;margin:10px 0 0">Sortie privée : ce lien contient le code d'invitation, ne le partage qu'aux personnes conviées.</p>
          <?php endif; ?>
        </section>
        <?php endif; ?>

        <?php if ($participants): ?>
          <h2 style="font-size:1.1rem;margin:26px 0 12px">Qui vient ? (<?= count($participants) ?>)</h2>
          <div style="display:flex;flex-wrap:wrap;gap:10px">
            <?php foreach ($participants as $p):
                $pname = (string) $p['name'];
                $pfirst = explode(' ', trim($pname))[0];
            ?>
              <span style="display:inline-flex;align-items:center;gap:8px;background:var(--surface);border:1px solid var(--line);border-radius:999px;padding:5px 14px 5px 5px;font-weight:600">
                <span style="width:28px;height:28px;border-radius:50%;display:grid;place-items:center;color:#fff;font-size:.75rem;font-weight:800;background:<?= ttt_e(ttt_avatar_color($pname)) ?>"><?= ttt_e(ttt_initials($pname)) ?></span>
                <?= ttt_e($pfirst) ?>
              </span>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
    </section>
  </main>
  <script>
  function tttCopy(b){
    var link=b.getAttribute('data-link');
    function done(){ var old=b.textContent; b.textContent='Lien copié ✓'; setTimeout(function(){ b.textContent=old.indexOf('copié')>-1?'Copier le lien':old; b.textContent='Copier le lien'; },2000); }
    function fallback(){ var i=document.getElementById('shareLink'); if(i){ i.focus(); i.select(); try{ document.execCommand('copy'); done(); }catch(e){} } }
    if(navigator.clipboard&&navigator.clipboard.writeText){ navigator.clipboard.writeText(link).then(done,fallback); } else { fallback(); }
  }
  </script>
<?php ttt_footer(); ?>
