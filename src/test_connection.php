
<?php
header('Content-Type: application/json');

$host = "127.0.0.1";
$port = "3306";
$username = "root";
$password = "";
$database = "staysync";

try {
    $conn = new PDO(
        "mysql:host=$host;port=$port;dbname=$database;charset=utf8mb4",
        $username,
        $password
    );

    $conn->setAttribute(
        PDO::ATTR_ERRMODE,
        PDO::ERRMODE_EXCEPTION
    );

    $tables = $conn->query("SHOW TABLES")
                   ->fetchAll(PDO::FETCH_COLUMN);

    $users = $conn->query("SELECT COUNT(*) FROM users")
                  ->fetchColumn();

    echo json_encode([
        "status" => "success",
        "message" => "Database connected successfully!",
        "database" => $database,
        "tables" => $tables,
        "total_users" => $users
    ], JSON_PRETTY_PRINT);

} catch (PDOException $e) {
    echo json_encode([
        "status" => "failed",
        "message" => $e->getMessage()
    ], JSON_PRETTY_PRINT);
}
?>