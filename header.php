<?php
// header.php — the top of EVERY page: <head>, navigation, flash messages.
// A page sets $pageTitle and $activePage first, then does:
//     require 'header.php';
// Change the menu here once and every page updates.

$pageTitle  = $pageTitle  ?? 'Campus Lost & Found';
$activePage = $activePage ?? '';
$loggedIn   = is_logged_in();
$isAdmin    = ($_SESSION['role'] ?? '') === 'admin';

/** Prints aria-current="page" on the link of the page you are on. */
function current_attr(string $page, string $activePage): string
{
    return $page === $activePage ? 'aria-current="page"' : '';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title><?php echo h($pageTitle); ?> — Campus Lost &amp; Found</title>
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link
    href="https://fonts.googleapis.com/css2?family=Fraunces:wght@500;600&family=Inter:wght@400;500;600;700&display=swap"
    rel="stylesheet"
  />
  <link rel="stylesheet" href="css/style.css" />
  <link rel="stylesheet" href="css/extras.css" />
</head>
<body>

<?php if ($loggedIn): ?>

  <!-- ===== LOGGED-IN: three-line button (top-left) + slide-in account menu ===== -->
  <header class="site-nav">
    <div class="container nav-bar">
      <button class="menu-btn" id="menu-btn" type="button"
              aria-label="Open menu" aria-expanded="false" aria-controls="side-menu">
        <span></span><span></span><span></span>
      </button>
      <a class="brand" href="dashboard.php">Campus Lost &amp; Found <span>desk</span></a>
    </div>
  </header>

  <div class="menu-backdrop" id="menu-backdrop"></div>

  <nav class="side-menu" id="side-menu" aria-label="Account menu">
    <div class="menu-user">
      <strong><?php echo h($_SESSION['full_name'] ?? ''); ?></strong>
      <span><?php echo $isAdmin ? 'Lost &amp; Found desk admin' : 'Student'; ?></span>
    </div>

    <a href="index.php" <?php echo current_attr('home', $activePage); ?>>Home</a>
    <a href="dashboard.php" <?php echo current_attr('dashboard', $activePage); ?>>Browse items</a>
    <a href="report-item.php" <?php echo current_attr('report', $activePage); ?>>Report an item</a>
    <a href="dashboard.php#my-reports">My reports</a>
    <a href="dashboard.php#my-claims">My claims</a>
    <?php if ($isAdmin): ?>
      <a href="admin.php" <?php echo current_attr('admin', $activePage); ?>>Admin panel</a>
    <?php endif; ?>

    <a class="menu-signout" href="logout.php">Sign out</a>
  </nav>

<?php else: ?>

  <!-- ===== LOGGED-OUT: the simple public navigation bar ===== -->
  <header class="site-nav">
    <div class="container">
      <a class="brand" href="index.php">Campus Lost &amp; Found <span>desk</span></a>
      <button class="nav-toggle" aria-label="Toggle navigation menu" aria-expanded="false">&#9776;</button>
      <ul class="nav-links">
        <li><a href="index.php" <?php echo current_attr('home', $activePage); ?>>Home</a></li>
        <li><a href="login.php" <?php echo current_attr('login', $activePage); ?>>Log in</a></li>
        <li><a href="register.php" <?php echo current_attr('register', $activePage); ?>>Register</a></li>
      </ul>
    </div>
  </header>

<?php endif; ?>

<?php flash_show(); ?>
