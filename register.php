<?php
// register.php — receives the register.html form submission.
// This is the REAL security boundary. The JavaScript in js/validation.js
// only improves the user's experience; a user can disable JS entirely and
// submit straight to this file, so every check has to happen again here.

session_start();
require 'db.php';

// Only ever process an actual form submission, never a direct page visit.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: register.html');
    exit;
}

// --- Collect and trim input. The ?? '' means "use an empty string if this
// field wasn't sent at all", so a missing field never causes a PHP warning. ---
$fullName        = trim($_POST['full_name'] ?? '');
$email           = trim($_POST['email'] ?? '');
$phone           = trim($_POST['phone'] ?? '');
$password        = $_POST['password'] ?? '';
$confirmPassword = $_POST['confirm_password'] ?? '';

$errors = [];

// --- Server-side validation (mirrors the JS rules, on purpose) ---
if (strlen($fullName) < 2) {
    $errors[] = 'Please enter your full name.';
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'Enter a valid email address.';
}

if (strlen($password) < 8 || !preg_match('/[A-Za-z]/', $password) || !preg_match('/\d/', $password)) {
    $errors[] = 'Password needs at least 8 characters, including a letter and a number.';
}

if ($password !== $confirmPassword) {
    $errors[] = 'Passwords do not match.';
}

if (!empty($errors)) {
    // Simple output for now — later this can redirect back to register.html
    // with the messages passed along, instead of a plain echo.
    echo implode('<br>', $errors);
    echo '<br><a href="register.html">Go back</a>';
    exit;
}

// --- Reject duplicate emails before touching the password at all ---
$checkStmt = $pdo->prepare('SELECT user_id FROM users WHERE email = ?');
$checkStmt->execute([$email]);

if ($checkStmt->fetch()) {
    echo "An account with this email already exists. <a href='login.html'>Log in instead</a>.";
    exit;
}

// --- Hash the password. This is the ONLY form the password ever takes
// once it leaves this script — the plain text is never stored anywhere. ---
$passwordHash = password_hash($password, PASSWORD_DEFAULT);

// --- Insert the new user as a normal 'student' role ---
$insertStmt = $pdo->prepare(
    'INSERT INTO users (full_name, email, password, phone, role) VALUES (?, ?, ?, ?, ?)'
);
$insertStmt->execute([
    $fullName,
    $email,
    $passwordHash,
    $phone !== '' ? $phone : null,
    'student',
]);

// --- Deliberately NOT logging them in here. They must log in separately. ---
header('Location: login.html?registered=1');
exit;