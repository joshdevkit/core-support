<?php

namespace Core;

class Database
{
    private static $instance = null;
    private $pdo;

    private function __construct()
    {
        $dbHost = env('DB_HOST', 'localhost');
        $dbUser = env('DB_USER', 'root');
        $dbPass = env('DB_PASSWORD', '');
        $dbName = env('DB_DATABASE');

        $dsn = "mysql:host={$dbHost};dbname={$dbName};charset=utf8mb4";

        $options = [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
            \PDO::ATTR_EMULATE_PREPARES => false,
        ];

        try {
            $this->pdo = new \PDO($dsn, $dbUser, $dbPass, $options);
        } catch (\PDOException $e) {
            throw new \Exception("Database connection failed: " . $e->getMessage());
        }
    }

    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance->pdo;
    }

    // Prevent cloning of the instance
    public function __clone()
    {
        throw new \Exception("Cloning a singleton is not allowed");
    }

    // Prevent unserializing of the instance
    public function __wakeup()
    {
        throw new \Exception("Unserializing a singleton is not allowed");
    }
}
