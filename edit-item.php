<?php
// edit-item.php — edit ONE of your own reports.
//   GET  -> show the form, pre-filled
//   POST -> validate and save (optionally adding / replacing the photo)

require_once 'auth-check.php';
require_once 'db.php';

$itemId = (int) ($_GET['item_id'] ?? $_POST['item_id'] ?? 0);

$stmt = $pdo->prepare('SELECT * FROM items WHERE item_id = ?');
$stmt->execute([$itemId]);
$item = $stmt->fetch();

// Ownership check — the security-critical line of this file. Without it,
// anyone could edit anyone's report by changing item_id in the URL.
// (One message for "doesn't exist" and "not yours" so we leak nothing.)
if (!$item || (int) $item['user_id'] !== current_user_id()) {
    flash_set('error', "That report wasn't found, or it isn't yours.");
    redirect('dashboard.php');
}

// Once a claim is in progress (or it's resolved) the report is frozen,
// so the details can't change under the admin's feet.
if ($item['status'] !== 'active') {
    flash_set('error', 'This report can no longer be edited because a claim is in progress or it has ended.');
    redirect('dashboard.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $itemName    = post_value('item_name');
    $category    = post_value('category');
    $description = post_value('description');
    $location    = post_value('location');
    $itemDate    = post_value('item_date');

    $errors   = validate_item_input($itemName, $category, $description, $location, $itemDate);
    $newImage = null;
    if (empty($errors)) {
        $newImage = process_image_upload('item_image', $errors);
    }

    if (!empty($errors)) {
        flash_errors($errors);
        remember_input([
            'item_name' => $itemName, 'category' => $category, 'description' => $description,
            'location' => $location, 'item_date' => $itemDate,
        ]);
        redirect('edit-item.php?item_id=' . $itemId);
    }

    // user_id in the WHERE clause is a second, independent ownership guard.
    if ($newImage !== null) {
        $update = $pdo->prepare(
            "UPDATE items SET item_name = ?, category = ?, description = ?, location = ?, item_date = ?, image_path = ?
             WHERE item_id = ? AND user_id = ? AND status = 'active'"
        );
        $update->execute([$itemName, $category, $description, $location, $itemDate, $newImage, $itemId, current_user_id()]);
        delete_image_file($item['image_path']);          // the replaced photo is no longer needed
    } else {
        $update = $pdo->prepare(
            "UPDATE items SET item_name = ?, category = ?, description = ?, location = ?, item_date = ?
             WHERE item_id = ? AND user_id = ? AND status = 'active'"
        );
        $update->execute([$itemName, $category, $description, $location, $itemDate, $itemId, current_user_id()]);
    }

    flash_set('success', 'Your report was updated.');
    redirect('dashboard.php');
}

// Show the form. If the last attempt failed, typed values win over saved ones.
$values     = array_merge($item, pull_old());
$pageTitle  = 'Edit report';
$activePage = '';
require 'header.php';
?>

  <section class="section">
    <div class="container">

      <div class="form-card" style="max-width:600px;">
        <h2>Edit your report</h2>
        <p class="text-muted" style="margin-bottom:1.5rem;">
          Reporting as: <strong><?php echo h(ucfirst($item['item_type'])); ?></strong>
          (the lost/found type can&rsquo;t be changed after reporting).
        </p>

        <form action="edit-item.php" method="post" enctype="multipart/form-data">
          <?php echo csrf_field(); ?>
          <input type="hidden" name="item_id" value="<?php echo (int) $item['item_id']; ?>" />

          <div class="form-field">
            <label for="item_name">Item name</label>
            <input type="text" id="item_name" name="item_name" value="<?php echo h($values['item_name']); ?>" required />
          </div>

          <div class="form-row form-row-2">
            <div class="form-field">
              <label for="category">Category</label>
              <select id="category" name="category" required>
                <?php foreach (CATEGORIES as $value => $label): ?>
                  <option value="<?php echo h($value); ?>" <?php echo $values['category'] === $value ? 'selected' : ''; ?>>
                    <?php echo h($label); ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="form-field">
              <label for="item_date">Date</label>
              <input type="date" id="item_date" name="item_date" max="<?php echo date('Y-m-d'); ?>"
                     value="<?php echo h($values['item_date']); ?>" required />
            </div>
          </div>

          <div class="form-field">
            <label for="location">Location</label>
            <input type="text" id="location" name="location" value="<?php echo h($values['location']); ?>" required />
          </div>

          <div class="form-field">
            <label for="description">Description</label>
            <textarea id="description" name="description" required><?php echo h($values['description']); ?></textarea>
          </div>

          <div class="form-field">
            <?php if ($item['image_path']): ?>
              <label>Current photo</label>
              <img class="photo-preview" src="<?php echo h($item['image_path']); ?>" alt="Photo of <?php echo h($item['item_name']); ?>" />
              <label for="item_image">Replace photo <span class="text-muted">(optional)</span></label>
            <?php else: ?>
              <label for="item_image">Add a photo <span class="text-muted">(optional)</span></label>
            <?php endif; ?>
            <input type="file" id="item_image" name="item_image" accept="image/jpeg,image/png,image/webp" />
            <p class="hint">JPG, PNG or WEBP, up to 3 MB.</p>
          </div>

          <button type="submit" class="btn btn-primary btn-block">Save changes</button>
          <p style="margin-top:1rem; text-align:center;"><a href="dashboard.php#my-reports">Cancel</a></p>
        </form>
      </div>

    </div>
  </section>

<?php require 'footer.php'; ?>
