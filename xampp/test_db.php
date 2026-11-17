<?php
header('Content-Type: text/plain');

require __DIR__ . '/config.php';

try {
    $db = new PDO(
        "mysql:host=$host;port=$port;dbname=$database;charset=utf8mb4",
        $username,
        $password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
        ]
    );

    echo "Database connected successfully!\n\n";

    $tables = $db->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);

    echo "Available tables:\n";

    foreach ($tables as $table) {
        echo $table . "\n";
    }

    echo "\nInnoDB recovery mode:\n";
    $result = $db->query("SELECT @@innodb_force_recovery")->fetchColumn();
    echo $result;

} catch (Throwable $e) {
    echo "Connection failed:\n";
    echo $e->getMessage();
}
