<?php
// report-item-save.php — receives the report-item.php form.
// Handles BOTH lost and found reports, since they share one table.

require 'auth-check.php';
require 'db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: report-item.php');
    exit;
}

// --- Collect input ---
$reportType  = $_POST['report_type'] ?? '';
$itemName    = trim($_POST['item_name'] ?? '');
$category    = $_POST['category'] ?? '';
$description = trim($_POST['description'] ?? '');
$location    = trim($_POST['location'] ?? '');
$itemDate    = $_POST['item_date'] ?? '';

$allowedTypes      = ['lost', 'found'];
$allowedCategories = ['electronics', 'accessories', 'documents', 'bags', 'other'];

$errors = [];

// --- Server-side validation, mirroring the client-side checks ---
if (!in_array($reportType, $allowedTypes, true)) {
    $errors[] = 'Choose whether this is a lost or found report.';
}
if (strlen($itemName) < 3) {
    $errors[] = 'Give the item a short, specific name.';
}
if (!in_array($category, $allowedCategories, true)) {
    $errors[] = 'Choose a valid category.';
}
if (strlen($description) < 10) {
    $errors[] = 'Add a few more details in the description.';
}
if (strlen($location) < 2) {
    $errors[] = 'Tell us roughly where this happened.';
}
if ($itemDate === '' || strtotime($itemDate) > time()) {
    $errors[] = 'Choose a valid date that is not in the future.';
}

// --- Image upload is OPTIONAL. We only validate it if one was provided. ---
$imagePath = null; // stays NULL if no image is uploaded — that's fine

if (isset($_FILES['item_image']) && $_FILES['item_image']['error'] !== UPLOAD_ERR_NO_FILE) {

    // UPLOAD_ERR_NO_FILE means the field was left empty — nothing to do.
    // Any other error code (or a real file) needs checking.
    if ($_FILES['item_image']['error'] !== UPLOAD_ERR_OK) {
        $errors[] = 'There was a problem uploading the image. Try again.';
    } else {
        $maxSizeInBytes = 3 * 1024 * 1024; // 3 MB
        $allowedMimeTypes = ['image/jpeg', 'image/png', 'image/webp'];

        // Never trust the file extension or the browser-reported MIME type —
        // finfo reads the actual file content to determine its real type.
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $actualMimeType = $finfo->file($_FILES['item_image']['tmp_name']);

        if (!in_array($actualMimeType, $allowedMimeTypes, true)) {
            $errors[] = 'Upload a JPG, PNG or WEBP image.';
        } elseif ($_FILES['item_image']['size'] > $maxSizeInBytes) {
            $errors[] = 'Image must be smaller than 3 MB.';
        }
    }
}

if (!empty($errors)) {
    echo implode('<br>', $errors);
    echo '<br><a href="report-item.php">Go back</a>';
    exit;
}

// --- If an image passed validation, move it into /uploads with a unique name ---
if (isset($_FILES['item_image']) && $_FILES['item_image']['error'] === UPLOAD_ERR_OK) {
    $uploadsDir = __DIR__ . '/uploads';

    if (!is_dir($uploadsDir)) {
        mkdir($uploadsDir, 0755, true);
    }

    // uniqid() avoids overwriting another user's file that happens to have
    // the same original filename (e.g. two people both upload "photo.jpg").
    $extension = strtolower(pathinfo($_FILES['item_image']['name'], PATHINFO_EXTENSION));
    $newFilename = 'item_' . uniqid() . '.' . $extension;

    move_uploaded_file($_FILES['item_image']['tmp_name'], $uploadsDir . '/' . $newFilename);

    $imagePath = 'uploads/' . $newFilename; // this is what gets stored in the DB
}

// --- Insert the new item, owned by whoever is currently logged in ---
$stmt = $pdo->prepare(
    'INSERT INTO items (user_id, item_type, item_name, category, description, location, item_date, image_path, status)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
);
$stmt->execute([
    $_SESSION['user_id'],
    $reportType,
    $itemName,
    $category,
    $description,
    $location,
    $itemDate,
    $imagePath,
    'active',
]);

header('Location: dashboard.php?reported=1');
exit;   