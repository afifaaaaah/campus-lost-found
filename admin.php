<?php
// admin.php — the Lost & Found desk's control panel (admins only).
// admin-check.php sends everyone else away before any of this runs.

require_once 'admin-check.php';     // also loads auth-check.php and db.php

run_cleanup($pdo);

// --- The four headline numbers
$studentCount = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'student'")->fetchColumn();
$activeCount  = (int) $pdo->query("SELECT COUNT(*) FROM items WHERE status = 'active'")->fetchColumn();
$pendingCount = (int) $pdo->query("SELECT COUNT(*) FROM claims WHERE claim_status = 'pending'")->fetchColumn();
$pickupCount  = (int) $pdo->query("SELECT COUNT(*) FROM items WHERE status = 'claimed'")->fetchColumn();

// --- Claims waiting for a decision (oldest first = fairest)
$pendingClaims = $pdo->query(
    "SELECT c.claim_id, c.claim_reason, c.claim_date,
            i.item_name, i.category, i.location, i.item_date, i.description, i.image_path,
            u.full_name AS claimant_name, u.email AS claimant_email, u.phone AS claimant_phone,
            f.full_name AS finder_name
     FROM claims c
     JOIN items i ON c.item_id = i.item_id
     JOIN users u ON c.claimant_id = u.user_id
     JOIN users f ON i.user_id = f.user_id
     WHERE c.claim_status = 'pending'
     ORDER BY c.claim_date ASC"
)->fetchAll();

// --- Every listing, newest first
$allItems = $pdo->query(
    "SELECT i.*, u.full_name AS reporter_name
     FROM items i
     JOIN users u ON i.user_id = u.user_id
     ORDER BY i.created_at DESC
     LIMIT 100"
)->fetchAll();

// --- Last 10 decisions (a simple audit trail)
$recentDecisions = $pdo->query(
    "SELECT c.claim_status, c.reviewed_at, c.admin_note, i.item_name, u.full_name AS claimant_name
     FROM claims c
     JOIN items i ON c.item_id = i.item_id
     JOIN users u ON c.claimant_id = u.user_id
     WHERE c.claim_status != 'pending'
     ORDER BY c.reviewed_at DESC
     LIMIT 10"
)->fetchAll();

