<?php
// admin-check.php — the bouncer for ADMIN-only pages.
// Runs the normal login check first, then asks the DATABASE (not the
// session) whether this user is currently an admin. Checking the database
// means a demoted admin loses access immediately, even mid-session.

require_once 'auth-check.php';
require_once 'db.php';

$roleStmt = $pdo->prepare('SELECT role FROM users WHERE user_id = ?');
$roleStmt->execute([current_user_id()]);
$currentRole = $roleStmt->fetchColumn();

if ($currentRole !== 'admin') {
    flash_set('error', 'That page is for admins only.');
    redirect('dashboard.php');
}
$_SESSION['role'] = 'admin';
