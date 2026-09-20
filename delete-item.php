<?php
// delete-item.php — deletes ONLY the current user's own item.
// Only accepts POST, never GET, so a stray link or crawler can never
// trigger a delete just by being visited.

require 'auth-check.php';
require 'db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: dashboard.php');
    exit;
}

$itemId = (int) ($_POST['item_id'] ?? 0);

// Fetch first so we can check ownership AND get the image path to delete.
$stmt = $pdo->prepare('SELECT * FROM items WHERE item_id = ?');
$stmt->execute([$itemId]);
$item = $stmt->fetch();

if ($item && $item['user_id'] === $_SESSION['user_id']) {

    // Delete the row. Any claims pointing at it are removed automatically
    // by the ON DELETE CASCADE foreign key we set up in the schema.
    $deleteStmt = $pdo->prepare('DELETE FROM items WHERE item_id = ? AND user_id = ?');
    $deleteStmt->execute([$itemId, $_SESSION['user_id']]);

    // Clean up the uploaded image file too, if there was one, so we don't
    // accumulate orphaned files with no database row pointing to them.
    if ($item['image_path'] && file_exists(__DIR__ . '/' . $item['image_path'])) {
        unlink(__DIR__ . '/' . $item['image_path']);
    }
}
// If the item doesn't exist or isn't theirs, we silently do nothing —
// no error message needed, the end result (item not in their list) is the same.

header('Location: dashboard.php?deleted=1');
exit;