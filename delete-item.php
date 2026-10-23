<?php
// delete-item.php — delete ONE of your own reports.
// POST only (never GET) and CSRF-protected, so a stray link or a malicious
// web page can never trigger a delete.

require_once 'auth-check.php';
require_once 'db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('dashboard.php');
}
verify_csrf();

$itemId = (int) ($_POST['item_id'] ?? 0);

$stmt = $pdo->prepare('SELECT * FROM items WHERE item_id = ?');
$stmt->execute([$itemId]);
$item = $stmt->fetch();

// Allowed only for your own item, and not while a claim is being handled
// (status matched / claimed) — the admin owns it during that window.
$canDelete = $item
    && (int) $item['user_id'] === current_user_id()
    && in_array($item['status'], ['active', 'resolved', 'closed'], true);

if ($canDelete) {
    // user_id in the WHERE clause = second, independent ownership guard
    $delete = $pdo->prepare('DELETE FROM items WHERE item_id = ? AND user_id = ?');
    $delete->execute([$itemId, current_user_id()]);

    if ($delete->rowCount() === 1) {
        delete_image_file($item['image_path']);     // tidy up the photo too
        flash_set('success', 'Your report was deleted.');
    }
} else {
    flash_set('error', "That report can't be deleted right now. If a claim is in progress, the desk is handling it.");
}

redirect('dashboard.php');
