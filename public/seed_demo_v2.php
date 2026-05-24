<?php
/**
 * Endpoint public d'execution du seed_demo_v2.
 *
 * Le routing OVH mutu rewrite /migrations/* vers /public/migrations/* (qui n'existe pas).
 * On expose donc le seed via /public/seed_demo_v2.php (accessible via /seed_demo_v2.php).
 *
 * Token MIGRATIONS_TOKEN requis. Idempotent.
 * A supprimer apres usage (one-shot demo).
 */
require __DIR__ . '/../migrations/seed_demo_v2.php';
