<?php
// logout.php — ends the session completely.

session_start();

// Clear all session variables, then destroy the session itself.
$_SESSION = [];
session_destroy();

header('Location: login.html?loggedout=1');
exit;