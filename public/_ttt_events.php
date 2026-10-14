<?php
declare(strict_types=1);
/**
 * _ttt_events.php — Logique metier des sorties (events) + inscriptions (rsvps),
 * reliee a la base MySQL reelle.
 *
 * PROD 2026-10 — dig-holding : Mehdi Benali (archi) / Clara Dupont (back) / Lukas Brennan (front).
 *
 * Toutes les ecritures passent par PDO prepare (anti-injection). Les regles metier
 * (capacite, organisateur seul maitre de sa sortie, usage unique) sont appliquees ici.
 */

require_once __DIR__ . '/_ttt_auth.php';
require_once __DIR__ . '/_ttt_themes.php';

/** Ville pilote (Toulouse). city_id NOT NULL dans events. */
function ttt_default_city_id(): int
{
    return 1;
}

/** Point par defaut : centre de Toulouse (POINT(lng lat) SRID 0). */
function ttt_default_point_wkt(): string
{
    return 'POINT(1.4442 43.6047)';
}

/** Themes proposes a la creation (taxonomie complete 2026-10). */
function ttt_event_categories(): array
{
    return ttt_theme_slugs();
}

/** Genere un code d'invitation court (sorties privees). */
function ttt_make_invite_code(): string
{
    return strtoupper(bin2hex(random_bytes(4))); // 8 caracteres hex
}

/** Quartiers de la ville pilote, pour le select. */
function ttt_neighborhoods(): array
{
    try {
        $stmt = ttt_pdo()->prepare('SELECT id, name FROM neighborhoods WHERE city_id = ? ORDER BY name ASC');
        $stmt->execute([ttt_default_city_id()]);
        return $stmt->fetchAll();
    } catch (\Throwable $e) {
        error_log('[ttt_events] neighborhoods: ' . $e->getMessage());
        return [];
    }
}

/**
 * Charge une sortie complete (jointures + compteur d'inscrits + statut du viewer).
 * Retourne null si introuvable.
 */
function ttt_event_full(int $id, ?int $viewerId = null): ?array
{
    try {
        $sql = "
            SELECT e.*,
                   ST_Y(e.location) AS lat, ST_X(e.location) AS lng,
                   n.name AS neighborhood, c.name AS city,
                   u.name AS host_name,
                   (SELECT COUNT(*) FROM rsvps r WHERE r.event_id = e.id AND r.status = 'going') AS going_count
            FROM events e
            LEFT JOIN neighborhoods n ON n.id = e.neighborhood_id
            LEFT JOIN cities c ON c.id = e.city_id
            LEFT JOIN users u ON u.id = e.host_user_id
            WHERE e.id = ? LIMIT 1
        ";
        $stmt = ttt_pdo()->prepare($sql);
        $stmt->execute([$id]);
        $ev = $stmt->fetch();
        if (!$ev) {
            return null;
        }
        $ev['viewer_going'] = false;
        if ($viewerId) {
            $rs = ttt_pdo()->prepare("SELECT status FROM rsvps WHERE event_id = ? AND user_id = ? LIMIT 1");
            $rs->execute([$id, $viewerId]);
            $r = $rs->fetch();
            $ev['viewer_going'] = $r && $r['status'] === 'going';
        }
        return $ev;
    } catch (\Throwable $e) {
        error_log('[ttt_events] event_full: ' . $e->getMessage());
        return null;
    }
}

/** Liste des participants "going" d'une sortie (organisateur d'abord). */
function ttt_event_participants(int $id): array
{
    try {
        $sql = "
            SELECT u.id, u.name
            FROM rsvps r
            JOIN users u ON u.id = r.user_id
            JOIN events e ON e.id = r.event_id
            WHERE r.event_id = ? AND r.status = 'going'
            ORDER BY (u.id = e.host_user_id) DESC, r.created_at ASC, u.id ASC
        ";
        $stmt = ttt_pdo()->prepare($sql);
        $stmt->execute([$id]);
        return $stmt->fetchAll();
    } catch (\Throwable $e) {
        error_log('[ttt_events] participants: ' . $e->getMessage());
        return [];
    }
}

/**
 * Valide les champs d'une sortie. Retourne [clean, errors].
 * $partial = true : ne valide que les champs presents (edition).
 */
