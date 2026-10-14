<?php
declare(strict_types=1);
/**
 * deconnexion.php — Se deconnecter. POST (via bouton du header, protege CSRF).
 * PROD 2026-10 — dig-holding.
 */

require_once __DIR__ . '/_ttt_auth.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    ttt_check_csrf();
    ttt_logout();
    ttt_flash_set('ok', 'Tu es deconnecte. A bientot !');
    ttt_redirect('/');
}

// GET : page de confirmation simple (si quelqu'un atterrit ici directement).
require_once __DIR__ . '/_ttt_layout.php';
ttt_head('Deconnexion | TrouveTaTeam', 'Se deconnecter de TrouveTaTeam.', 'index');
?>
  <main id="main">
    <section class="section" style="max-width:420px;margin:0 auto">
      <div class="card card-pad" style="text-align:center">
        <h1 style="font-size:1.4rem;margin:0 0 14px">Se deconnecter ?</h1>
        <form method="post" action="/deconnexion">
          <?= ttt_csrf_field() ?>
          <button type="submit" class="btn btn-primary btn-block">Oui, me deconnecter</button>
        </form>
        <p style="margin-top:12px"><a class="btn btn-ghost btn-block" href="/">Annuler</a></p>
      </div>
    </section>
  </main>
<?php ttt_footer(); ?>
