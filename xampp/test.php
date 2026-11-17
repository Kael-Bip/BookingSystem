<?php

header('Content-Type: text/plain; charset=utf-8');

require __DIR__ . '/config.php';
$dbname = $database;

try {
    $conn = new PDO(
        "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4",
        $username,
        $password
    );

    $conn->setAttribute(
        PDO::ATTR_ERRMODE,
        PDO::ERRMODE_EXCEPTION
    );

    echo "DATABASE CONNECTED SUCCESSFULLY!\n\n";

    echo "Database: " . $dbname . "\n";
    echo "Port: " . $port . "\n\n";

    echo "Available Tables:\n";

    $tables = $conn->query("SHOW TABLES");

    while ($table = $tables->fetch(PDO::FETCH_NUM)) {
        echo $table[0] . "\n";
    }

} catch (PDOException $e) {
    echo "CONNECTION FAILED!\n\n";
    echo $e->getMessage();
}