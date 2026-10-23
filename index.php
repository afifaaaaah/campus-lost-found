<?php
// index.php — public home page. The three numbers in the hero are REAL
// counts from the database (the earlier static version had made-up ones).

require_once 'helpers.php';
require_once 'db.php';

$activeReports = (int) $pdo->query("SELECT COUNT(*) FROM items WHERE status IN ('active', 'matched')")->fetchColumn();
$foundWaiting  = (int) $pdo->query("SELECT COUNT(*) FROM items WHERE item_type = 'found' AND status = 'active'")->fetchColumn();
$studentCount  = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'student'")->fetchColumn();

$pageTitle  = 'Home';
$activePage = 'home';
require 'header.php';
?>

  <!-- =============================== HERO ================================ -->
  <section class="section">
    <div class="container grid grid-2" style="align-items:center;">
      <div>
        <h1>Left something behind on campus?</h1>
        <p>
          Report it, search what other students have handed in, and claim
          what&rsquo;s yours &mdash; every claim is verified in person at the
          campus Lost &amp; Found desk before anything changes hands.
        </p>

        <div class="flex-between" style="justify-content:flex-start; gap:1rem; margin-top:1.5rem;">
          <?php if ($loggedIn): ?>
            <a class="btn btn-primary" href="report-item.php">Report an item</a>
            <a class="btn btn-outline" href="dashboard.php">Browse items</a>
          <?php else: ?>
            <a class="btn btn-primary" href="register.php">Create an account</a>
            <a class="btn btn-outline" href="login.php">Log in</a>
          <?php endif; ?>
        </div>

        <div class="stats-row">
          <div class="stat">
            <span class="num"><?php echo $activeReports; ?></span>
            <span class="label">open reports right now</span>
          </div>
          <div class="stat">
            <span class="num"><?php echo $foundWaiting; ?></span>
            <span class="label">found items waiting for an owner</span>
          </div>
          <div class="stat">
            <span class="num"><?php echo $studentCount; ?></span>
            <span class="label">registered students</span>
          </div>
        </div>
      </div>

      <!-- An illustration of what a listing looks like -->
      <div class="tag-card" style="max-width:320px; justify-self:end;">
        <span class="status status-found">Found</span>
        <h3>Black leather wallet</h3>
        <p class="meta">Library, 2nd floor &middot; example listing</p>
        <p class="text-muted" style="font-size:0.9rem;">
          Contains a student ID and a few cards. Handed in to the front desk.
        </p>
        <a class="btn btn-accent btn-block" href="<?php echo $loggedIn ? 'dashboard.php' : 'login.php'; ?>">This might be mine</a>
      </div>
    </div>
  </section>

  <!-- ========================= HOW IT WORKS =============================== -->
  <section class="section" style="background:var(--color-paper-dim);">
    <div class="container">
      <h2>How a recovery works</h2>
      <p class="text-muted">Three steps, with a human check at the end.</p>

      <div class="grid grid-3 mt-4">
        <div>
          <h3>1. Report it</h3>
          <p>Lost something? Found something? Post a report with where and when &mdash; a photo helps but isn&rsquo;t required. Items handed in at the desk are logged by the desk staff.</p>
        </div>
        <div>
          <h3>2. Find the match</h3>
          <p>The site compares lost and found reports and suggests possible matches, or you can search and filter the board yourself.</p>
        </div>
        <div>
          <h3>3. Claim &amp; verify</h3>
          <p>Submit a claim saying why it&rsquo;s yours. The desk reviews it, you show your student ID, and the item is handed back.</p>
        </div>
      </div>
    </div>
  </section>

<?php require 'footer.php'; ?>
