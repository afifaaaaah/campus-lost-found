<?php
// edit-item.php — lets a user edit ONLY their own report.
// GET: show the pre-filled form. POST: validate and update.

require 'auth-check.php';
require 'db.php';

$itemId = (int) ($_GET['item_id'] ?? $_POST['item_id'] ?? 0);

$stmt = $pdo->prepare('SELECT * FROM items WHERE item_id = ?');
$stmt->execute([$itemId]);
$item = $stmt->fetch();

if (!$item) {
    echo "That item doesn't exist. <a href='dashboard.php'>Back to dashboard</a>.";
    exit;
}

// Ownership check — this is the security-critical line in this whole file.
// Without it, any logged-in user could edit anyone else's report just by
// changing the item_id in the URL.
if ($item['user_id'] !== $_SESSION['user_id']) {
    echo "You can only edit your own reports. <a href='dashboard.php'>Back to dashboard</a>.";
    exit;
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $itemName    = trim($_POST['item_name'] ?? '');
    $category    = $_POST['category'] ?? '';
    $description = trim($_POST['description'] ?? '');
    $location    = trim($_POST['location'] ?? '');
    $itemDate    = $_POST['item_date'] ?? '';

    $allowedCategories = ['electronics', 'accessories', 'documents', 'bags', 'other'];

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

    if (empty($errors)) {
        // WHERE item_id = ? AND user_id = ? — the user_id check here is a
        // second, redundant safety net on top of the check above; even if
        // someone found a way around the first check, this query still
        // can't touch a row that isn't theirs.
        $updateStmt = $pdo->prepare(
            'UPDATE items SET item_name = ?, category = ?, description = ?, location = ?, item_date = ?
             WHERE item_id = ? AND user_id = ?'
        );
        $updateStmt->execute([
            $itemName, $category, $description, $location, $itemDate,
            $itemId, $_SESSION['user_id'],
        ]);

        header('Location: dashboard.php?updated=1');
        exit;
    }

    // Validation failed — keep the submitted values so the form doesn't
    // clear itself, by overwriting $item with what was just typed.
    $item['item_name']   = $itemName;
    $item['category']    = $category;
    $item['description'] = $description;
    $item['location']    = $location;
    $item['item_date']   = $itemDate;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Edit report — Campus Lost &amp; Found</title>
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link
    href="https://fonts.googleapis.com/css2?family=Fraunces:wght@500;600&family=Inter:wght@400;500;600;700&display=swap"
    rel="stylesheet"
  />
  <link rel="stylesheet" href="css/style.css" />
</head>
<body>

  <header class="site-nav">
    <div class="container">
      <a class="brand" href="index.html">Campus Lost &amp; Found <span>desk</span></a>
      <button class="nav-toggle" aria-label="Toggle navigation menu" aria-expanded="false">&#9776;</button>
      <ul class="nav-links">
        <li><a href="index.html">Home</a></li>
        <li><a href="dashboard.php">Browse items</a></li>
        <li><a href="report-item.php">Report an item</a></li>
        <li><a href="logout.php">Log out</a></li>
      </ul>
    </div>
  </header>

  <section class="section">
    <div class="container">

      <div class="form-card" style="max-width:600px;">
        <h2>Edit your report</h2>

        <?php if (!empty($errors)): ?>
          <div class="alert alert-error">
            <?php echo implode('<br>', array_map('htmlspecialchars', $errors)); ?>
          </div>
        <?php endif; ?>

        <p class="text-muted" style="margin-bottom:1.5rem;">
          Reporting as: <strong><?php echo htmlspecialchars($item['item_type']); ?></strong>
          (the lost/found type can't be changed after reporting)
        </p>

        <form method="post" action="edit-item.php">
          <input type="hidden" name="item_id" value="<?php echo (int) $item['item_id']; ?>" />

          <div class="form-field">
            <label for="item_name">Item name</label>
            <input type="text" id="item_name" name="item_name"
              value="<?php echo htmlspecialchars($item['item_name']); ?>" required />
          </div>

          <div class="form-row form-row-2">
            <div class="form-field">
              <label for="category">Category</label>
              <select id="category" name="category" required>
                <?php
                $categories = [
                    'electronics'  => 'Electronics',
                    'accessories'  => 'Accessories',
                    'documents'    => 'Documents / ID cards',
                    'bags'         => 'Bags',
                    'other'        => 'Other',
                ];
                foreach ($categories as $value => $label) {
                    $selected = ($item['category'] === $value) ? 'selected' : '';
                    echo "<option value=\"$value\" $selected>$label</option>";
                }
                ?>
              </select>
            </div>

            <div class="form-field">
              <label for="item_date">Date</label>
              <input type="date" id="item_date" name="item_date"
                value="<?php echo htmlspecialchars($item['item_date']); ?>" required />
            </div>
          </div>

          <div class="form-field">
            <label for="location">Location</label>
            <input type="text" id="location" name="location"
              value="<?php echo htmlspecialchars($item['location']); ?>" required />
          </div>

          <div class="form-field">
            <label for="description">Description</label>
            <textarea id="description" name="description" required><?php echo htmlspecialchars($item['description']); ?></textarea>
          </div>

          <button type="submit" class="btn btn-primary btn-block">Save changes</button>
        </form>
      </div>

    </div>
  </section>

  <footer class="site-footer">
    <div class="container">
      <span>&copy; 2026 Campus Lost &amp; Found</span>
      <span>Built as a student systems project</span>
    </div>
  </footer>

  <script src="js/main.js"></script>
</body>
</html>