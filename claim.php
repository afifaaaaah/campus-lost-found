<?php
// claim.php — someone clicks "Claim this item" on a FOUND listing.
//   GET  -> show the item and a "why is it yours?" form
//   POST -> record the claim and lock the item for admin review

require_once 'auth-check.php';
require_once 'db.php';

$itemId = (int) ($_GET['item_id'] ?? $_POST['item_id'] ?? 0);

/** Show a message and go back to the dashboard. */
function bounce(string $message): void
{
    flash_set('error', $message);
    redirect('dashboard.php');
}

$stmt = $pdo->prepare('SELECT * FROM items WHERE item_id = ?');
$stmt->execute([$itemId]);
$item = $stmt->fetch();

if (!$item)                                          bounce("That item doesn't exist.");
if ($item['item_type'] !== 'found')                  bounce('Only found items can be claimed.');
if ((int) $item['user_id'] === current_user_id())    bounce("You can't claim an item you reported yourself.");
if ($item['status'] !== 'active')                    bounce('This item already has a claim in progress, or has been resolved.');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $claimReason = post_value('claim_reason');
    if (mb_strlen($claimReason) < 10 || mb_strlen($claimReason) > 1000) {
        flash_set('error', 'Describe why this item is yours (10 to 1000 characters).');
        remember_input(['claim_reason' => $claimReason]);
        redirect('claim.php?item_id=' . $itemId);
    }

    // A transaction = "all of these steps happen, or none of them do".
    $pdo->beginTransaction();

    // STEP 1 — try to lock the item. The WHERE ... status = 'active' makes
    // this atomic: if two students click Submit at the same instant, the
    // database lets exactly ONE of these UPDATEs change a row. The other
    // updates zero rows and is turned away below. No messaging needed.
    $lock = $pdo->prepare("UPDATE items SET status = 'matched' WHERE item_id = ? AND status = 'active'");
    $lock->execute([$itemId]);

    if ($lock->rowCount() !== 1) {
        $pdo->rollBack();
        bounce('Someone else just claimed this item. It is now under review.');
    }

    // STEP 2 — record the claim
    $insert = $pdo->prepare(
        "INSERT INTO claims (item_id, claimant_id, claim_reason, claim_status) VALUES (?, ?, ?, 'pending')"
    );
    $insert->execute([$itemId, current_user_id(), $claimReason]);

    $pdo->commit();

    flash_set('success', 'Your claim was submitted. Bring your student ID to the Lost & Found desk to complete verification.');
    redirect('dashboard.php#my-claims');
}

$old        = pull_old();
$pageTitle  = 'Claim item';
$activePage = '';
require 'header.php';
?>

  <section class="section">
    <div class="container">

      <div class="form-card" style="max-width:520px;">
        <h2>Claim this item</h2>

        <div class="tag-card" style="margin-bottom:1.5rem;">
          <?php if ($item['image_path']): ?>
            <img class="item-photo" src="<?php echo h($item['image_path']); ?>" alt="Photo of <?php echo h($item['item_name']); ?>" />
          <?php endif; ?>
          <span class="status status-found">Found</span>
          <h3><?php echo h($item['item_name']); ?></h3>
          <p class="meta"><?php echo h($item['location']); ?> &middot; <?php echo h(fmt_date($item['item_date'])); ?></p>
          <p style="font-size:0.9rem;"><?php echo h($item['description']); ?></p>
        </div>

        <p class="text-muted" style="margin-bottom:1.5rem;">
          Describe why you believe this item is yours &mdash; something only the
          owner would know. If your claim looks credible, bring your student ID
          to the Lost &amp; Found desk; the staff do the final check in person.
        </p>

        <form method="post" action="claim.php">
          <?php echo csrf_field(); ?>
          <input type="hidden" name="item_id" value="<?php echo (int) $item['item_id']; ?>" />

          <div class="form-field">
            <label for="claim_reason">Why is this item yours?</label>
            <textarea id="claim_reason" name="claim_reason" required
              placeholder="e.g. a mark, the contents, where exactly you lost it"><?php echo h($old['claim_reason'] ?? ''); ?></textarea>
          </div>

          <button type="submit" class="btn btn-primary btn-block">Submit claim</button>
          <p style="margin-top:1rem; text-align:center;"><a href="dashboard.php">Cancel</a></p>
        </form>
      </div>

    </div>
  </section>

<?php require 'footer.php'; ?>