function ttt_validate_event(array $b, bool $partial = false): array
{
    $clean = [];
    $errors = [];
    $has = function ($k) use ($b) {
        return array_key_exists($k, $b);
    };
    $need = function ($k) use ($partial, $has) {
        return !$partial || $has($k);
    };

    if ($need('title')) {
        $t = trim((string) ($b['title'] ?? ''));
        $t = preg_replace('/\s+/', ' ', $t);
        if (mb_strlen($t) < 3) {
            $errors['title'] = 'Titre trop court (3 caracteres minimum).';
        } elseif (mb_strlen($t) > 70) {
            $errors['title'] = 'Titre trop long (70 caracteres maximum).';
        } else {
            $clean['title'] = $t;
        }
    }
    if ($need('category')) {
        $c = ttt_theme_alias(strtolower(trim((string) ($b['category'] ?? ''))));
        if (!ttt_theme_valid($c)) {
            $errors['category'] = 'Choisis une activite dans la liste.';
        } else {
            $clean['category'] = $c;
        }
    }
    if ($has('visibility')) {
        $vis = strtolower(trim((string) $b['visibility']));
        $clean['visibility'] = $vis === 'private' ? 'private' : 'public';
    }
    if ($has('neighborhood_id')) {
        $nid = (string) $b['neighborhood_id'];
        if ($nid === '' || $nid === '0') {
            $clean['neighborhood_id'] = null;
        } elseif (ctype_digit($nid)) {
            $clean['neighborhood_id'] = (int) $nid;
        } else {
            $errors['neighborhood_id'] = 'Quartier invalide.';
        }
    }
    if ($need('starts_at')) {
        $raw = trim((string) ($b['starts_at'] ?? ''));
        // Format attendu du <input type="datetime-local"> : YYYY-MM-DDTHH:MM
        $ts = $raw !== '' ? strtotime($raw) : false;
        if ($ts === false) {
            $errors['starts_at'] = 'Date et heure invalides.';
        } elseif (!$partial && $ts < time() - 3600) {
            $errors['starts_at'] = 'La date est deja passee.';
        } elseif ((int) date('Y', $ts) > (int) date('Y') + 5) {
            $errors['starts_at'] = 'Date trop lointaine.';
        } else {
            $clean['starts_at'] = date('Y-m-d H:i:s', $ts);
        }
    }
    if ($has('capacity')) {
        $capRaw = trim((string) $b['capacity']);
        if ($capRaw === '') {
            $clean['capacity'] = null;
        } elseif (ctype_digit($capRaw)) {
            $cap = (int) $capRaw;
            if ($cap < 2 || $cap > 500) {
                $errors['capacity'] = 'Nombre de places entre 2 et 500 (ou vide = illimite).';
            } else {
                $clean['capacity'] = $cap;
            }
        } else {
            $errors['capacity'] = 'Nombre de places invalide.';
        }
    }
    if ($has('description')) {
        $d = trim((string) $b['description']);
        $d = str_replace(["\r\n", "\r"], "\n", $d);
        if (mb_strlen($d) > 600) {
            $errors['description'] = 'Description trop longue (600 caracteres maximum).';
        } else {
            $clean['description'] = $d;
        }
    }
    return [$clean, $errors];
}

/**
 * Cree une sortie et inscrit automatiquement l'organisateur. Retourne l'id.
 * Location = centroid du quartier si fourni, sinon centre Toulouse.
 */
