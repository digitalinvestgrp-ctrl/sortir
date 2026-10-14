<?php
declare(strict_types=1);
/**
 * modifier.php — Modifier / annuler SA sortie : /sortie/{id}/modifier
 *
 * PROD 2026-10 — dig-holding : Mehdi Benali (archi) / Clara Dupont (back) / Lukas Brennan (front).
 * Reserve a l'organisateur. POST action=update (modifie) | cancel (annule, status=cancelled).
 */

require_once __DIR__ . '/_ttt_events.php';
require_once __DIR__ . '/_ttt_layout.php';

$id = (int) ($_GET['id'] ?? 0);
$user = ttt_require_login('/sortie/' . $id . '/modifier');

$ev = ttt_event_full($id, (int) $user['id']);
if (!$ev) {
    http_response_code(404);
    ttt_head('Sortie introuvable | TrouveTaTeam', 'Introuvable.', 'sorties');
    echo '<main id="main"><section class="section"><div class="container"><div class="empty card"><p>Sortie introuvable.</p><div class="empty-actions"><a class="btn btn-primary" href="/compte">Mon compte</a></div></div></div></section></main>';
    ttt_footer();
    return;
}

// Seul l'organisateur
if ((int) $ev['host_user_id'] !== (int) $user['id']) {
    http_response_code(403);
    ttt_head('Acces refuse | TrouveTaTeam', 'Acces refuse.', 'sorties');
    echo '<main id="main"><section class="section"><div class="container"><div class="empty card"><div class="empty-big">🔒</div><p>Seul l\'organisateur peut modifier cette sortie.</p><div class="empty-actions"><a class="btn btn-primary" href="/sortie/' . $id . '">Voir la sortie</a></div></div></div></section></main>';
    ttt_footer();
    return;
}

$errors = [];

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    ttt_check_csrf();
    $action = (string) ($_POST['action'] ?? 'update');

    if ($action === 'cancel') {
        try {
            ttt_event_cancel($id);
            ttt_flash_set('ok', 'Sortie annulee.');
            ttt_redirect('/sortie/' . $id);
        } catch (\Throwable $e) {
            error_log('[modifier] cancel: ' . $e->getMessage());
            $errors['_'] = 'Annulation impossible. Reessaie.';
        }
    } else {
        [$clean, $errors] = ttt_validate_event($_POST, false);
        if (!$errors) {
            try {
                ttt_event_update($id, $clean);
                ttt_flash_set('ok', 'Sortie mise a jour.');
                ttt_redirect('/sortie/' . $id);
            } catch (\Throwable $e) {
                error_log('[modifier] update: ' . $e->getMessage());
                $errors['_'] = 'Mise a jour impossible. Reessaie.';
            }
        }
        // En cas d'erreur on re-affiche avec les valeurs postees
        $ev = array_merge($ev, [
            'title' => $_POST['title'] ?? $ev['title'],
            'category' => $_POST['category'] ?? $ev['category'],
            'neighborhood_id' => $_POST['neighborhood_id'] ?? $ev['neighborhood_id'],
            'capacity' => $_POST['capacity'] ?? $ev['capacity'],
            'description' => $_POST['description'] ?? $ev['description'],
        ]);
    }
}

$neighborhoods = ttt_neighborhoods();
$startLocal = date('Y-m-d\TH:i', strtotime((string) $ev['starts_at']));
if (!empty($_POST['starts_at'])) {
    $startLocal = ttt_e((string) $_POST['starts_at']);
}

