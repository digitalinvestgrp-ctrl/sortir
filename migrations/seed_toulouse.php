<?php
/**
 * Seeder Toulouse (one-shot, pattern Jurenys/Agendia)
 *
 * Usage : `https://www.trouvetateam.fr/migrations/seed_toulouse.php?token=<MIGRATIONS_TOKEN>`
 *
 * Cree : 1 ville (Toulouse) + 10 quartiers + 5 membres demo + 1 groupe + 1 etablissement pro + 5 sorties + RSVPs
 * Tous les inserts sont idempotents (INSERT IGNORE / ON DUPLICATE KEY UPDATE)
 */
declare(strict_types=1);

// Token de garde
$expectedToken = $_ENV['MIGRATIONS_TOKEN'] ?? getenv('MIGRATIONS_TOKEN');
if (!$expectedToken) {
    $envFile = __DIR__ . '/../.env';
    if (file_exists($envFile)) {
        foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            if (str_starts_with(trim($line), 'MIGRATIONS_TOKEN=')) {
                $expectedToken = trim(substr(trim($line), 17), " \t\"'");
                break;
            }
        }
    }
}

if (!$expectedToken || !hash_equals($expectedToken, $_GET['token'] ?? '')) {
    http_response_code(403);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Forbidden']);
    exit;
}

require_once __DIR__ . '/../src/Core/Bootstrap.php';
\App\Core\Bootstrap::init();

header('Content-Type: application/json; charset=utf-8');

$pdo = \App\Core\Pdo::instance();

$report = ['neighborhoods' => 0, 'users' => 0, 'group' => 0, 'pro' => 0, 'events' => 0, 'rsvps' => 0];

