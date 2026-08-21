<?php
namespace Book100\Core;

use PDO;

final class Database
{
    private static ?PDO $pdo = null;

    public static function pdo(): PDO
    {
        if (self::$pdo) return self::$pdo;
        $connection = Env::get('DB_CONNECTION', 'sqlite');
        if ($connection === 'mysql') {
            $host = Env::get('DB_HOST', '127.0.0.1');
            $port = Env::get('DB_PORT', '3306');
            $db = Env::get('DB_DATABASE', 'arka_shop');
            $dsn = "mysql:host={$host};port={$port};dbname={$db};charset=utf8mb4";
            self::$pdo = new PDO($dsn, Env::get('DB_USERNAME', 'root'), Env::get('DB_PASSWORD', ''), [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        } else {
            $path = dirname(__DIR__, 2) . '/' . Env::get('DB_DATABASE', 'storage/database.sqlite');
            if (!is_dir(dirname($path))) mkdir(dirname($path), 0775, true);
            self::$pdo = new PDO('sqlite:' . $path, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        }
        return self::$pdo;
    }
}
