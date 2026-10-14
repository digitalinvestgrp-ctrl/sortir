<?php
declare(strict_types=1);
/**
 * dev_mails.php — Boite d'envoi de dev (/dev/mails?key=...).
 *
 * PROD 2026-10 — dig-holding. Permet de recuperer les liens magiques quand aucun SMTP
 * n'est configure (regle DIG : pas de service paye sans GO). PROTEGE par une cle secrete
 * (MAIL_DEV_KEY du .env) : sans la bonne cle -> 404. Evite toute prise de compte publique.
 */

require_once __DIR__ . '/_ttt_auth.php';

header('Cache-Control: no-store');
header('X-Robots-Tag: noindex, nofollow');

$key = ttt_mail_dev_key();
$given = (string) ($_GET['key'] ?? '');

// Pas de cle configuree, ou cle fournie incorrecte -> 404 (page "inexistante").
if ($key === '' || !hash_equals($key, $given)) {
    http_response_code(404);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><meta charset="utf-8"><title>404</title><h1>404 Not Found</h1>';
    exit;
}

$mails = ttt_outbox_recent(40);
header('Content-Type: text/html; charset=utf-8');
?>
<!doctype html>
<html lang="fr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>Dev mailbox — TrouveTaTeam</title>
<style>
  body{margin:0;background:#f4f1ee;font-family:system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;color:#1f2330;padding:24px}
  h1{font-size:1.3rem}
  .mail{background:#fff;border:1px solid #e7e2db;border-radius:14px;padding:16px 18px;margin:0 0 12px;max-width:760px}
  .meta{color:#6b7080;font-size:.82rem;margin-bottom:6px}
  .subj{font-weight:700;margin-bottom:8px}
  a.link{word-break:break-all;color:#d8431f;font-weight:600}
  .empty{color:#6b7080}
</style></head>
<body>
  <h1>Dev mailbox 📬 <span style="font-size:.8rem;color:#6b7080;font-weight:400">(liens magiques — usage unique)</span></h1>
  <?php if (!$mails): ?>
    <p class="empty">Aucun mail pour l'instant. Demande un lien depuis <a href="/connexion">/connexion</a>.</p>
  <?php else: ?>
    <?php foreach ($mails as $m): ?>
      <div class="mail">
        <div class="meta"><?= ttt_e($m['ts'] ?? '') ?> · <?= ttt_e($m['kind'] ?? '') ?> · a <strong><?= ttt_e($m['to'] ?? '') ?></strong></div>
        <div class="subj"><?= ttt_e($m['subject'] ?? '') ?></div>
        <?php if (!empty($m['url'])): ?>
          <a class="link" href="<?= ttt_e($m['url']) ?>"><?= ttt_e($m['url']) ?></a>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>
</body></html>
