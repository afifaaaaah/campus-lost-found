<?php
require 'auth-check.php';
require 'db.php';

// --- Read filter values from the URL (?search=...&category=...&type=...) ---
// Using GET (not POST) for filters is deliberate: it makes the filtered
// view a real, shareable/bookmarkable URL, and lets the browser back
// button work naturally.
$search   = trim($_GET['search'] ?? '');
$category = $_GET['category'] ?? 'all';
$type     = $_GET['type'] ?? 'all';

$allowedCategories = ['all', 'electronics', 'accessories', 'documents', 'bags', 'other'];
$allowedTypes      = ['all', 'lost', 'found'];

if (!in_array($category, $allowedCategories, true)) {
    $category = 'all';
}
if (!in_array($type, $allowedTypes, true)) {
    $type = 'all';
}

// --- Build the browse query one condition at a time ---
// We never concatenate $search/$category/$type directly into the SQL
// string — every value goes in as a ? placeholder, filled in by execute().
$conditions = ["status != 'closed'"];
$params = [];

if ($search !== '') {
    $conditions[] = 'item_name LIKE ?';
    $params[] = '%' . $search . '%';
}
if ($category !== 'all') {
    $conditions[] = 'category = ?';
    $params[] = $category;
}
if ($type !== 'all') {
    $conditions[] = 'item_type = ?';
    $params[] = $type;
}

$whereClause = implode(' AND ', $conditions);

$browseStmt = $pdo->prepare("SELECT * FROM items WHERE $whereClause ORDER BY created_at DESC LIMIT 30");
$browseStmt->execute($params);
$browseItems = $browseStmt->fetchAll();

// --- My reports: every item this user has ever submitted ---
$myItemsStmt = $pdo->prepare('SELECT * FROM items WHERE user_id = ? ORDER BY created_at DESC');
$myItemsStmt->execute([$_SESSION['user_id']]);
$myItems = $myItemsStmt->fetchAll();

// --- My claims: every claim this user has submitted, with the item name ---
$myClaimsStmt = $pdo->prepare(
    'SELECT claims.*, items.item_name
     FROM claims
     JOIN items ON claims.item_id = items.item_id
     WHERE claimant_id = ?
     ORDER BY claim_date DESC'
);
$myClaimsStmt->execute([$_SESSION['user_id']]);
$myClaims = $myClaimsStmt->fetchAll();

