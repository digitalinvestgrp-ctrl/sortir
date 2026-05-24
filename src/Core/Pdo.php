<?php
declare(strict_types=1);

namespace App\Core;

/**
 * PDO singleton — connexion MySQL trouvetateam
 * Pattern Agendia
 */
class Pdo
{
    private static ?\PDO $instance = null;

    public static function instance(): \PDO
    {
        if (self::$instance === null) {
            $cfg = Bootstrap::config('db');
            $dsn = sprintf(
                'mysql:host=%s;port=%d;dbname=%s;charset=%s',
                $cfg['host'],
                $cfg['port'],
                $cfg['name'],
                $cfg['charset']
            );
            self::$instance = new \PDO($dsn, $cfg['user'], $cfg['pass'], [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
                \PDO::ATTR_EMULATE_PREPARES => false,
                \PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci",
            ]);
        }
        return self::$instance;
    }

    /** Test only : permet d'injecter une PDO mock */
    public static function setInstance(?\PDO $pdo): void
    {
        self::$instance = $pdo;
    }
}
