<?php
// register.php — GET shows the form, POST creates the account.
// The JavaScript checks in js/validation.js are only a convenience;
// THIS is where the rules are actually enforced.

require_once 'helpers.php';
require_once 'db.php';

if (is_logged_in()) {
    redirect('dashboard.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $fullName        = post_value('full_name');
    $email           = post_value('email');
    $phone           = post_value('phone');
    $password        = post_value('password', false);
    $confirmPassword = post_value('confirm_password', false);

    $errors = [];

    if (mb_strlen($fullName) < 2 || mb_strlen($fullName) > 100) {
        $errors[] = 'Please enter your full name (2 to 100 characters).';
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 150) {
        $errors[] = 'Enter a valid email address.';
    }
    if ($phone !== '' && !preg_match('/^\+?[0-9\s-]{7,15}$/', $phone)) {
        $errors[] = 'Enter a valid phone number, or leave it blank.';
    }
    if (strlen($password) < 8 || !preg_match('/[A-Za-z]/', $password) || !preg_match('/\d/', $password)) {
        $errors[] = 'Password needs at least 8 characters, including a letter and a number.';
    }
    if (strlen($password) > 72) {
        // bcrypt (what password_hash uses) only reads the first 72 bytes
        $errors[] = 'Password can be at most 72 characters.';
    }
    if ($password !== $confirmPassword) {
        $errors[] = 'Passwords do not match.';
    }

    if (empty($errors)) {
        $check = $pdo->prepare('SELECT user_id FROM users WHERE email = ?');
        $check->execute([$email]);
        if ($check->fetch()) {
            $errors[] = 'An account with this email already exists. Try logging in instead.';
        }
    }

    if (!empty($errors)) {
        flash_errors($errors);
        // Keep what they typed (but never the passwords)
        remember_input(['full_name' => $fullName, 'email' => $email, 'phone' => $phone]);
        redirect('register.php');
    }

    // The ONLY form the password takes once it leaves this script.
    $passwordHash = password_hash($password, PASSWORD_DEFAULT);

    try {
        $insert = $pdo->prepare(
            'INSERT INTO users (full_name, email, password, phone, role) VALUES (?, ?, ?, ?, ?)'
        );
        $insert->execute([$fullName, $email, $passwordHash, $phone !== '' ? $phone : null, 'student']);
    } catch (PDOException $e) {
        // 23000 = duplicate key: two people registered the same email at the
        // same instant and the UNIQUE constraint caught the second one.
        if ($e->getCode() === '23000') {
            flash_set('error', 'An account with this email already exists.');
            redirect('register.php');
        }
        throw $e;
    }

    // Deliberately NOT logging them in: they must log in themselves.
    flash_set('success', 'Account created. Please log in.');
    redirect('login.php');
}

$old        = pull_old();
$pageTitle  = 'Register';
$activePage = 'register';
require 'header.php';
?>

  <section class="section">
    <div class="container">

      <div class="form-card" style="max-width:520px;">
        <h2>Create your account</h2>
        <p class="text-muted" style="margin-bottom:1.5rem;">
          Use your campus email so the desk can verify who you are when you
          come to collect an item.
        </p>

        <form id="register-form" action="register.php" method="post" novalidate>
          <?php echo csrf_field(); ?>

          <div class="form-field" id="field-full-name">
            <label for="full_name">Full name</label>
            <input type="text" id="full_name" name="full_name" autocomplete="name"
                   value="<?php echo h($old['full_name'] ?? ''); ?>" required />
            <p class="error-message" role="alert"></p>
          </div>

          <div class="form-field" id="field-email">
            <label for="email">Campus email</label>
            <input type="email" id="email" name="email" placeholder="you@university.edu"
                   autocomplete="email" value="<?php echo h($old['email'] ?? ''); ?>" required />
            <p class="error-message" role="alert"></p>
          </div>

          <div class="form-field" id="field-phone">
            <label for="phone">Phone number <span class="text-muted">(optional)</span></label>
            <input type="tel" id="phone" name="phone" autocomplete="tel" placeholder="+91 98765 43210"
                   value="<?php echo h($old['phone'] ?? ''); ?>" />
            <p class="hint">Shown only to the Lost &amp; Found desk when reviewing your claims.</p>
            <p class="error-message" role="alert"></p>
          </div>

          <div class="form-row form-row-2">
            <div class="form-field" id="field-password">
              <label for="password">Password</label>
              <input type="password" id="password" name="password" autocomplete="new-password" required />
              <p class="hint">At least 8 characters, one letter and one number.</p>
              <p class="error-message" role="alert"></p>
            </div>

            <div class="form-field" id="field-confirm-password">
              <label for="confirm_password">Confirm password</label>
              <input type="password" id="confirm_password" name="confirm_password" autocomplete="new-password" required />
              <p class="error-message" role="alert"></p>
            </div>
          </div>

          <button type="submit" class="btn btn-primary btn-block">Create account</button>
        </form>

        <p class="text-muted" style="margin-top:1.25rem; font-size:0.9rem;">
          Already registered? <a href="login.php">Log in</a>
        </p>
      </div>

    </div>
  </section>

<?php require 'footer.php'; ?>