ttt_head('Modifier ma sortie | TrouveTaTeam', 'Modifie ou annule ta sortie.', 'sorties');
?>
  <main id="main">
    <section class="section" style="max-width:620px;margin:0 auto">
      <p style="margin:0 0 10px"><a class="btn btn-ghost btn-sm" href="/sortie/<?= $id ?>">← Retour a la sortie</a></p>
      <h1 style="font-size:1.6rem;margin:0 0 16px">Modifier la sortie</h1>

      <?php if (isset($errors['_'])): ?><div class="ttt-flash ttt-flash-error" style="margin-bottom:16px"><?= ttt_e($errors['_']) ?></div><?php endif; ?>

      <form method="post" action="/sortie/<?= $id ?>/modifier" class="card card-pad" novalidate>
        <?= ttt_csrf_field() ?>
        <input type="hidden" name="action" value="update">
        <label class="field">
          <span>Titre de la sortie</span>
          <input class="input" type="text" name="title" maxlength="70" required value="<?= ttt_e($ev['title']) ?>">
          <?php if (isset($errors['title'])): ?><small style="color:#b4231f"><?= ttt_e($errors['title']) ?></small><?php endif; ?>
        </label>
        <label class="field">
          <span>Activité</span>
          <select class="input" name="category" required>
            <?php foreach (ttt_themes_grouped() as $famille => $slugs): ?>
              <optgroup label="<?= ttt_e($famille) ?>">
                <?php foreach ($slugs as $slug => $meta): ?>
                  <option value="<?= ttt_e($slug) ?>" <?= ttt_theme_alias((string) $ev['category']) === $slug ? 'selected' : '' ?>><?= $meta[0] . ' ' . ttt_e($meta[2]) ?></option>
                <?php endforeach; ?>
              </optgroup>
            <?php endforeach; ?>
          </select>
          <?php if (isset($errors['category'])): ?><small style="color:#b4231f"><?= ttt_e($errors['category']) ?></small><?php endif; ?>
        </label>
        <div class="field">
          <span>Visibilité</span>
          <?php $evVis = ($ev['visibility'] ?? 'public') === 'private' ? 'private' : 'public'; ?>
          <label class="check" style="margin-bottom:8px">
            <input type="radio" name="visibility" value="public" <?= $evVis !== 'private' ? 'checked' : '' ?>>
            <span><strong>Publique</strong> — visible dans la liste</span>
          </label>
          <label class="check">
            <input type="radio" name="visibility" value="private" <?= $evVis === 'private' ? 'checked' : '' ?>>
            <span><strong>Privée</strong> — accessible via lien d'invitation</span>
          </label>
        </div>
        <label class="field">
          <span>Quartier (Toulouse)</span>
          <select class="input" name="neighborhood_id">
            <option value="">Non précisé</option>
            <?php foreach ($neighborhoods as $n): ?>
              <option value="<?= (int) $n['id'] ?>" <?= (string) $ev['neighborhood_id'] === (string) $n['id'] ? 'selected' : '' ?>><?= ttt_e($n['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </label>
        <label class="field">
          <span>Date et heure</span>
          <input class="input" type="datetime-local" name="starts_at" required value="<?= $startLocal ?>">
          <?php if (isset($errors['starts_at'])): ?><small style="color:#b4231f"><?= ttt_e($errors['starts_at']) ?></small><?php endif; ?>
        </label>
        <label class="field">
          <span>Nombre de places <span style="font-weight:500;color:var(--ink-2)">(vide = illimité)</span></span>
          <input class="input" type="number" name="capacity" min="2" max="500" value="<?= $ev['capacity'] !== null ? (int) $ev['capacity'] : '' ?>">
          <?php if (isset($errors['capacity'])): ?><small style="color:#b4231f"><?= ttt_e($errors['capacity']) ?></small><?php endif; ?>
        </label>
        <label class="field">
          <span>Description</span>
          <textarea class="input" name="description" rows="4" maxlength="600"><?= ttt_e($ev['description'] ?? '') ?></textarea>
          <?php if (isset($errors['description'])): ?><small style="color:#b4231f"><?= ttt_e($errors['description']) ?></small><?php endif; ?>
        </label>
        <button type="submit" class="btn btn-primary btn-lg btn-block">Enregistrer les modifications</button>
      </form>

      <?php if (($ev['status'] ?? '') !== 'cancelled'): ?>
        <form method="post" action="/sortie/<?= $id ?>/modifier" style="margin-top:14px" onsubmit="return confirm('Annuler definitivement cette sortie ?');">
          <?= ttt_csrf_field() ?>
          <input type="hidden" name="action" value="cancel">
          <button type="submit" class="btn btn-ghost btn-block" style="color:#b4231f">Annuler cette sortie</button>
        </form>
      <?php else: ?>
        <p style="text-align:center;color:var(--ink-2);margin-top:14px">Cette sortie est annulee.</p>
      <?php endif; ?>
    </section>
  </main>
<?php ttt_footer(); ?>
