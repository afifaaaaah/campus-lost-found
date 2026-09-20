<?php
// auth-check.php — the "bouncer" for protected pages.
// Any .php page that should only be visible to logged-in users starts
// with:   require 'auth-check.php';
// as its very first line (before any HTML is printed).

session_start();

if (!isset($_SESSION['user_id'])) {
    // Not logged in — send them to login instead of showing the page.
    header('Location: login.html');
    exit;
}