// ----- City Toulouse -----
$pdo->prepare("INSERT INTO cities (name, slug, country_code, is_pilot) VALUES (?, ?, 'FR', 1)
  ON DUPLICATE KEY UPDATE name=VALUES(name), is_pilot=VALUES(is_pilot)")
    ->execute(['Toulouse', 'toulouse']);
$cityId = (int) $pdo->query("SELECT id FROM cities WHERE slug='toulouse'")->fetchColumn();

// ----- Neighborhoods -----
$neighborhoods = [
    ['Capitole', 'capitole', 43.6045, 1.4440],
    ['Saint-Cyprien', 'saint-cyprien', 43.5960, 1.4290],
    ['Carmes', 'carmes', 43.5980, 1.4445],
    ['Saint-Michel', 'saint-michel', 43.5895, 1.4470],
    ['Esquirol', 'esquirol', 43.6005, 1.4440],
    ['Compans-Caffarelli', 'compans-caffarelli', 43.6130, 1.4350],
    ['Minimes', 'minimes', 43.6250, 1.4360],
    ['Rangueil', 'rangueil', 43.5650, 1.4640],
    ['Jolimont', 'jolimont', 43.6135, 1.4660],
    ['Croix-Daurade', 'croix-daurade', 43.6420, 1.4630],
];
$hoodIds = [];
foreach ($neighborhoods as [$name, $slug, $lat, $lng]) {
    // POINT(lng, lat) — convention standard du projet
    $wkt = sprintf('POINT(%F %F)', $lng, $lat);
    $pdo->prepare("INSERT INTO neighborhoods (city_id, name, slug, centroid) VALUES (?, ?, ?, ST_GeomFromText(?, 0))
      ON DUPLICATE KEY UPDATE name=VALUES(name), centroid=VALUES(centroid)")
        ->execute([$cityId, $name, $slug, $wkt]);
    $hoodIds[$slug] = [
        'id' => (int) $pdo->query("SELECT id FROM neighborhoods WHERE city_id={$cityId} AND slug='{$slug}'")->fetchColumn(),
        'lat' => $lat, 'lng' => $lng,
    ];
    $report['neighborhoods']++;
}

// ----- Members demo (gate 18+) -----
$members = [];
$demo = [
    ['Lea Fontan', 'lea@demo.trouvetateam', 'capitole', '1994-03-12'],
    ['Hugo Marty', 'hugo@demo.trouvetateam', 'saint-cyprien', '1990-07-22'],
    ['Sarah Belin', 'sarah@demo.trouvetateam', 'carmes', '1997-11-02'],
    ['Theo Roux', 'theo@demo.trouvetateam', 'rangueil', '1988-01-30'],
    ['Camille Aubry', 'camille@demo.trouvetateam', 'minimes', '1992-09-18'],
];
$pwHash = password_hash('password123', PASSWORD_BCRYPT, ['cost' => 12]);
foreach ($demo as [$name, $email, $hood, $birth]) {
    $pdo->prepare("INSERT INTO users (name, email, password, birthdate, phone_verified_at) VALUES (?, ?, ?, ?, NOW())
      ON DUPLICATE KEY UPDATE name=VALUES(name), birthdate=VALUES(birthdate)")
        ->execute([$name, $email, $pwHash, $birth]);
    $uid = (int) $pdo->query("SELECT id FROM users WHERE email='{$email}'")->fetchColumn();
    $pdo->prepare("INSERT INTO profiles (user_id, neighborhood_id, display_name, reputation_score, attended_count) VALUES (?, ?, ?, ?, ?)
      ON DUPLICATE KEY UPDATE display_name=VALUES(display_name), neighborhood_id=VALUES(neighborhood_id)")
        ->execute([$uid, $hoodIds[$hood]['id'], $name, random_int(0, 50), random_int(0, 10)]);
    $members[] = $uid;
    $report['users']++;
}

// ----- Interest group -----
$pdo->prepare("INSERT INTO interest_groups (owner_user_id, city_id, name, slug, description, category, members_count) VALUES (?, ?, ?, ?, ?, ?, ?)
  ON DUPLICATE KEY UPDATE name=VALUES(name), description=VALUES(description)")
    ->execute([$members[0], $cityId, 'Rando du dimanche', 'rando-du-dimanche', 'On marche ensemble chaque dimanche autour de Toulouse.', 'sport', count($members)]);
$groupId = (int) $pdo->query("SELECT id FROM interest_groups WHERE city_id={$cityId} AND slug='rando-du-dimanche'")->fetchColumn();
$report['group'] = 1;

// ----- Pro establishment -----
$proEmail = 'bar@demo.trouvetateam';
$pdo->prepare("INSERT INTO users (name, email, password, birthdate, phone_verified_at) VALUES (?, ?, ?, ?, NOW())
  ON DUPLICATE KEY UPDATE name=VALUES(name)")
    ->execute(['Le Comptoir du Capitole', $proEmail, $pwHash, '1980-05-05']);
$proOwnerId = (int) $pdo->query("SELECT id FROM users WHERE email='{$proEmail}'")->fetchColumn();

$wktCap = sprintf('POINT(%F %F)', $hoodIds['capitole']['lng'], $hoodIds['capitole']['lat']);
$pdo->prepare("INSERT INTO pro_establishments (owner_user_id, city_id, neighborhood_id, name, category, description, address_public, location, plan, is_verified) VALUES (?, ?, ?, ?, ?, ?, ?, ST_GeomFromText(?, 0), ?, 1)
  ON DUPLICATE KEY UPDATE name=VALUES(name), location=VALUES(location)")
    ->execute([$proOwnerId, $cityId, $hoodIds['capitole']['id'], 'Le Comptoir du Capitole', 'bar', 'Bar a tapas place du Capitole. Soirees a theme en semaine.', 'Place du Capitole, Toulouse', $wktCap, 'pro_start']);
$estabId = (int) $pdo->query("SELECT id FROM pro_establishments WHERE name='Le Comptoir du Capitole'")->fetchColumn();
$report['pro'] = 1;

// ----- Events -----
$events = [
    ['Apero decouverte au Capitole', 'Premier verre pour les nouveaux Toulousains.', 'aperitif', 'capitole', 2, 0, $members[0], $estabId, null],
    ['Rando Canal du Midi', 'Marche tranquille le long du canal, niveau debutant.', 'sport', 'saint-cyprien', 5, 0, $members[1], null, $groupId],
    ['Jeux de societe aux Carmes', 'Soiree jeux dans un cafe convivial.', 'jeux', 'carmes', 3, 0, $members[2], null, null],
    ['Running matinal Rangueil', 'Footing 5km, allure cool.', 'sport', 'rangueil', 1, 0, $members[3], null, null],
    ['Concert local aux Minimes', 'Scene ouverte, musiciens du quartier.', 'musique', 'minimes', 4, 1, $proOwnerId, $estabId, null],
];

foreach ($events as $i => [$title, $desc, $cat, $hood, $daysAhead, $sponsored, $hostId, $eId, $gId]) {
    $h = $hoodIds[$hood];
    $startsAt = (new \DateTimeImmutable("+{$daysAhead} days"))->setTime(19, 0)->format('Y-m-d H:i:s');
    $wkt = sprintf('POINT(%F %F)', $h['lng'] + 0.001 * $i, $h['lat'] + 0.001 * $i);
    $pdo->prepare("INSERT INTO events (host_user_id, establishment_id, interest_group_id, city_id, neighborhood_id, title, description, category, starts_at, capacity, is_sponsored, status, location) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'published', ST_GeomFromText(?, 0))
      ON DUPLICATE KEY UPDATE description=VALUES(description), starts_at=VALUES(starts_at), location=VALUES(location)")
        ->execute([$hostId, $eId, $gId, $cityId, $h['id'], $title, $desc, $cat, $startsAt, random_int(6, 20), $sponsored, $wkt]);
    $eventId = (int) $pdo->lastInsertId();
    if (!$eventId) {
        $eventId = (int) $pdo->query("SELECT id FROM events WHERE title=" . $pdo->quote($title))->fetchColumn();
    }
    $report['events']++;

    // RSVPs (1 a count members)
    $going = array_slice($members, 0, random_int(1, count($members)));
    foreach ($going as $m) {
        $pdo->prepare("INSERT INTO rsvps (event_id, user_id, status) VALUES (?, ?, 'going')
          ON DUPLICATE KEY UPDATE status=VALUES(status)")
            ->execute([$eventId, $m]);
        $report['rsvps']++;
    }
}

echo json_encode(['success' => true, 'seeded' => $report, 'time' => gmdate('Y-m-d H:i:s')], JSON_PRETTY_PRINT);
