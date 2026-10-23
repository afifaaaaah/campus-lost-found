<?php
// auth-check.php — the "bouncer" for protected pages.
// Put   require_once 'auth-check.php';   as the first line of any page
// that only logged-in users may see. It checks two things:
//   1. Is there a logged-in session at all?
//   2. Has the user been idle too long? (session timeout)

require_once 'helpers.php';

// 1. Not logged in -> go to the login page
if (!is_logged_in()) {
    flash_set('error', 'Please log in to continue.');
    redirect('login.php');
}

// 2. Idle for more than SESSION_TIMEOUT_SECONDS -> end the session
$idleSeconds = time() - ($_SESSION['last_activity'] ?? time());
if ($idleSeconds > SESSION_TIMEOUT_SECONDS) {
    session_unset();
    session_destroy();
    session_start();                 // fresh, empty session just to carry the message
    session_regenerate_id(true);
    flash_set('error', 'You were signed out after 30 minutes of inactivity. Please log in again.');
    redirect('login.php');
}

// Still active: reset the idle clock on every request
$_SESSION['last_activity'] = time();
