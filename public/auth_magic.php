<?php
declare(strict_types=1);
/**
 * auth_magic.php — Connexion par lien magique : /auth/magic?t=<token>
 *
 * PROD 2026-10 — dig-holding : Mehdi Benali (archi) / Clara Dupont (back) / Lukas Brennan (front).
 *
 * BUG CORRIGE (2026-10) : les scanners d'email (Gmail, SafeLinks Outlook, antivirus) font un
 * GET de prefetch sur le lien. Avec une consommation du token au GET (usage unique), le token
 * etait consomme par le scanner et le VRAI clic de l'utilisateur tombait sur "lien expire" -> BOUCLE.
 *
 * Correctif : le GET ne consomme PLUS le token. Il affiche une page "Confirme ta connexion"
 * avec un BOUTON. C'est le POST (clic humain) qui consomme le token et ouvre la session.
 * Les scanners ne font que des GET -> ils ne consomment plus rien.
 */

require_once __DIR__ . '/_ttt_auth.php';

header('Cache-Control: no-store');
header('X-Robots-Tag: noindex, nofollow');

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

/* -------- POST : consommation reelle (clic humain) -------- */
if ($method === 'POST') {
    $t = (string) ($_POST['t'] ?? '');
    $res = ttt_consume_magic($t);
    if ($res === null) {
        // Deja consomme / expire : si l'utilisateur a deja une session (double-clic), on l'envoie
        // sur son compte ; sinon on le renvoie proprement vers la connexion.
        if (ttt_is_logged_in()) {
            ttt_redirect('/compte');
        }
        ttt_flash_set('error', 'Ce lien de connexion a expire ou a deja ete utilise. Demande-en un nouveau.');
        ttt_redirect('/connexion');
    }
    [$userId, $next] = $res;
    try {
        $pdo = ttt_pdo();
        $q = $pdo->prepare('SELECT id, is_blocked FROM users WHERE id = ? LIMIT 1');
        $q->execute([$userId]);
        $u = $q->fetch();
        if (!$u || (int) $u['is_blocked'] === 1) {
            ttt_flash_set('error', 'Compte introuvable ou bloque.');
            ttt_redirect('/connexion');
        }
        try {
            $pdo->prepare('UPDATE users SET email_verified_at = COALESCE(email_verified_at, NOW()) WHERE id = ?')->execute([$userId]);
        } catch (\Throwable $e) {
            // ignore
        }
        ttt_login_user($userId);
        ttt_flash_set('ok', 'Te voila connecte. Bienvenue !');
        ttt_redirect($next ?: '/compte');
    } catch (\Throwable $e) {
        error_log('[auth_magic] POST ' . $e->getMessage());
        ttt_flash_set('error', 'Connexion impossible pour le moment. Reessaie.');
        ttt_redirect('/connexion');
    }
}

/* -------- GET : page de confirmation (NE CONSOMME PAS le token) -------- */
$t = (string) ($_GET['t'] ?? '');
$peek = ttt_peek_magic($t);
$valid = $peek !== null;
$tEsc = htmlspecialchars($t, ENT_QUOTES, 'UTF-8');

header('Content-Type: text/html; charset=utf-8');
?>
<!doctype html>
<html lang="fr"><head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>Connexion — TrouveTaTeam</title>
<style>
  body{margin:0;min-height:100vh;display:grid;place-items:center;background:#f4f1ee;font-family:system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;color:#1f2330;padding:16px}
  .card{background:#fff;border-radius:18px;padding:34px 26px;max-width:400px;width:100%;text-align:center;box-shadow:0 10px 30px rgba(0,0,0,.06);box-sizing:border-box}
  .logo{font-weight:800;font-size:1.5rem;margin-bottom:14px}.logo span{color:#ff5a36}
  h1{font-size:1.2rem;margin:0 0 8px}
  p{color:#4b5060;line-height:1.5;margin:0 0 6px}
  button{margin-top:20px;border:0;border-radius:999px;background:#ff5a36;color:#fff;font:inherit;font-weight:700;font-size:1.05rem;padding:15px 26px;cursor:pointer;width:100%}
  a.btn{display:inline-block;margin-top:18px;color:#ff5a36;font-weight:700;text-decoration:none}
</style></head>
<body><main class="card">
  <div class="logo">Trouve<span>Ta</span>Team</div>
  <?php if ($valid): ?>
    <h1>Confirme ta connexion</h1>
    <p>Clique sur le bouton pour accéder à ton compte.</p>
    <form method="post" action="/auth/magic">
      <input type="hidden" name="t" value="<?= $tEsc ?>">
      <button type="submit">Me connecter</button>
    </form>
  <?php else: ?>
    <h1>Lien expiré</h1>
    <p>Ce lien de connexion a expiré ou a déjà été utilisé.</p>
    <a class="btn" href="/connexion">Demander un nouveau lien →</a>
  <?php endif; ?>
</main></body></html>
