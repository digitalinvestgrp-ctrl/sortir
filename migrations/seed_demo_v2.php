<?php
/**
 * Seeder Demo V2 (one-shot, pattern Jurenys/Agendia)
 *
 * Usage : `https://www.trouvetateam.fr/migrations/seed_demo_v2.php?token=<MIGRATIONS_TOKEN>`
 *
 * Cree des donnees fictives realistes pour visualisation Stephane :
 *   - 10 membres fictifs (prenoms FR + initiale, geoloc Toulouse variee, bios)
 *   - 5 sorties variees (apero, rando, jeux, sport, culturel)
 *   - RSVPs croises (3-7 par event)
 *
 * Idempotent (INSERT IGNORE / ON DUPLICATE KEY UPDATE).
 * Reutilise les quartiers crees par seed_toulouse.php.
 * Coexiste avec les 5 membres demo V1 (emails differents @demo2.trouvetateam).
 *
 * Donnees fictives RGPD-safe :
 *   - Prenoms communs FR + initiale nom (pas de nom complet)
 *   - Telephones reserves ARCEP : 06.99.XX.XX.XX / 07.99.XX.XX.XX
 *   - Avatars genere via ui-avatars.com (free, initiales)
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

$report = ['neighborhoods_used' => 0, 'users' => 0, 'events' => 0, 'rsvps' => 0];

// ----- Ville Toulouse (doit exister via seed_toulouse.php) -----
$cityId = (int) $pdo->query("SELECT id FROM cities WHERE slug='toulouse'")->fetchColumn();
if (!$cityId) {
    echo json_encode(['error' => 'City toulouse manquante. Lancer seed_toulouse.php d\'abord.']);
    exit;
}

// ----- Quartiers (utilise ce qui existe + ajoute ceux manquants demandes) -----
$neighborhoodsWanted = [
    ['Capitole', 'capitole', 43.6045, 1.4440],
    ['Saint-Cyprien', 'saint-cyprien', 43.5960, 1.4290],
    ['Compans-Caffarelli', 'compans-caffarelli', 43.6130, 1.4350],
    ['Carmes', 'carmes', 43.5980, 1.4445],
    ['Minimes', 'minimes', 43.6250, 1.4360],
    ['Saint-Michel', 'saint-michel', 43.5895, 1.4470],
    ['Empalot', 'empalot', 43.5780, 1.4530],
    ['Rangueil', 'rangueil', 43.5650, 1.4640],
    ['Borderouge', 'borderouge', 43.6480, 1.4520],
    ['Croix-Daurade', 'croix-daurade', 43.6420, 1.4630],
];
$hoodIds = [];
foreach ($neighborhoodsWanted as [$name, $slug, $lat, $lng]) {
    $wkt = sprintf('POINT(%F %F)', $lng, $lat);
    $pdo->prepare("INSERT INTO neighborhoods (city_id, name, slug, centroid) VALUES (?, ?, ?, ST_GeomFromText(?, 0))
      ON DUPLICATE KEY UPDATE name=VALUES(name), centroid=VALUES(centroid)")
        ->execute([$cityId, $name, $slug, $wkt]);
    $row = $pdo->query("SELECT id FROM neighborhoods WHERE city_id={$cityId} AND slug='{$slug}'")->fetch();
    $hoodIds[$slug] = [
        'id' => (int) $row['id'],
        'lat' => $lat,
        'lng' => $lng,
        'name' => $name,
    ];
    $report['neighborhoods_used']++;
}

// ----- 10 membres fictifs realistes -----
// Format : [prenom, initiale_nom, email, quartier, naissance, bio, phone_arcep_99]
// Telephones reserves ARCEP 06.99.XX.XX.XX et 07.99.XX.XX.XX (jamais attribues, fiction safe)
$membersData = [
    ['Lea',     'M.', 'lea.m@demo2.trouvetateam',     'capitole',           '1998-03-12', 'Passionnee de rando et de concerts indie. Cherche des partenaires pour des escapades nature le week-end.',                'phone' => '0699121212'],
    ['Karim',   'B.', 'karim.b@demo2.trouvetateam',   'saint-cyprien',      '1992-07-22', 'Coach sportif, je cherche des partenaires pour running matinal (5h30-7h) le long de la Garonne.',                  'phone' => '0699232323'],
    ['Sophie',  'D.', 'sophie.d@demo2.trouvetateam',  'compans-caffarelli', '2000-11-02', 'Etudiante en archi, fan d expos et de brunchs dominicaux. Toujours partante pour les nouvelles ouvertures.',         'phone' => '0699343434'],
    ['Marc',    'T.', 'marc.t@demo2.trouvetateam',    'rangueil',           '1985-01-30', 'Papa solo (un fiston de 8 ans), je cherche des sorties famille le week-end : parcs, musees, pique-niques.',          'phone' => '0699454545'],
    ['Ines',    'B.', 'ines.b@demo2.trouvetateam',    'carmes',             '1995-09-18', 'Dev backend, soirees jeux de societe et aperos en petit comite. Catan, 7 Wonders, Codenames, je gere tout.',          'phone' => '0699565656'],
    ['Hugo',    'L.', 'hugo.l@demo2.trouvetateam',    'minimes',            '2002-05-08', 'Etudiant kine, foot a 5 et bars a biere artisanale. Disponible la plupart des soirs apres 19h.',                     'phone' => '0699676767'],
    ['Camille', 'V.', 'camille.v@demo2.trouvetateam', 'saint-michel',       '1988-12-15', 'Graphiste freelance, marches bio le samedi matin et cine independant en semaine. Cafes calmes uniquement.',         'phone' => '0699787878'],
    ['Yann',    'P.', 'yann.p@demo2.trouvetateam',    'empalot',            '1990-06-25', 'Ingenieur son, concerts live, festivals et jam sessions. Connaisseur des salles toulousaines.',                      'phone' => '0699898989'],
    ['Sarah',   'K.', 'sarah.k@demo2.trouvetateam',   'borderouge',         '1997-04-11', 'Prof de yoga, randos zen le dimanche matin et brunchs vegetariens. Cercle bienveillant uniquement.',                'phone' => '0799112233'],
    ['Pierre',  'M.', 'pierre.m@demo2.trouvetateam', 'croix-daurade',      '1981-08-03', 'Restaurateur, degustations et foires gastronomiques. Toujours en quete de nouvelles adresses sud-ouest.',           'phone' => '0799223344'],
];

$pwHash = password_hash('demo2password', PASSWORD_BCRYPT, ['cost' => 12]);
$members = [];
foreach ($membersData as $m) {
    [$prenom, $init, $email, $hood, $birth, $bio] = $m;
    $phone = $m['phone'];
    $name = $prenom . ' ' . $init;
    $displayName = $name;

    $pdo->prepare("INSERT INTO users (name, email, password, birthdate, phone, phone_verified_at) VALUES (?, ?, ?, ?, ?, NOW())
      ON DUPLICATE KEY UPDATE name=VALUES(name), birthdate=VALUES(birthdate), phone=VALUES(phone)")
        ->execute([$name, $email, $pwHash, $birth, $phone]);
    $uid = (int) $pdo->query("SELECT id FROM users WHERE email=" . $pdo->quote($email))->fetchColumn();

    // Avatar via ui-avatars.com (genere a partir initiales, free, aucune photo volee)
    $initials = urlencode($prenom . '+' . trim($init, '.'));
    $avatarUrl = "https://ui-avatars.com/api/?name={$initials}&background=6366f1&color=ffffff&size=200&bold=true";

    $pdo->prepare("INSERT INTO profiles (user_id, neighborhood_id, display_name, bio, avatar_url, reputation_score, attended_count) VALUES (?, ?, ?, ?, ?, ?, ?)
      ON DUPLICATE KEY UPDATE display_name=VALUES(display_name), neighborhood_id=VALUES(neighborhood_id), bio=VALUES(bio), avatar_url=VALUES(avatar_url)")
        ->execute([$uid, $hoodIds[$hood]['id'], $displayName, $bio, $avatarUrl, random_int(15, 95), random_int(2, 25)]);

    $members[] = ['id' => $uid, 'name' => $displayName, 'hood' => $hood];
    $report['users']++;
}

// Helper : trouve member par nom court (ex: 'lea', 'karim')
$findMember = function(string $shortName) use ($members): array {
    foreach ($members as $m) {
        if (str_starts_with(strtolower($m['name']), strtolower($shortName))) {
            return $m;
        }
    }
    throw new \RuntimeException("Member {$shortName} introuvable");
};

// ----- 5 sorties fictives variees -----
// Format : [titre, description, categorie, quartier, days_ahead, hour, capacite, organisateur_prenom, rsvp_prenoms[]]
$events = [
    [
        'title' => 'Apero terrasse Saint-Pierre',
        'desc'  => "Premier verre de l\'ete sur les quais. On se retrouve a la terrasse Saint-Pierre pour un apero detente apres le boulot. Ambiance tranquille, on echange, on rit, on profite du coucher de soleil sur la Garonne.",
        'cat'   => 'aperitif',
        'hood'  => 'capitole',
        'days_ahead' => 2,
        'hour'  => 19,
        'capacity' => 8,
        'host'  => 'lea',
        'rsvps' => ['lea', 'karim', 'sophie', 'hugo', 'camille', 'yann'],
    ],
    [
        'title' => 'Rando Pic du Midi (dimanche)',
        'desc'  => "Depart 8h en covoiturage depuis Toulouse direction le Pic du Midi. Marche niveau intermediaire (6h aller-retour, 800m D+). Prevoir chaussures de rando, eau (2L), pique-nique. Retour 19h. Place limitee : 12.",
        'cat'   => 'sport',
        'hood'  => 'saint-cyprien',
        'days_ahead' => 5,
        'hour'  => 8,
        'capacity' => 12,
        'host'  => 'karim',
        'rsvps' => ['karim', 'lea', 'sarah', 'marc', 'pierre'],
    ],
    [
        'title' => 'Soiree jeux de societe chez Ines',
        'desc'  => "Salon bien fourni : Catan, 7 Wonders, Codenames, Azul, Splendor. On commence par un jeu rapide, puis on enchaine. Apero partage (chacun amene un truc). 6 places max pour rester convivial.",
        'cat'   => 'jeux',
        'hood'  => 'carmes',
        'days_ahead' => 3,
        'hour'  => 20,
        'capacity' => 6,
        'host'  => 'ines',
        'rsvps' => ['ines', 'sophie', 'camille', 'hugo'],
    ],
    [
        'title' => 'Foot a 5 ce samedi matin',
        'desc'  => "Match amical au Five Soccer Atlanta (zone Atlanta, parking facile). Niveau detente, ambiance fair-play. Reservation deja faite (10 EUR a partager). 10 places, on splitte en deux equipes.",
        'cat'   => 'sport',
        'hood'  => 'minimes',
        'days_ahead' => 4,
        'hour'  => 10,
        'capacity' => 10,
        'host'  => 'hugo',
        'rsvps' => ['hugo', 'karim', 'marc', 'yann', 'pierre', 'ines', 'lea'],
    ],
    [
        'title' => 'Expo Toulouse-Lautrec aux Abattoirs + brunch',
        'desc'  => "Visite guidee de l\'expo Toulouse-Lautrec aux Abattoirs (1h30) puis brunch au cafe du musee. Tickets a prendre individuellement (14 EUR). Petit groupe pour echanger sereinement sur les oeuvres.",
        'cat'   => 'culturel',
        'hood'  => 'saint-michel',
        'days_ahead' => 4,
        'hour'  => 11,
        'capacity' => 4,
        'host'  => 'sophie',
        'rsvps' => ['sophie', 'camille', 'sarah'],
    ],
];

foreach ($events as $i => $e) {
    $h = $hoodIds[$e['hood']];
    $host = $findMember($e['host']);
    $startsAt = (new \DateTimeImmutable("+{$e['days_ahead']} days"))->setTime($e['hour'], 0)->format('Y-m-d H:i:s');
    $wkt = sprintf('POINT(%F %F)', $h['lng'] + 0.0008 * $i, $h['lat'] + 0.0008 * $i);

    // Verifie si event existe deja (par titre + host) pour upsert manuel
    $stmtCheck = $pdo->prepare("SELECT id FROM events WHERE title = ? AND host_user_id = ? LIMIT 1");
    $stmtCheck->execute([$e['title'], $host['id']]);
    $existingId = (int) ($stmtCheck->fetchColumn() ?: 0);

    if ($existingId) {
        // Update
        $pdo->prepare("UPDATE events SET description = ?, category = ?, starts_at = ?, capacity = ?, neighborhood_id = ?, city_id = ?, location = ST_GeomFromText(?, 0), status = 'published' WHERE id = ?")
            ->execute([$e['desc'], $e['cat'], $startsAt, $e['capacity'], $h['id'], $cityId, $wkt, $existingId]);
        $eventId = $existingId;
    } else {
        $pdo->prepare("INSERT INTO events (host_user_id, city_id, neighborhood_id, title, description, category, starts_at, capacity, is_sponsored, status, location) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0, 'published', ST_GeomFromText(?, 0))")
            ->execute([$host['id'], $cityId, $h['id'], $e['title'], $e['desc'], $e['cat'], $startsAt, $e['capacity'], $wkt]);
        $eventId = (int) $pdo->lastInsertId();
    }
    $report['events']++;

    // RSVPs
    foreach ($e['rsvps'] as $rsvpName) {
        $rsvpMember = $findMember($rsvpName);
        $pdo->prepare("INSERT INTO rsvps (event_id, user_id, status) VALUES (?, ?, 'going')
          ON DUPLICATE KEY UPDATE status=VALUES(status)")
            ->execute([$eventId, $rsvpMember['id']]);
        $report['rsvps']++;
    }
}

echo json_encode(['success' => true, 'seeded' => $report, 'time' => gmdate('Y-m-d H:i:s')], JSON_PRETTY_PRINT);
