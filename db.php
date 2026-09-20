<?php
// db.php — the ONE place a database connection is created.
// Every other PHP file does require 'db.php'; and then uses $pdo.
// Never open a second connection anywhere else in the project.

$host   = '127.0.0.1';
$dbName = 'campus_lost_found';
$user   = 'root';
$pass   = '';   // XAMPP's default root password is empty

try {
    $pdo = new PDO(
        "mysql:host=$host;dbname=$dbName;charset=utf8mb4",
        $user,
        $pass,
        [
            // Throw exceptions on error instead of failing silently
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            // Return rows as associative arrays ($row['email']) by default
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );
} catch (PDOException $e) {
    // In a real deployment you would log this instead of showing it —
    // for now, showing it helps you debug setup problems.
    die('Database connection failed: ' . $e->getMessage());
}