<?php
require_once 'auth-check.php';

$old        = pull_old();                         // what they typed if the last attempt failed
$oldType    = $old['report_type'] ?? 'lost';
$pageTitle  = 'Report an item';
$activePage = 'report';
require 'header.php';
?>

  <section class="section">
    <div class="container">

      <div class="form-card" style="max-width:600px;">
        <h2>Report an item</h2>
        <p class="text-muted" style="margin-bottom:1.5rem;">
          Use the same form whether you lost something or found something &mdash;
          just tell us which below.
        </p>

        <form id="report-form" action="report-item-save.php" method="post" enctype="multipart/form-data" novalidate>
          <?php echo csrf_field(); ?>

          <div class="form-field">
            <label>What are you reporting?</label>
            <div class="form-row form-row-2">
              <label style="font-weight:400; display:flex; align-items:center; gap:0.5rem;">
                <input type="radio" name="report_type" value="lost"
                       <?php echo $oldType === 'lost' ? 'checked' : ''; ?> style="width:auto;" />
                I lost an item
              </label>
              <label style="font-weight:400; display:flex; align-items:center; gap:0.5rem;">
                <input type="radio" name="report_type" value="found"
                       <?php echo $oldType === 'found' ? 'checked' : ''; ?> style="width:auto;" />
                I found an item
              </label>
            </div>
          </div>

          <div class="form-field" id="field-item-name">
            <label for="item_name">Item name</label>
            <input type="text" id="item_name" name="item_name" placeholder="e.g. Black leather wallet"
                   value="<?php echo h($old['item_name'] ?? ''); ?>" required />
            <p class="error-message" role="alert"></p>
          </div>

          <div class="form-row form-row-2">
            <div class="form-field" id="field-category">
              <label for="category">Category</label>
              <select id="category" name="category" required>
                <option value="">Choose one</option>
                <?php foreach (CATEGORIES as $value => $label): ?>
                  <option value="<?php echo h($value); ?>"
                    <?php echo ($old['category'] ?? '') === $value ? 'selected' : ''; ?>>
                    <?php echo h($label); ?>
                  </option>
                <?php endforeach; ?>
              </select>
              <p class="error-message" role="alert"></p>
            </div>

            <div class="form-field" id="field-date">
              <label for="item_date">Date</label>
              <input type="date" id="item_date" name="item_date" max="<?php echo date('Y-m-d'); ?>"
                     value="<?php echo h($old['item_date'] ?? ''); ?>" required />
              <p class="error-message" role="alert"></p>
            </div>
          </div>

          <div class="form-field" id="field-location">
            <label for="location">Location</label>
            <input type="text" id="location" name="location" placeholder="e.g. Library, 2nd floor"
                   value="<?php echo h($old['location'] ?? ''); ?>" required />
            <p class="error-message" role="alert"></p>
          </div>

          <div class="form-field" id="field-description">
            <label for="description">Description</label>
            <textarea id="description" name="description"
                      placeholder="Colour, brand, distinguishing marks, contents..." required><?php echo h($old['description'] ?? ''); ?></textarea>
            <p class="error-message" role="alert"></p>
          </div>

          <div class="form-field" id="field-image">
            <label for="item_image">Photo <span class="text-muted">(optional)</span></label>
            <input type="file" id="item_image" name="item_image" accept="image/jpeg,image/png,image/webp" />
            <p class="hint">JPG, PNG or WEBP, up to 3 MB. (Browsers can&rsquo;t refill a file field, so choose the photo again if the form reloads.)</p>
            <p class="error-message" role="alert"></p>
          </div>

          <button type="submit" class="btn btn-primary btn-block">Submit report</button>
        </form>
      </div>

    </div>
  </section>

<?php require 'footer.php'; ?>
