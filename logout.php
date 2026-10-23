<?php
// logout.php — ends the session completely.

require_once 'helpers.php';

session_unset();        // forget everything stored in $_SESSION
session_destroy();      // delete the session on the server

// Start a brand-new empty session just to carry the "signed out" message
session_start();
session_regenerate_id(true);
flash_set('success', 'You have been signed out.');

redirect('login.php');
