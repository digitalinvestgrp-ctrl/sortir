<?php
declare(strict_types=1);
/**
 * connexion.php — Inscription express + connexion par lien magique (sans mot de passe).
 *
 * PROD 2026-10 — dig-holding : Mehdi Benali (archi) / Clara Dupont (back) / Lukas Brennan (front).
 *
 * GET  : formulaire (prenom + email/telephone).
 * POST : cree ou retrouve l'utilisateur, emet un lien magique (api_tokens name='magic'),
 *        tente l'envoi email + journalise dans l'outbox, puis affiche l'ecran "verifie ta boite".
 *
 * Securite : le lien magique n'est JAMAIS affiche en clair ici (anti prise de compte).
 * Sans SMTP, il se recupere via /dev/mails?key=... (cle secrete du .env).
 */

require_once __DIR__ . '/_ttt_auth.php';
require_once __DIR__ . '/_ttt_layout.php';

// Deja connecte -> compte
if (ttt_is_logged_in()) {
    ttt_redirect('/compte');
}

$next = ttt_safe_next($_GET['next'] ?? ($_POST['next'] ?? '/compte'));
$errors = [];
$sent = false;
$sentTo = '';
$old = ['name' => '', 'contact' => ''];

/** Normalise un contact : ['email'=>..] | ['phone'=>..] | null */
function ttt_parse_contact(string $raw): ?array
{
    $s = trim($raw);
    if ($s === '') {
        return null;
    }
    if (strpos($s, '@') !== false) {
        $email = strtolower($s);
        return (strlen($email) <= 254 && filter_var($email, FILTER_VALIDATE_EMAIL)) ? ['email' => $email] : null;
    }
    $p = preg_replace('/[\s.\-()\/]/', '', $s);
    if (preg_match('/^00\d+$/', $p)) {
        $p = '+' . substr($p, 2);
    }
    if (preg_match('/^0[1-9]\d{8}$/', $p)) {
        $p = '+33' . substr($p, 1);
    }
    return preg_match('/^\+[1-9]\d{7,14}$/', $p) ? ['phone' => $p] : null;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    ttt_check_csrf();
    $old['name'] = trim((string) ($_POST['name'] ?? ''));
    $old['contact'] = trim((string) ($_POST['contact'] ?? ''));

    $contact = ttt_parse_contact($old['contact']);
    if (!$contact) {
        $errors['contact'] = 'Indique un email ou un numero de telephone valide.';
    }

    if (!$errors) {
        try {
            $pdo = ttt_pdo();
            // Retrouver un compte existant par email ou telephone
            $existing = null;
            if (isset($contact['email'])) {
                $q = $pdo->prepare('SELECT * FROM users WHERE email = ? LIMIT 1');
                $q->execute([$contact['email']]);
                $existing = $q->fetch() ?: null;
            } else {
                $q = $pdo->prepare('SELECT * FROM users WHERE phone = ? LIMIT 1');
                $q->execute([$contact['phone']]);
                $existing = $q->fetch() ?: null;
            }

            if ($existing) {
                if ((int) ($existing['is_blocked'] ?? 0) === 1) {
                    $errors['contact'] = 'Ce compte est bloque.';
                } else {
                    [$plain, $url] = ttt_issue_magic((int) $existing['id'], $next);
                    ttt_send_magic_mail($existing, $url, 'login');
                    $sent = true;
                    $sentTo = (string) ($existing['email'] ?: $existing['phone']);
                }
            } else {
                // Creation : prenom requis
                $name = $old['name'];
                $name = preg_replace('/\s+/', ' ', $name);
                if (mb_strlen($name) < 1 || mb_strlen($name) > 40) {
                    $errors['name'] = 'Indique ton prenom (40 caracteres maximum).';
                }
                if (!$errors) {
                    $email = $contact['email'] ?? null;
                    $phone = $contact['phone'] ?? null;
                    // Email obligatoire en colonne (UNIQUE NOT NULL). Si inscription par telephone,
                    // on genere un email technique interne non routable (le vrai contact = phone).
                    if ($email === null) {
                        $email = 'tel+' . preg_replace('/\D/', '', (string) $phone) . '@phone.trouvetateam.local';
                    }
                    $ins = $pdo->prepare('INSERT INTO users (name, email, phone) VALUES (?, ?, ?)');
                    $ins->execute([$name, $email, $phone]);
                    $userId = (int) $pdo->lastInsertId();
                    // Profil minimal (display_name) pour coherence avec /membres.
                    try {
                        $pf = $pdo->prepare('INSERT INTO profiles (user_id, display_name) VALUES (?, ?)');
                        $pf->execute([$userId, $name]);
                    } catch (\Throwable $e) {
                        error_log('[connexion] profile create: ' . $e->getMessage());
                    }
                    $user = ['id' => $userId, 'name' => $name, 'email' => $contact['email'] ?? null, 'phone' => $phone];
                    [$plain, $url] = ttt_issue_magic($userId, $next);
                    ttt_send_magic_mail($user, $url, 'welcome');
                    $sent = true;
                    $sentTo = (string) ($contact['email'] ?? $phone);
                }
            }
        } catch (\Throwable $e) {
            error_log('[connexion] ' . $e->getMessage());
            $errors['contact'] = 'Une erreur est survenue. Reessaie dans un instant.';
        }
    }
}

