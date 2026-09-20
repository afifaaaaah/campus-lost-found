<?php
// login.php — receives the login.html form submission.
// On success, this is where a PHP session actually begins.

session_start();
require 'db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: login.html');
    exit;
}

$email    = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';

if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $password === '') {
    echo "Enter a valid email and password. <a href='login.html'>Try again</a>.";
    exit;
}

$stmt = $pdo->prepare('SELECT user_id, full_name, password, role FROM users WHERE email = ?');
$stmt->execute([$email]);
$user = $stmt->fetch();

// password_verify() compares the typed password against the stored hash.
// We deliberately give the SAME error message whether the email doesn't
// exist or the password is wrong — this stops an attacker from using the
// error message to figure out which registered emails exist.
if (!$user || !password_verify($password, $user['password'])) {
    echo "Incorrect email or password. <a href='login.html'>Try again</a>.";
    exit;
}

// Regenerate the session ID on every successful login. This prevents
// "session fixation" — where an attacker tricks a victim into using a
// session ID the attacker already knows, then logging in makes it valid.
session_regenerate_id(true);

// Everything a protected page needs to know about who is logged in.
$_SESSION['user_id']   = $user['user_id'];
$_SESSION['full_name'] = $user['full_name'];
$_SESSION['role']      = $user['role'];

header('Location: dashboard.php');
exit;