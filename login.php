<?php
// login.php — one file, two jobs:
//   GET  request -> show the login form
//   POST request -> check the credentials and start a session

require_once 'helpers.php';
require_once 'db.php';

// Already logged in? No need to see the login page.
if (is_logged_in()) {
    redirect('dashboard.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $email    = post_value('email');
    $password = post_value('password', false);   // never trim a password

    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $password === '') {
        flash_set('error', 'Enter a valid email and your password.');
        remember_input(['email' => $email]);
        redirect('login.php');
    }

    $stmt = $pdo->prepare('SELECT user_id, full_name, password, role FROM users WHERE email = ?');
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    // password_verify() re-hashes the typed password and compares it with
    // the stored hash. We give ONE message for "no such email" and "wrong
    // password" so an attacker can't use this form to discover which
    // emails are registered.
    if (!$user || !password_verify($password, $user['password'])) {
        flash_set('error', 'Incorrect email or password.');
        remember_input(['email' => $email]);
        redirect('login.php');
    }

    // New session ID on every login: stops "session fixation", where an
    // attacker plants a known session ID and waits for the victim to log in.
    session_regenerate_id(true);

    $_SESSION['user_id']       = (int) $user['user_id'];
    $_SESSION['full_name']     = $user['full_name'];
    $_SESSION['role']          = $user['role'];
    $_SESSION['last_activity'] = time();      // starts the idle-timeout clock

    redirect($user['role'] === 'admin' ? 'admin.php' : 'dashboard.php');
}

$old        = pull_old();
$pageTitle  = 'Log in';
$activePage = 'login';
require 'header.php';
?>

  <section class="section">
    <div class="container">

      <div class="form-card">
        <h2>Welcome back</h2>
        <p class="text-muted" style="margin-bottom:1.5rem;">
          Log in to report an item, check your claims, or view your reports.
        </p>

        <form id="login-form" action="login.php" method="post" novalidate>
          <?php echo csrf_field(); ?>

          <div class="form-field" id="field-login-email">
            <label for="login_email">Email</label>
            <input type="email" id="login_email" name="email"
                   value="<?php echo h($old['email'] ?? ''); ?>"
                   placeholder="you@university.edu" autocomplete="email" required />
            <p class="error-message" role="alert"></p>
          </div>

          <div class="form-field" id="field-login-password">
            <label for="login_password">Password</label>
            <input type="password" id="login_password" name="password"
                   autocomplete="current-password" required />
            <p class="error-message" role="alert"></p>
          </div>

          <button type="submit" class="btn btn-primary btn-block">Log in</button>
        </form>

        <p class="text-muted" style="margin-top:1.25rem; font-size:0.9rem;">
          New here? <a href="register.php">Create an account</a>
        </p>
      </div>

    </div>
  </section>

<?php require 'footer.php'; ?>
