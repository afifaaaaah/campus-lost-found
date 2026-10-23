<?php
require_once 'auth-check.php';
require_once 'db.php';

// Lazy automatic cleanup (resolved items after 10 days, unclaimed after 30).
// Cheap on a small table, so we simply run it whenever the dashboard loads.
run_cleanup($pdo);

$userId = current_user_id();

// --- Filters come from the URL: dashboard.php?search=wallet&category=bags&type=found
// GET (not POST) on purpose: the filtered view is a bookmarkable URL.
$search   = get_value('search');
$category = get_value('category');
$type     = get_value('type');

if ($category !== 'all' && !array_key_exists($category, CATEGORIES)) {
    $category = 'all';
}
if (!in_array($type, ['lost', 'found'], true)) {
    $type = 'all';
}

// --- Build the search query one condition at a time.
// Values NEVER go into the SQL text; each is a ? filled in by execute().
$conditions = ["status IN ('active', 'matched')"];
$params     = [];

if ($search !== '') {
    $conditions[] = '(item_name LIKE ? OR description LIKE ? OR location LIKE ?)';
    $like = '%' . $search . '%';
    array_push($params, $like, $like, $like);
}
if ($category !== 'all') {
    $conditions[] = 'category = ?';
    $params[]     = $category;
}
if ($type !== 'all') {
    $conditions[] = 'item_type = ?';
    $params[]     = $type;
}

$browseStmt = $pdo->prepare(
    'SELECT * FROM items WHERE ' . implode(' AND ', $conditions) . ' ORDER BY created_at DESC LIMIT 30'
);
$browseStmt->execute($params);
$browseItems = $browseStmt->fetchAll();

// --- My reports
$myItemsStmt = $pdo->prepare('SELECT * FROM items WHERE user_id = ? ORDER BY created_at DESC');
$myItemsStmt->execute([$userId]);
$myItems = $myItemsStmt->fetchAll();

// --- My claims (with the item's name)
$myClaimsStmt = $pdo->prepare(
    'SELECT claims.*, items.item_name
     FROM claims
     JOIN items ON claims.item_id = items.item_id
     WHERE claims.claimant_id = ?
     ORDER BY claims.claim_date DESC'
);
$myClaimsStmt->execute([$userId]);
$myClaims = $myClaimsStmt->fetchAll();

// --- Possible matches: for each of MY active lost reports, look for similar
// found items posted by other people (scoring lives in helpers.php).
$matchGroups = [];
foreach ($myItems as $mine) {
    if ($mine['item_type'] === 'lost' && $mine['status'] === 'active') {
        $found = find_possible_matches($pdo, $mine, $userId);
        if (!empty($found)) {
            $matchGroups[] = ['lost' => $mine, 'found' => $found];
        }
    }
    if (count($matchGroups) >= 5) {
        break;
    }
}

