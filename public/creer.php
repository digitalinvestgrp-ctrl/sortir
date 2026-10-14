<?php
declare(strict_types=1);
/**
 * creer.php — Creer une sortie : /creer (reserve aux membres connectes).
 *
 * PROD 2026-10 — dig-holding : Mehdi Benali (archi) / Clara Dupont (back) / Lukas Brennan (front).
 * POST : valide + insere dans `events` (reliee MySQL), auto-inscrit l'organisateur, redirige vers le detail.
 */

require_once __DIR__ . '/_ttt_events.php';
require_once __DIR__ . '/_ttt_layout.php';

$user = ttt_require_login('/creer');
$errors = [];
$old = ['title' => '', 'category' => '', 'neighborhood_id' => '', 'starts_at' => '', 'capacity' => '', 'description' => '', 'visibility' => 'public'];

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    ttt_check_csrf();
    foreach ($old as $k => $_) {
        $old[$k] = trim((string) ($_POST[$k] ?? ''));
    }
    [$clean, $errors] = ttt_validate_event($_POST, false);
    if (!$errors) {
        try {
            $eventId = ttt_event_create((int) $user['id'], $clean);
            ttt_flash_set('ok', 'Ta sortie est en ligne 🎉 Partage le lien a tes potes !');
            ttt_redirect('/sortie/' . $eventId . '?created=1');
        } catch (\Throwable $e) {
            error_log('[creer] ' . $e->getMessage());
            $errors['_'] = 'Impossible de creer la sortie pour le moment. Reessaie.';
        }
    }
}

$neighborhoods = ttt_neighborhoods();

ttt_head('Creer une sortie | TrouveTaTeam', 'Organise ta sortie sport ou loisirs a Toulouse et invite la communaute.', 'creer');
?>
  <main id="main">
    <section class="section" style="max-width:620px;margin:0 auto">
      <h1 style="font-size:1.7rem;margin:4px 0 4px">Créer une sortie</h1>
      <p style="color:var(--ink-2);margin:0 0 20px">Tu seras automatiquement inscrit comme organisateur.</p>

      <?php if (isset($errors['_'])): ?>
        <div class="ttt-flash ttt-flash-error" style="margin-bottom:16px"><?= ttt_e($errors['_']) ?></div>
      <?php endif; ?>

      <form method="post" action="/creer" class="card card-pad" novalidate>
        <?= ttt_csrf_field() ?>
        <label class="field">
          <span>Titre de la sortie</span>
          <input class="input" type="text" name="title" maxlength="70" required value="<?= ttt_e($old['title']) ?>" placeholder="Apéro au Capitole, Running canal du Midi…">
          <?php if (isset($errors['title'])): ?><small style="color:#b4231f"><?= ttt_e($errors['title']) ?></small><?php endif; ?>
        </label>

        <label class="field">
          <span>Activité</span>
          <select class="input" name="category" required>
            <option value="">Choisis une activité…</option>
            <?php foreach (ttt_themes_grouped() as $famille => $slugs): ?>
              <optgroup label="<?= ttt_e($famille) ?>">
                <?php foreach ($slugs as $slug => $meta): ?>
                  <option value="<?= ttt_e($slug) ?>" <?= $old['category'] === $slug ? 'selected' : '' ?>><?= $meta[0] . ' ' . ttt_e($meta[2]) ?></option>
                <?php endforeach; ?>
              </optgroup>
            <?php endforeach; ?>
          </select>
          <?php if (isset($errors['category'])): ?><small style="color:#b4231f"><?= ttt_e($errors['category']) ?></small><?php endif; ?>
        </label>

        <label class="field">
          <span>Quartier (Toulouse)</span>
          <select class="input" name="neighborhood_id">
            <option value="">Non précisé</option>
            <?php foreach ($neighborhoods as $n): ?>
              <option value="<?= (int) $n['id'] ?>" <?= $old['neighborhood_id'] === (string) $n['id'] ? 'selected' : '' ?>><?= ttt_e($n['name']) ?></option>
            <?php endforeach; ?>
          </select>
          <?php if (isset($errors['neighborhood_id'])): ?><small style="color:#b4231f"><?= ttt_e($errors['neighborhood_id']) ?></small><?php endif; ?>
        </label>

        <label class="field">
          <span>Date et heure</span>
          <input class="input" type="datetime-local" name="starts_at" required value="<?= ttt_e($old['starts_at']) ?>">
          <?php if (isset($errors['starts_at'])): ?><small style="color:#b4231f"><?= ttt_e($errors['starts_at']) ?></small><?php endif; ?>
        </label>

        <label class="field">
          <span>Nombre de places <span style="font-weight:500;color:var(--ink-2)">(vide = illimité)</span></span>
          <input class="input" type="number" name="capacity" min="2" max="500" value="<?= ttt_e($old['capacity']) ?>" placeholder="Ex : 10">
          <?php if (isset($errors['capacity'])): ?><small style="color:#b4231f"><?= ttt_e($errors['capacity']) ?></small><?php endif; ?>
        </label>

        <label class="field">
          <span>Description <span style="font-weight:500;color:var(--ink-2)">(optionnel)</span></span>
          <textarea class="input" name="description" rows="4" maxlength="600" placeholder="Donne envie : lieu de rdv, niveau, ambiance…"><?= ttt_e($old['description']) ?></textarea>
          <?php if (isset($errors['description'])): ?><small style="color:#b4231f"><?= ttt_e($errors['description']) ?></small><?php endif; ?>
        </label>

        <div class="field">
          <span>Visibilité</span>
          <label class="check" style="margin-bottom:8px">
            <input type="radio" name="visibility" value="public" <?= $old['visibility'] !== 'private' ? 'checked' : '' ?>>
            <span><strong>Publique</strong> — visible par tous, apparaît dans la liste des sorties</span>
          </label>
          <label class="check">
            <input type="radio" name="visibility" value="private" <?= $old['visibility'] === 'private' ? 'checked' : '' ?>>
            <span><strong>Privée</strong> — accessible seulement via le lien d'invitation que tu partages</span>
          </label>
        </div>

        <button type="submit" class="btn btn-primary btn-lg btn-block">Publier ma sortie</button>
      </form>
    </section>
  </main>
<?php ttt_footer(); ?>
