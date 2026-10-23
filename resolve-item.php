<?php
// resolve-item.php — the owner of a LOST report clicks "I got it back".
// The item becomes 'resolved'; the cleanup rule then removes it after 10 days.

require_once 'auth-check.php';
require_once 'db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('dashboard.php');
}
verify_csrf();

$itemId = (int) ($_POST['item_id'] ?? 0);

// Everything is in the WHERE clause: it must be yours, it must be a lost
// report, and it must still be active. Anything else updates 0 rows.
$update = $pdo->prepare(
    "UPDATE items SET status = 'resolved', resolved_at = NOW()
     WHERE item_id = ? AND user_id = ? AND item_type = 'lost' AND status = 'active'"
);
$update->execute([$itemId, current_user_id()]);

if ($update->rowCount() === 1) {
    flash_set('success', 'Great news! Your report is marked as resolved and will be removed in ' . CLEANUP_RESOLVED_DAYS . ' days.');
} else {
    flash_set('error', "That report couldn't be marked as resolved.");
}

redirect('dashboard.php');