ttt_head(
    'Connexion / Inscription | TrouveTaTeam',
    'Connecte-toi ou cree ton compte TrouveTaTeam en 10 secondes, sans mot de passe : on t envoie un lien magique.',
    'connexion'
);
?>
  <main id="main">
    <section class="section" style="max-width:520px;margin:0 auto">
      <div class="card card-pad" style="margin-top:8px">
        <?php if ($sent): ?>
          <h1 style="margin:0 0 10px;font-size:1.5rem">Verifie ta boite 📬</h1>
          <p style="color:var(--ink-2);line-height:1.55">
            On vient d'envoyer un lien de connexion a <strong><?= ttt_e($sentTo) ?></strong>.
            Clique dessus pour te connecter (valable 30 minutes, usage unique).
          </p>
          <p style="color:var(--ink-2);font-size:.9rem;margin-top:14px">
            Pas recu ? Verifie tes spams, ou <a href="/connexion">recommence</a>.
          </p>
        <?php else: ?>
          <h1 style="margin:0 0 6px;font-size:1.6rem">Connexion</h1>
          <p style="color:var(--ink-2);margin:0 0 20px;line-height:1.5">
            Pas de mot de passe. Entre ton prenom et ton email : on t'envoie un lien magique pour te connecter.
          </p>
          <form method="post" action="/connexion" novalidate>
            <?= ttt_csrf_field() ?>
            <input type="hidden" name="next" value="<?= ttt_e($next) ?>">
            <label class="field">
              <span>Prenom <span style="font-weight:500;color:var(--ink-2)">(pour un nouveau compte)</span></span>
              <input class="input" type="text" name="name" maxlength="40" autocomplete="given-name"
                     value="<?= ttt_e($old['name']) ?>" placeholder="Ton prenom">
              <?php if (isset($errors['name'])): ?><small style="color:#b4231f;display:block;margin-top:6px"><?= ttt_e($errors['name']) ?></small><?php endif; ?>
            </label>
            <label class="field">
              <span>Email ou telephone</span>
              <input class="input" type="text" name="contact" required autocomplete="email"
                     value="<?= ttt_e($old['contact']) ?>" placeholder="toi@exemple.fr">
              <?php if (isset($errors['contact'])): ?><small style="color:#b4231f;display:block;margin-top:6px"><?= ttt_e($errors['contact']) ?></small><?php endif; ?>
            </label>
            <button type="submit" class="btn btn-primary btn-lg btn-block">Recevoir mon lien</button>
          </form>
          <p style="color:var(--ink-2);font-size:.82rem;margin-top:16px;line-height:1.5">
            En continuant, tu rejoins une communaute d'adultes. Reserve aux 18 ans et plus.
          </p>
        <?php endif; ?>
      </div>
    </section>
  </main>
<?php ttt_footer(); ?>