function ttt_event_create(int $hostUserId, array $c): int
{
    $pdo = ttt_pdo();
    $pdo->beginTransaction();
    try {
        $nid = $c['neighborhood_id'] ?? null;
        // Expression SQL pour la localisation (NOT NULL)
        if ($nid) {
            $locExpr = '(SELECT centroid FROM neighborhoods WHERE id = :nid2)';
        } else {
            $locExpr = "ST_GeomFromText('" . ttt_default_point_wkt() . "', 0)";
        }
        $sql = "INSERT INTO events (host_user_id, city_id, neighborhood_id, title, description, category, starts_at, capacity, status, visibility, invite_code, location)
                VALUES (:host, :city, :nid, :title, :descr, :cat, :starts, :cap, 'published', :vis, :code, {$locExpr})";
        $stmt = $pdo->prepare($sql);
        $params = [
            ':host' => $hostUserId,
            ':city' => ttt_default_city_id(),
            ':nid' => $nid,
            ':title' => $c['title'],
            ':descr' => $c['description'] ?? null,
            ':cat' => $c['category'] ?? null,
            ':starts' => $c['starts_at'],
            ':cap' => $c['capacity'] ?? null,
            ':vis' => ($c['visibility'] ?? 'public') === 'private' ? 'private' : 'public',
            ':code' => ttt_make_invite_code(),
        ];
        if ($nid) {
            $params[':nid2'] = $nid;
        }
        $stmt->execute($params);
        $eventId = (int) $pdo->lastInsertId();

        // Auto-inscription de l'organisateur
        $rs = $pdo->prepare(
            "INSERT INTO rsvps (event_id, user_id, status) VALUES (?, ?, 'going')
             ON DUPLICATE KEY UPDATE status = 'going', updated_at = NOW()"
        );
        $rs->execute([$eventId, $hostUserId]);

        $pdo->commit();
        return $eventId;
    } catch (\Throwable $e) {
        $pdo->rollBack();
        error_log('[ttt_events] create: ' . $e->getMessage());
        throw $e;
    }
}

/** Met a jour une sortie (champs en liste blanche). */
function ttt_event_update(int $eventId, array $c): void
{
    $allowed = ['title', 'description', 'category', 'starts_at', 'capacity', 'neighborhood_id', 'visibility'];
    $sets = [];
    $params = [':id' => $eventId];
    foreach ($allowed as $k) {
        if (array_key_exists($k, $c)) {
            $sets[] = "`$k` = :$k";
            $params[":$k"] = $c[$k];
        }
    }
    // Une sortie qui devient privee doit disposer d'un code d'invitation.
    if (array_key_exists('visibility', $c) && $c['visibility'] === 'private') {
        $sets[] = 'invite_code = COALESCE(invite_code, :newcode)';
        $params[':newcode'] = ttt_make_invite_code();
    }
    // Si le quartier change, on realigne la localisation sur son centroid.
    if (array_key_exists('neighborhood_id', $c)) {
        if ($c['neighborhood_id']) {
            $sets[] = "location = (SELECT centroid FROM neighborhoods WHERE id = :nid2)";
            $params[':nid2'] = $c['neighborhood_id'];
        }
    }
    if (!$sets) {
        return;
    }
    $sql = 'UPDATE events SET ' . implode(', ', $sets) . ', updated_at = NOW() WHERE id = :id';
    $stmt = ttt_pdo()->prepare($sql);
    $stmt->execute($params);
}

/** Annule une sortie (status = cancelled). On ne SUPPRIME jamais la ligne. */
function ttt_event_cancel(int $eventId): void
{
    $stmt = ttt_pdo()->prepare("UPDATE events SET status = 'cancelled', updated_at = NOW() WHERE id = ?");
    $stmt->execute([$eventId]);
}

/**
 * Inscription (RSVP going) avec gestion des places limitees (transaction + verrou).
 * Retourne ['ok'=>bool, 'reason'=>'already'|'full'|'cancelled'|'joined'|'error'].
 */
function ttt_rsvp_join(int $eventId, int $userId): array
{
    $pdo = ttt_pdo();
    $pdo->beginTransaction();
    try {
        $es = $pdo->prepare('SELECT id, capacity, status FROM events WHERE id = ? FOR UPDATE');
        $es->execute([$eventId]);
        $ev = $es->fetch();
        if (!$ev) {
            $pdo->rollBack();
            return ['ok' => false, 'reason' => 'error'];
        }
        if ($ev['status'] === 'cancelled') {
            $pdo->rollBack();
            return ['ok' => false, 'reason' => 'cancelled'];
        }
        // Deja inscrit ?
        $rs = $pdo->prepare("SELECT status FROM rsvps WHERE event_id = ? AND user_id = ? LIMIT 1");
        $rs->execute([$eventId, $userId]);
        $cur = $rs->fetch();
        if ($cur && $cur['status'] === 'going') {
            $pdo->commit();
            return ['ok' => true, 'reason' => 'already'];
        }
        // Capacite
        $cap = $ev['capacity'] !== null ? (int) $ev['capacity'] : 0;
        if ($cap > 0) {
            $cs = $pdo->prepare("SELECT COUNT(*) FROM rsvps WHERE event_id = ? AND status = 'going'");
            $cs->execute([$eventId]);
            $going = (int) $cs->fetchColumn();
            if ($going >= $cap) {
                $pdo->rollBack();
                return ['ok' => false, 'reason' => 'full'];
            }
        }
        $ins = $pdo->prepare(
            "INSERT INTO rsvps (event_id, user_id, status) VALUES (?, ?, 'going')
             ON DUPLICATE KEY UPDATE status = 'going', updated_at = NOW()"
        );
        $ins->execute([$eventId, $userId]);
        $pdo->commit();
        return ['ok' => true, 'reason' => 'joined'];
    } catch (\Throwable $e) {
        $pdo->rollBack();
        error_log('[ttt_events] rsvp_join: ' . $e->getMessage());
        return ['ok' => false, 'reason' => 'error'];
    }
}

