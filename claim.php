<?php
// claim.php — shown when someone clicks "Claim this item" on a found listing.
// GET request: show the item + a reason form.
// POST request: record the claim and lock the item.

require 'auth-check.php';
require 'db.php';

$itemId = (int) ($_GET['item_id'] ?? $_POST['item_id'] ?? 0);

// --- Load the item being claimed ---
$stmt = $pdo->prepare('SELECT * FROM items WHERE item_id = ?');
$stmt->execute([$itemId]);
$item = $stmt->fetch();

if (!$item) {
    echo "That item doesn't exist. <a href='dashboard.php'>Back to dashboard</a>.";
    exit;
}

if ($item['item_type'] !== 'found') {
    echo "Only found items can be claimed. <a href='dashboard.php'>Back to dashboard</a>.";
    exit;
}

if ($item['user_id'] === $_SESSION['user_id']) {
    echo "You can't claim an item you reported yourself. <a href='dashboard.php'>Back to dashboard</a>.";
    exit;
}

// This is the check that solves "what if two people try to claim the same
// item" — the FIRST person to successfully submit a claim moves the item
// out of 'active', so this check blocks everyone after them.
if ($item['status'] !== 'active') {
    echo "This item already has a claim pending review, or has been resolved. <a href='dashboard.php'>Back to dashboard</a>.";
    exit;
}

// --- Handle the claim submission ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $claimReason = trim($_POST['claim_reason'] ?? '');

    if (strlen($claimReason) < 10) {
        echo "Please describe why this item is yours (at least 10 characters). <a href='javascript:history.back()'>Go back</a>.";
        exit;
    }

    // Insert the claim...
    $insertStmt = $pdo->prepare(
        'INSERT INTO claims (item_id, claimant_id, claim_reason, claim_status) VALUES (?, ?, ?, ?)'
    );
    $insertStmt->execute([$itemId, $_SESSION['user_id'], $claimReason, 'pending']);

    // ...and immediately lock the item so nobody else can claim it while
    // this claim is being reviewed at the admin desk.
    $updateStmt = $pdo->prepare('UPDATE items SET status = ? WHERE item_id = ?');
    $updateStmt->execute(['matched', $itemId]);

    header('Location: dashboard.php?claimed=1');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Claim item — Campus Lost &amp; Found</title>
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

      <div class="form-card" style="max-width:520px;">
        <h2>Claim this item</h2>

        <div class="tag-card" style="margin-bottom:1.5rem;">
          <span class="status status-found">Found</span>
          <h3><?php echo htmlspecialchars($item['item_name']); ?></h3>
          <p class="meta">
            <?php echo htmlspecialchars($item['location']); ?> &middot;
            <?php echo htmlspecialchars($item['item_date']); ?>
          </p>
          <p style="font-size:0.9rem;"><?php echo htmlspecialchars($item['description']); ?></p>
        </div>

        <p class="text-muted" style="margin-bottom:1.5rem;">
          Describe why you believe this item is yours. If your claim looks
          credible, bring your student ID to the Lost &amp; Found desk to
          collect it — the desk staff will do the final verification in person.
        </p>

        <form method="post" action="claim.php">
          <input type="hidden" name="item_id" value="<?php echo (int) $item['item_id']; ?>" />

          <div class="form-field">
            <label for="claim_reason">Why is this item yours?</label>
            <textarea id="claim_reason" name="claim_reason" required
              placeholder="e.g. Describe a mark, contents, or where exactly you lost it"></textarea>
          </div>

          <button type="submit" class="btn btn-primary btn-block">Submit claim</button>
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