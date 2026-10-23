<?php
// cleanup.php — OPTIONAL scheduled version of the automatic cleanup.
//
// The site already cleans up whenever the dashboard / admin page loads.
// If you would rather run it on a timer (a cron job on Linux/Docker, or
// Task Scheduler on Windows), run this from the COMMAND LINE:
//
//     php cleanup.php
//
// Example cron line (every day at 03:00):
//     0 3 * * * php /var/www/html/cleanup.php
//
// It refuses to run from a browser, so nobody can trigger it by URL.

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Run this script from the command line.');
}

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/db.php';

$removed = run_cleanup($pdo);
echo date('Y-m-d H:i:s') . " - removed $removed expired item(s)\n";