$pageTitle  = 'Admin panel';
$activePage = 'admin';
require 'header.php';
?>

  <section class="section" style="padding-bottom:1rem;">
    <div class="container">
      <h1 style="font-size:1.8rem;">Lost &amp; Found desk</h1>
      <p class="text-muted">Review claims, hand items back, and keep the board tidy.</p>

      <div class="stat-grid mt-4">
        <div class="stat-card">
          <span class="num"><?php echo $studentCount; ?></span>
          <span class="label">registered students</span>
        </div>
        <div class="stat-card">
          <span class="num"><?php echo $activeCount; ?></span>
          <span class="label">active listings</span>
        </div>
        <div class="stat-card <?php echo $pendingCount > 0 ? 'needs-attention' : ''; ?>">
          <span class="num"><?php echo $pendingCount; ?></span>
          <span class="label">claims to review</span>
        </div>
        <div class="stat-card <?php echo $pickupCount > 0 ? 'needs-attention' : ''; ?>">
          <span class="num"><?php echo $pickupCount; ?></span>
          <span class="label">awaiting hand-over</span>
        </div>
      </div>
    </div>
  </section>

  <!-- ========================== CLAIMS TO REVIEW ========================== -->
  <section class="section-tight">
    <div class="container">
      <h2 style="font-size:1.4rem;">Claims to review</h2>
      <p class="text-muted">
        Approve once the claimant has convinced you (in person, with their student ID).
        Reject to put the item back on the board for others to claim.
      </p>

      <?php if (empty($pendingClaims)): ?>
        <p class="empty-note">No claims are waiting. Nice and quiet.</p>
      <?php endif; ?>

      <?php foreach ($pendingClaims as $claim): ?>
        <div class="claim-panel">

          <!-- Left: the found item -->
          <div>
            <?php if ($claim['image_path']): ?>
              <img class="photo-preview" src="<?php echo h($claim['image_path']); ?>" alt="Photo of <?php echo h($claim['item_name']); ?>" />
            <?php endif; ?>
            <h3><?php echo h($claim['item_name']); ?></h3>
            <dl>
              <dt>Category</dt>
              <dd><?php echo h(CATEGORIES[$claim['category']] ?? $claim['category']); ?></dd>
              <dt>Found at</dt>
              <dd><?php echo h($claim['location']); ?>, <?php echo h(fmt_date($claim['item_date'])); ?></dd>
              <dt>Description given by the finder (<?php echo h($claim['finder_name']); ?>)</dt>
              <dd><?php echo h($claim['description']); ?></dd>
            </dl>
          </div>

          <!-- Right: the claimant, their reason, and the decision -->
          <div>
            <h3>Claim by <?php echo h($claim['claimant_name']); ?></h3>
            <dl>
              <dt>Contact</dt>
              <dd>
                <?php echo h($claim['claimant_email']); ?>
                <?php echo $claim['claimant_phone'] ? ' &middot; ' . h($claim['claimant_phone']) : ''; ?>
              </dd>
              <dt>Submitted</dt>
              <dd><?php echo h(fmt_date($claim['claim_date'])); ?></dd>
              <dt>Their reason</dt>
              <dd><?php echo h($claim['claim_reason']); ?></dd>
            </dl>

            <form method="post" action="admin-action.php" class="claim-decision">
              <?php echo csrf_field(); ?>
              <input type="hidden" name="action" value="decide_claim" />
              <input type="hidden" name="claim_id" value="<?php echo (int) $claim['claim_id']; ?>" />
              <input type="text" name="admin_note" maxlength="255"
                     placeholder="Note for the claimant (optional), e.g. bring your student ID" />
              <button type="submit" name="decision" value="approve" class="btn btn-success btn-sm">Approve claim</button>
              <button type="submit" name="decision" value="reject" class="btn btn-danger btn-sm"
                      onclick="return confirm('Reject this claim and put the item back on the board?');">Reject claim</button>
            </form>
          </div>

        </div>
      <?php endforeach; ?>
    </div>
  </section>

  <!-- ============================= ALL LISTINGS ============================= -->
  <section class="section-tight">
    <div class="container">
      <h2 style="font-size:1.4rem;">All listings</h2>

      <?php if (empty($allItems)): ?>
        <p class="empty-note">Nothing has been reported yet.</p>
      <?php else: ?>
        <div class="table-wrap mt-4">
          <table>
            <thead>
              <tr>
                <th scope="col">Item</th>
                <th scope="col">Type</th>
                <th scope="col">Reported by</th>
                <th scope="col">Posted</th>
                <th scope="col">Status</th>
                <th scope="col">Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($allItems as $row): ?>
                <tr>
                  <td><?php echo h($row['item_name']); ?></td>
                  <td><?php echo h(ucfirst($row['item_type'])); ?></td>
                  <td><?php echo h($row['reporter_name']); ?></td>
                  <td><?php echo h(fmt_date($row['created_at'])); ?></td>
                  <td><span class="status <?php echo h(item_status_class($row)); ?>"><?php echo h(item_status_label($row['status'])); ?></span></td>
                  <td class="table-actions">

                    <?php if (in_array($row['status'], ['active', 'claimed'], true)): ?>
                      <form method="post" action="admin-action.php" class="inline-form"
                            onsubmit="return confirm('Mark this item as resolved? It will be removed in <?php echo (int) CLEANUP_RESOLVED_DAYS; ?> days.');">
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="action" value="resolve" />
                        <input type="hidden" name="item_id" value="<?php echo (int) $row['item_id']; ?>" />
                        <button type="submit"><?php echo $row['status'] === 'claimed' ? 'Mark handed over' : 'Mark resolved'; ?></button>
                      </form>
                    <?php endif; ?>

                    <?php if ($row['status'] === 'active'): ?>
                      <form method="post" action="admin-action.php" class="inline-form"
                            onsubmit="return confirm('Close this listing (for example, a duplicate or invalid report)?');">
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="action" value="close" />
                        <input type="hidden" name="item_id" value="<?php echo (int) $row['item_id']; ?>" />
                        <button type="submit">Close</button>
                      </form>
                    <?php endif; ?>

                    <form method="post" action="admin-action.php" class="inline-form"
                          onsubmit="return confirm('Permanently delete this listing and its claims?');">
                      <?php echo csrf_field(); ?>
                      <input type="hidden" name="action" value="delete" />
                      <input type="hidden" name="item_id" value="<?php echo (int) $row['item_id']; ?>" />
                      <button type="submit" class="danger">Delete</button>
                    </form>

                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>
  </section>

  <!-- ========================== RECENT DECISIONS ========================== -->
  <section class="section-tight">
    <div class="container">
      <h2 style="font-size:1.4rem;">Recent decisions</h2>

      <?php if (empty($recentDecisions)): ?>
        <p class="empty-note">No claims have been reviewed yet.</p>
      <?php else: ?>
        <div class="table-wrap mt-4">
          <table>
            <thead>
              <tr>
                <th scope="col">Item</th>
                <th scope="col">Claimant</th>
                <th scope="col">Decision</th>
                <th scope="col">Date</th>
                <th scope="col">Note</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($recentDecisions as $decision): ?>
                <tr>
                  <td><?php echo h($decision['item_name']); ?></td>
                  <td><?php echo h($decision['claimant_name']); ?></td>
                  <td><span class="status <?php echo h(claim_status_class($decision['claim_status'])); ?>"><?php echo h(ucfirst($decision['claim_status'])); ?></span></td>
                  <td><?php echo h(fmt_date($decision['reviewed_at'])); ?></td>
                  <td class="wrap"><?php echo $decision['admin_note'] ? h($decision['admin_note']) : '<span class="text-muted">&mdash;</span>'; ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>
  </section>

<?php require 'footer.php'; ?>
