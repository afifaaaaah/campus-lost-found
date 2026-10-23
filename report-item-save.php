<?php
// report-item-save.php — receives the form from report-item.php.
// Handles BOTH lost and found reports, since they share one table.

require_once 'auth-check.php';
require_once 'db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('report-item.php');
}
verify_csrf();

$reportType  = post_value('report_type');
$itemName    = post_value('item_name');
$category    = post_value('category');
$description = post_value('description');
$location    = post_value('location');
$itemDate    = post_value('item_date');

// Validate the text fields first (rules live in helpers.php)
$errors = validate_item_input($itemName, $category, $description, $location, $itemDate);
if (!in_array($reportType, ['lost', 'found'], true)) {
    $errors[] = 'Choose whether this is a lost or found report.';
}

// The photo is OPTIONAL. We only look at it once the rest is valid, so a
// failed form never leaves an orphan file on disk.
$imagePath = null;
if (empty($errors)) {
    $imagePath = process_image_upload('item_image', $errors);   // null if no photo
}

if (!empty($errors)) {
    flash_errors($errors);
    remember_input([
        'report_type' => $reportType, 'item_name' => $itemName, 'category' => $category,
        'description' => $description, 'location' => $location, 'item_date' => $itemDate,
    ]);
    redirect('report-item.php');
}

$stmt = $pdo->prepare(
    'INSERT INTO items (user_id, item_type, item_name, category, description, location, item_date, image_path, status)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
);
$stmt->execute([
    current_user_id(), $reportType, $itemName, $category,
    $description, $location, $itemDate, $imagePath, 'active',
]);

flash_set('success', 'Your report was submitted.');
redirect('dashboard.php');