$pageTitle  = 'Dashboard';
$activePage = 'dashboard';
require 'header.php';
?>

  <section class="section" style="padding-bottom:1rem;">
    <div class="container">
      <div class="flex-between">
        <div>
          <h1 style="font-size:1.8rem;">Hi, <?php echo h($_SESSION['full_name']); ?></h1>
          <p class="text-muted mb-0">Here&rsquo;s what&rsquo;s been reported across campus recently.</p>
        </div>
        <a class="btn btn-accent" href="report-item.php">+ Report an item</a>
      </div>
    </div>
  </section>

  <!-- ============================ POSSIBLE MATCHES ============================ -->
  <?php if (!empty($matchGroups)): ?>
  <section class="section-tight">
    <div class="container">
      <h2 style="font-size:1.4rem;">Possible matches for your lost items</h2>
      <p class="text-muted">Found items that look similar to something you reported. Take a look &mdash; if one is yours, claim it.</p>

      <?php foreach ($matchGroups as $group): ?>
        <h3 style="font-size:1.05rem; margin-top:var(--space-3);">
          For your lost &ldquo;<?php echo h($group['lost']['item_name']); ?>&rdquo;
        </h3>
        <div class="grid grid-3">
          <?php foreach ($group['found'] as $match): ?>
            <div class="tag-card">
              <?php if ($match['image_path']): ?>
                <img class="item-photo" src="<?php echo h($match['image_path']); ?>" alt="Photo of <?php echo h($match['item_name']); ?>" />
              <?php endif; ?>
              <span class="status status-found">Found</span>
              <h3><?php echo h($match['item_name']); ?></h3>
              <p class="meta"><?php echo h($match['location']); ?> &middot; <?php echo h(fmt_date($match['item_date'])); ?></p>
              <a class="btn btn-accent btn-block" href="claim.php?item_id=<?php echo (int) $match['item_id']; ?>">This is mine</a>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endforeach; ?>
    </div>
  </section>
  <?php endif; ?>

  <!-- ============================ SEARCH / FILTER ============================= -->
  <section class="section" style="padding-bottom:0.5rem;">
    <div class="container">
      <form method="get" action="dashboard.php" class="form-card" style="max-width:none;">
        <div class="form-row" style="grid-template-columns: 2fr 1fr 1fr auto; align-items:end;">

          <div class="form-field mb-0">
            <label for="search-input">Search by name, place or description</label>
            <input type="text" id="search-input" name="search" value="<?php echo h($search); ?>"
                   placeholder="e.g. wallet, library, black" />
          </div>

          <div class="form-field mb-0">
            <label for="filter-category">Category</label>
            <select id="filter-category" name="category">
              <option value="all">All categories</option>
              <?php foreach (CATEGORIES as $value => $label): ?>
                <option value="<?php echo h($value); ?>" <?php echo $category === $value ? 'selected' : ''; ?>><?php echo h($label); ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="form-field mb-0">
            <label for="filter-type">Type</label>
            <select id="filter-type" name="type">
              <option value="all">Lost &amp; found</option>
              <option value="lost"  <?php echo $type === 'lost'  ? 'selected' : ''; ?>>Lost</option>
              <option value="found" <?php echo $type === 'found' ? 'selected' : ''; ?>>Found</option>
            </select>
          </div>

          <button type="submit" class="btn btn-primary">Apply</button>
        </div>
      </form>
    </div>
  </section>

  <!-- ============================ BROWSE ITEMS =============================== -->
  <section class="section">
    <div class="container">
      <h2 style="font-size:1.4rem;">Recently reported</h2>

      <?php if (empty($browseItems)): ?>
        <p class="empty-note mt-4">No items match your search or filters.</p>
      <?php endif; ?>

      <div class="grid grid-3 mt-4">
        <?php foreach ($browseItems as $listing): ?>
          <?php $isMine = ((int) $listing['user_id'] === $userId); ?>
          <div class="tag-card">
            <?php if ($listing['image_path']): ?>
              <img class="item-photo" src="<?php echo h($listing['image_path']); ?>" alt="Photo of <?php echo h($listing['item_name']); ?>" />
            <?php endif; ?>

            <span class="status <?php echo $listing['item_type'] === 'lost' ? 'status-lost' : 'status-found'; ?>">
              <?php echo h(ucfirst($listing['item_type'])); ?>
            </span>
            <h3><?php echo h($listing['item_name']); ?></h3>
            <p class="meta"><?php echo h($listing['location']); ?> &middot; <?php echo h(fmt_date($listing['item_date'])); ?></p>
            <p style="font-size:0.9rem;"><?php echo h($listing['description']); ?></p>

            <?php if ($isMine): ?>
              <p class="text-muted" style="font-size:0.8rem;">This is your report.</p>
            <?php elseif ($listing['item_type'] === 'found' && $listing['status'] === 'active'): ?>
              <a class="btn btn-outline btn-block" href="claim.php?item_id=<?php echo (int) $listing['item_id']; ?>">Claim this item</a>
            <?php elseif ($listing['item_type'] === 'found'): ?>
              <span class="btn btn-outline btn-block" style="opacity:0.6; cursor:default;">Claim pending review</span>
            <?php else: ?>
              <p class="text-muted" style="font-size:0.8rem;">
                Found this? Report it as a found item and the owner will see it here.
              </p>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </section>

  <!-- ============================== MY REPORTS ============================== -->
  <section class="section" id="my-reports" style="padding-top:0;">
    <div class="container">
      <h2 style="font-size:1.4rem;">My reports</h2>

      <?php if (empty($myItems)): ?>
        <p class="empty-note mt-4">You haven&rsquo;t reported anything yet.</p>
      <?php else: ?>
        <div class="table-wrap mt-4">
          <table>
            <thead>
              <tr>
                <th scope="col">Item</th>
                <th scope="col">Category</th>
                <th scope="col">Type</th>
                <th scope="col">Date</th>
                <th scope="col">Status</th>
                <th scope="col">Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($myItems as $mine): ?>
                <tr>
                  <td><?php echo h($mine['item_name']); ?></td>
                  <td><?php echo h(CATEGORIES[$mine['category']] ?? ucfirst($mine['category'])); ?></td>
                  <td><?php echo h(ucfirst($mine['item_type'])); ?></td>
                  <td><?php echo h(fmt_date($mine['item_date'])); ?></td>
                  <td><span class="status <?php echo h(item_status_class($mine)); ?>"><?php echo h(item_status_label($mine['status'])); ?></span></td>
                  <td class="table-actions">

                    <?php if ($mine['status'] === 'active'): ?>
                      <a href="edit-item.php?item_id=<?php echo (int) $mine['item_id']; ?>">Edit</a>

                      <?php if ($mine['item_type'] === 'lost'): ?>
                        <form method="post" action="resolve-item.php" class="inline-form"
                              onsubmit="return confirm('Mark this as resolved? It will be removed in <?php echo (int) CLEANUP_RESOLVED_DAYS; ?> days.');">
                          <?php echo csrf_field(); ?>
                          <input type="hidden" name="item_id" value="<?php echo (int) $mine['item_id']; ?>" />
                          <button type="submit">I got it back</button>
                        </form>
                      <?php endif; ?>
                    <?php elseif ($mine['status'] === 'matched'): ?>
                      <span class="text-muted">Claim under review</span>
                    <?php elseif ($mine['status'] === 'claimed'): ?>
                      <span class="text-muted">Ready for hand-over at the desk</span>
                    <?php endif; ?>

                    <?php if (in_array($mine['status'], ['active', 'resolved', 'closed'], true)): ?>
                      <form method="post" action="delete-item.php" class="inline-form"
                            onsubmit="return confirm('Delete this report? This cannot be undone.');">
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="item_id" value="<?php echo (int) $mine['item_id']; ?>" />
                        <button type="submit" class="danger">Delete</button>
                      </form>
                    <?php endif; ?>

                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <p class="text-muted" style="margin-top:1rem; font-size:0.85rem;">
          Reports are removed automatically: resolved ones after <?php echo (int) CLEANUP_RESOLVED_DAYS; ?> days,
          and reports nobody responded to after <?php echo (int) CLEANUP_UNRESPONDED_DAYS; ?> days.
        </p>
      <?php endif; ?>
    </div>
  </section>

  <!-- ============================== MY CLAIMS ============================== -->
  <section class="section" id="my-claims" style="padding-top:0;">
    <div class="container">
      <h2 style="font-size:1.4rem;">My claims</h2>

      <?php if (empty($myClaims)): ?>
        <p class="empty-note mt-4">You haven&rsquo;t submitted any claims yet.</p>
      <?php else: ?>
        <div class="table-wrap mt-4">
          <table>
            <thead>
              <tr>
                <th scope="col">Item</th>
                <th scope="col">Submitted</th>
                <th scope="col">Status</th>
                <th scope="col">Note from the desk</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($myClaims as $claim): ?>
                <tr>
                  <td><?php echo h($claim['item_name']); ?></td>
                  <td><?php echo h(fmt_date($claim['claim_date'])); ?></td>
                  <td><span class="status <?php echo h(claim_status_class($claim['claim_status'])); ?>"><?php echo h(ucfirst($claim['claim_status'])); ?></span></td>
                  <td class="wrap"><?php echo $claim['admin_note'] ? h($claim['admin_note']) : '<span class="text-muted">&mdash;</span>'; ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <p class="text-muted" style="margin-top:1rem; font-size:0.85rem;">
          Claims are verified in person at the Lost &amp; Found desk. Bring your student ID.
        </p>
      <?php endif; ?>
    </div>
  </section>

<?php require 'footer.php'; ?>