/** Desinscription (RSVP -> cancelled). L'organisateur ne peut pas se desinscrire de sa propre sortie. */
function ttt_rsvp_leave(int $eventId, int $userId): array
{
    try {
        $es = ttt_pdo()->prepare('SELECT host_user_id FROM events WHERE id = ? LIMIT 1');
        $es->execute([$eventId]);
        $ev = $es->fetch();
        if (!$ev) {
            return ['ok' => false, 'reason' => 'error'];
        }
        if ((int) $ev['host_user_id'] === $userId) {
            return ['ok' => false, 'reason' => 'host'];
        }
        $stmt = ttt_pdo()->prepare("UPDATE rsvps SET status = 'cancelled', updated_at = NOW() WHERE event_id = ? AND user_id = ?");
        $stmt->execute([$eventId, $userId]);
        return ['ok' => true, 'reason' => 'left'];
    } catch (\Throwable $e) {
        error_log('[ttt_events] rsvp_leave: ' . $e->getMessage());
        return ['ok' => false, 'reason' => 'error'];
    }
}

/** Sorties que j'organise (toutes, y compris annulees/passees), plus recentes d'abord. */
function ttt_my_hosted(int $userId): array
{
    try {
        $sql = "
            SELECT e.id, e.title, e.category, e.starts_at, e.capacity, e.status, e.is_sponsored,
                   n.name AS neighborhood, c.name AS city, u.name AS host_name,
                   (SELECT COUNT(*) FROM rsvps r WHERE r.event_id = e.id AND r.status = 'going') AS rsvps_count
            FROM events e
            LEFT JOIN neighborhoods n ON n.id = e.neighborhood_id
            LEFT JOIN cities c ON c.id = e.city_id
            LEFT JOIN users u ON u.id = e.host_user_id
            WHERE e.host_user_id = ?
            ORDER BY e.starts_at DESC
        ";
        $stmt = ttt_pdo()->prepare($sql);
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    } catch (\Throwable $e) {
        error_log('[ttt_events] my_hosted: ' . $e->getMessage());
        return [];
    }
}

/** Sorties ou je suis inscrit (going) mais que je n'organise pas. */
function ttt_my_joined(int $userId): array
{
    try {
        $sql = "
            SELECT e.id, e.title, e.category, e.starts_at, e.capacity, e.status, e.is_sponsored,
                   n.name AS neighborhood, c.name AS city, u.name AS host_name,
                   (SELECT COUNT(*) FROM rsvps r WHERE r.event_id = e.id AND r.status = 'going') AS rsvps_count
            FROM rsvps mr
            JOIN events e ON e.id = mr.event_id
            LEFT JOIN neighborhoods n ON n.id = e.neighborhood_id
            LEFT JOIN cities c ON c.id = e.city_id
            LEFT JOIN users u ON u.id = e.host_user_id
            WHERE mr.user_id = ? AND mr.status = 'going' AND e.host_user_id <> ?
            ORDER BY e.starts_at DESC
        ";
        $stmt = ttt_pdo()->prepare($sql);
        $stmt->execute([$userId, $userId]);
        return $stmt->fetchAll();
    } catch (\Throwable $e) {
        error_log('[ttt_events] my_joined: ' . $e->getMessage());
        return [];
    }
}
