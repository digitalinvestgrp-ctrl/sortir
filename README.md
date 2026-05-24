# Trouvetateam

Venture **sortir** (sorties / rencontres / evenements locaux) portee de Laravel 11 + PostgreSQL/PostGIS vers **PHP natif + MySQL 8 SPATIAL** pour deploiement sur OVH mutualise.

## Stack

- **Backend** : PHP 8.1+ natif, PSR-4 autoload, PDO MySQL
- **BDD** : MySQL 8.0 (OVH CloudDB cluster `cs1023012-002`), base `trouvetateam`
- **Hosting** : OVH mutualise `cluster030`, dossier `/ttt/`, multisite -> `/ttt/public/`
- **Domaines** : `trouvetateam.fr` + `trouvetateam.com` (a acheter chez OVH)
- **SMS** : driver mock `log` par defaut (gratuit, regle DIG anti-cout)
- **Auth** : Bearer tokens custom (table `api_tokens`, SHA256 hash), remplace Sanctum
- **Geo** : `POINT SRID 0` + `ST_Distance_Sphere` + bounding box `MBRContains` (perf)
- **Tests** : PHPUnit 10
- **Deploy** : script Python `_alex_ftp_deploy.py` (upload selectif FTP)

## Architecture

```
trouvetateam/
├── public/
│   ├── index.php           Front controller + router
│   ├── healthz.php         Monitoring endpoint
│   └── .htaccess           Rewrite + securite + CORS
├── src/
│   ├── Core/               Bootstrap, Pdo, Router, Request, Response, Validator, AuthMiddleware, Logger
│   ├── Controller/         Health, Auth, Discovery, PhoneVerification, Trust
│   ├── Model/              UserRepository, EventRepository, ... (11 repos PDO)
│   └── Service/            TokenService, PhoneVerificationService, Geo/, Sms/
├── config/
│   └── app.php             Config centrale (lit .env)
├── migrations/
│   ├── 001 a 010 .sql      Schema MySQL 8 spatial
│   ├── run.php             Runner one-shot (token-protected)
│   └── seed_toulouse.php   Seeder ville pilote
├── tests/
│   ├── Unit/               GeoPrivacy, Validator
│   └── Feature/            (a etoffer via CI MySQL)
├── .github/workflows/
│   └── ci.yml              CI MySQL + phpunit
└── _alex_ftp_deploy.py     Deploy script FTP
```

## Endpoints API (14)

### Libres (sans auth)
- `GET  /api/health`
- `POST /api/auth/register` (gate 18+)
- `POST /api/auth/login`
- `GET  /api/discovery/nearby?lat&lng&radius&category`
- `GET  /api/discovery/neighborhood/{slug}?city=toulouse`
- `GET  /api/cities/{slug}/neighborhoods`

### Authentifies (Bearer token)
- `GET  /api/auth/me`
- `POST /api/auth/logout`
- `POST /api/phone/request`
- `POST /api/phone/confirm`
- `POST /api/trust/report`
- `POST /api/trust/block`
- `POST /api/trust/unblock`

### Healthz (hors `/api`)
- `GET  /healthz.php` : statut DB + PHP version + SMS driver + min_age

## Mapping PostGIS -> MySQL spatial

| PostGIS source | MySQL cible |
|----------------|-------------|
| `geography(Point, 4326)` | `POINT NOT NULL SRID 0` (convention projet : POINT(lng, lat)) |
| `CREATE INDEX ... USING GIST` | `SPATIAL INDEX (col)` |
| `ST_SetSRID(ST_MakePoint(lng, lat), 4326)::geography` | `ST_GeomFromText('POINT(lng lat)', 0)` |
| `ST_DWithin(geog1, geog2, meters)` | `ST_Distance_Sphere(geom1, geom2) <= meters` (pre-filtre BBox `MBRContains` pour perf) |
| `ST_Distance(geog1, geog2)` | `ST_Distance_Sphere(geom1, geom2)` |
| `ST_X(geom)`, `ST_Y(geom)` | Identique |

Note SRID : on a choisi `SRID 0` (pas de contrainte axis order), POINT(lng, lat) cote stockage,
`ST_Distance_Sphere` qui calcule en metres (Haversine pur). Permet de contourner les questions d'axis
order MySQL 8.0.12+ pour SRID 4326.

## Procedure deploy V1

### Pre-requis externes (a faire dans Manager OVH par Stephane / blocages)

1. Acheter `trouvetateam.fr` + `trouvetateam.com` (~12 EUR / an chacun)
2. Multisite OVH : ajouter les 2 domaines, doc root = `/ttt/public/`
3. SSL Let's Encrypt sur les 2 domaines (post DNS propagation)
4. GRANT `safeprotek` sur BDD `trouvetateam` (ou creer user dedie)
5. Creer repo GitHub `digitalinvestgrp-ctrl/trouvetateam` (PAT scope insuffisant pour creation auto)

### Pre-requis internes (faisables agents)

- `/ttt/` + sous-dossiers : OK (Marcus, 2026-05-24)
- `.env` prod : modele `.env.example` -> a personnaliser + uploader `/ttt/.env`
- `composer install` en local (vendor pas commite -> uploader vendor/ via deploy script)

### Deploy

```bash
# 1. Build vendor en local
composer install --no-dev --optimize-autoloader

# 2. Cree .env local (copier .env.example, remplir APP_KEY + DB_PASS + MIGRATIONS_TOKEN)
cp .env.example .env
php -r 'echo "APP_KEY=" . bin2hex(random_bytes(32));'

# 3. Upload FTP
python _alex_ftp_deploy.py

# 4. Upload .env separement (jamais via git ni script auto)
curl -T .env -u cijufrg:170289aB ftp://ftp.cluster030.hosting.ovh.net/ttt/.env

# 5. Une fois multisite + SSL OK, lancer migrations via HTTPS
curl "https://www.trouvetateam.fr/migrations/run.php?token=<MIGRATIONS_TOKEN>"

# 6. Seed Toulouse
curl "https://www.trouvetateam.fr/migrations/seed_toulouse.php?token=<MIGRATIONS_TOKEN>"

# 7. Verifier healthz
curl https://www.trouvetateam.fr/healthz.php

# 8. SUPPRIMER migrations/run.php + seed_toulouse.php apres usage (securite)
```

## Tests locaux

```bash
composer install
vendor/bin/phpunit
```

CI MySQL automatique sur push main / PR via `.github/workflows/ci.yml`.

## Regles DIG respectees

- Pas de service payant sans GO Stephane explicite (SMS driver = mock log)
- Pas de SRID/PostGIS hors-stack OVH (MySQL spatial natif uniquement)
- `.env` non commite (`.gitignore`)
- Tokens BDD-stockes en SHA256 hash (jamais plain text)
- Gate 18+ a l'inscription (veto Tomas / sortir)
- GeoPrivacy : pas de GPS exact pour les membres (rattachement quartier)
- Migrations idempotentes + log de tracking + token de garde
- Scripts one-shot supprimes apres usage (securite, pattern Jurenys)
