<?php
// db.php — the ONE place a database connection is created.
// Every other file does: require_once 'db.php';  and then uses $pdo.
//
// On XAMPP none of the environment variables below exist, so the defaults
// (127.0.0.1 / root / no password) are used. Under Docker, docker-compose.yml
// sets DB_HOST, DB_USER, etc., and the same code connects to the container.

$host   = getenv('DB_HOST') ?: '127.0.0.1';
$dbName = getenv('DB_NAME') ?: 'campus_lost_found';
$user   = getenv('DB_USER') ?: 'root';
$pass   = getenv('DB_PASS') ?: '';

try {
    $pdo = new PDO(
        "mysql:host=$host;dbname=$dbName;charset=utf8mb4",
        $user,
        $pass,
        [
            // Throw exceptions on SQL errors instead of failing silently
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            // Return rows as associative arrays: $row['email']
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            // Use REAL prepared statements (the database does the escaping)
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );
} catch (PDOException $e) {
    // Fine while learning. On a real server you would log this instead
    // of showing it to visitors.
    die('Database connection failed: ' . $e->getMessage());
}