// Small helper so we don't repeat htmlspecialchars() everywhere below.
function h($value) {
    return htmlspecialchars($value ?? '');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Dashboard — Campus Lost &amp; Found</title>
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
        <li><a href="dashboard.php" aria-current="page">Browse items</a></li>
        <li><a href="report-item.php">Report an item</a></li>
        <li><a href="logout.php">Log out</a></li>
      </ul>
    </div>
  </header>

  <section class="section" style="padding-bottom:1rem;">
    <div class="container">
      <div class="flex-between">
        <div>
          <h1 style="font-size:1.8rem;">Hi, <?php echo h($_SESSION['full_name']); ?></h1>
          <p class="text-muted mb-0">Here's what's been reported across campus recently.</p>
        </div>
        <a class="btn btn-accent" href="report-item.php">+ Report an item</a>
      </div>

      <?php if (isset($_GET['reported'])): ?>
        <div class="alert alert-success mt-4">Your report was submitted.</div>
      <?php elseif (isset($_GET['updated'])): ?>
        <div class="alert alert-success mt-4">Your report was updated.</div>
      <?php elseif (isset($_GET['deleted'])): ?>
        <div class="alert alert-success mt-4">Your report was deleted.</div>
      <?php elseif (isset($_GET['claimed'])): ?>
        <div class="alert alert-success mt-4">Your claim was submitted. Bring your student ID to the Lost &amp; Found desk to complete verification.</div>
      <?php endif; ?>
    </div>
  </section>

  <!-- ============================ SEARCH / FILTER =============================
       A real GET form now — submitting it reloads the page with these
       values in the URL, and the PHP above runs a fresh SQL query using
       them. The JS in js/main.js still does a little instant client-side
       narrowing on top of whatever the server already sent down.
  -->
  <section class="section" style="padding-top:0.5rem; padding-bottom:0.5rem;">
    <div class="container">
      <form method="get" action="dashboard.php" class="form-card" style="max-width:none;">
        <div class="form-row" style="grid-template-columns: 2fr 1fr 1fr auto; align-items:end;">

          <div class="form-field mb-0">
            <label for="search-input">Search by item name</label>
            <input type="text" id="search-input" name="search" value="<?php echo h($search); ?>"
              placeholder="e.g. wallet, umbrella, ID card" />
          </div>

          <div class="form-field mb-0">
            <label for="filter-category">Category</label>
            <select id="filter-category" name="category">
              <option value="all">All categories</option>
              <option value="electronics" <?php echo $category === 'electronics' ? 'selected' : ''; ?>>Electronics</option>
              <option value="accessories" <?php echo $category === 'accessories' ? 'selected' : ''; ?>>Accessories</option>
              <option value="documents" <?php echo $category === 'documents' ? 'selected' : ''; ?>>Documents / ID cards</option>
              <option value="bags" <?php echo $category === 'bags' ? 'selected' : ''; ?>>Bags</option>
              <option value="other" <?php echo $category === 'other' ? 'selected' : ''; ?>>Other</option>
            </select>
          </div>

          <div class="form-field mb-0">
            <label for="filter-status">Type</label>
            <select id="filter-status" name="type">
              <option value="all">Lost &amp; found</option>
              <option value="lost" <?php echo $type === 'lost' ? 'selected' : ''; ?>>Lost</option>
              <option value="found" <?php echo $type === 'found' ? 'selected' : ''; ?>>Found</option>
            </select>
          </div>

          <button type="submit" class="btn btn-primary mb-0">Apply</button>
        </div>
      </form>
    </div>
  </section>

  <!-- ============================ ITEM TAG CARDS =============================== -->
  <section class="section">
    <div class="container">
      <h2 style="font-size:1.4rem;">Recently reported</h2>

      <?php if (empty($browseItems)): ?>
        <p class="text-muted mt-4">No items match your search or filters.</p>
      <?php endif; ?>

      <div class="grid grid-3 mt-4">
        <?php foreach ($browseItems as $listing): ?>
          <div class="tag-card">
            <span class="status <?php echo $listing['item_type'] === 'lost' ? 'status-lost' : 'status-found'; ?>">
              <?php echo ucfirst(h($listing['item_type'])); ?>
            </span>
            <h3><?php echo h($listing['item_name']); ?></h3>
            <p class="meta">
              <?php echo h($listing['location']); ?> &middot; <?php echo h($listing['item_date']); ?>
            </p>
            <p style="font-size:0.9rem;"><?php echo h($listing['description']); ?></p>

            <?php if ($listing['item_type'] === 'found' && $listing['status'] === 'active' && $listing['user_id'] !== $_SESSION['user_id']): ?>
              <a class="btn btn-outline btn-block" href="claim.php?item_id=<?php echo (int) $listing['item_id']; ?>">
                Claim this item
              </a>
            <?php elseif ($listing['item_type'] === 'found' && $listing['status'] !== 'active'): ?>
              <span class="btn btn-outline btn-block" style="opacity:0.6; cursor:default;">Claim pending review</span>
            <?php elseif ($listing['item_type'] === 'lost'): ?>
              <p class="text-muted" style="font-size:0.8rem;">
                Found this? Report it as a found item and the owner will find it here.
              </p>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </section>

  <!-- ============================== MY REPORTS TABLE ============================= -->
  <section class="section" style="padding-top:0;">
    <div class="container">
      <h2 style="font-size:1.4rem;">My reports</h2>

      <?php if (empty($myItems)): ?>
        <p class="text-muted mt-4">You haven't reported anything yet.</p>
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
                  <td><?php echo h(ucfirst($mine['category'])); ?></td>
                  <td><?php echo h(ucfirst($mine['item_type'])); ?></td>
                  <td><?php echo h($mine['item_date']); ?></td>
                  <td><span class="status status-<?php echo $mine['status'] === 'active' ? ($mine['item_type'] === 'lost' ? 'lost' : 'found') : 'claimed'; ?>">
                    <?php echo h(ucfirst($mine['status'])); ?>
                  </span></td>
                  <td class="table-actions">
                    <a href="edit-item.php?item_id=<?php echo (int) $mine['item_id']; ?>">Edit</a>
                    <form method="post" action="delete-item.php" style="display:inline;"
                      onsubmit="return confirm('Delete this report? This cannot be undone.');">
                      <input type="hidden" name="item_id" value="<?php echo (int) $mine['item_id']; ?>" />
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

  <!-- ============================== MY CLAIMS TABLE ============================= -->
  <section class="section" style="padding-top:0;">
    <div class="container">
      <h2 style="font-size:1.4rem;">My claims</h2>

      <?php if (empty($myClaims)): ?>
        <p class="text-muted mt-4">You haven't submitted any claims yet.</p>
      <?php else: ?>
        <div class="table-wrap mt-4">
          <table>
            <thead>
              <tr>
                <th scope="col">Item</th>
                <th scope="col">Submitted</th>
                <th scope="col">Status</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($myClaims as $claim): ?>
                <tr>
                  <td><?php echo h($claim['item_name']); ?></td>
                  <td><?php echo h($claim['claim_date']); ?></td>
                  <td><span class="status status-claimed"><?php echo h(ucfirst($claim['claim_status'])); ?></span></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <p class="text-muted" style="margin-top:1rem; font-size:0.85rem;">
          Claim verification is done in person at the Lost &amp; Found desk.
          This status will update once an admin reviews it.
        </p>
      <?php endif; ?>
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