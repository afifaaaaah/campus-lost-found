<?php
/**
 * =============================================================================
 * DATABASE CONNECTION — the ONE place a PDO connection is created.
 * =============================================================================
 * Every other PHP file that needs the database does:
 *
 *     require __DIR__ . '/../config/db.php';
 *
 * ...and then uses the $pdo variable this file creates. Never open a second
 * PDO connection somewhere else — one shared connection per request is the
 * correct pattern.
 * =============================================================================
 */

// --- Connection details ------------------------------------------------
// These match XAMPP's defaults. If you're running MySQL differently,
// change these four lines only — nothing else in the project needs to know.
$host    = '127.0.0.1';
$port    = '3306';
$dbName  = 'campus_lost_found';
$dbUser  = 'root';
$dbPass  = '';           // XAMPP's default MySQL root password is blank
$charset = 'utf8mb4';

// DSN = "Data Source Name" — a single string describing how to connect
$dsn = "mysql:host=$host;port=$port;dbname=$dbName;charset=$charset";

// --- Connection options --------------------------------------------------
$options = [
    // Throw a real PHP exception on any database error, instead of silently
    // failing or returning false. This makes bugs obvious instead of hidden.
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,

    // Return each row as an associative array, e.g. $row['item_name'],
    // instead of a numbered array or an object.
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,

    // Use real prepared statements sent to MySQL, rather than PHP faking
    // them — slightly safer and more accurate with data types.
    PDO::ATTR_EMULATE_PREPARES => false,
];

// --- Create the connection ------------------------------------------------
try {
    $pdo = new PDO($dsn, $dbUser, $dbPass, $options);
} catch (PDOException $e) {
    // In a real deployed app you would log this instead of showing it —
    // but while you're building and learning, seeing the real error is
    // more useful than a vague "something went wrong" message.
    http_response_code(500);
    die('Database connection failed: ' . $e->getMessage());
}