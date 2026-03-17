<?php
// core/Database.php

class Database {
    protected static $pdo;

    public static function connect($config) {
    

	    if (!self::$pdo) {
            $dsn = "mysql:host={$config['host']};dbname={$config['name']};charset={$config['charset']}";
            self::$pdo = new PDO($dsn, $config['user'], $config['pass']);
            self::$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        }
        return self::$pdo;
    }
}

