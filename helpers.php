<?php
// =========================================================================
// helpers.php — shared setup and small reusable functions
// =========================================================================
// Almost every page requires this file (directly, or through
// auth-check.php). It does three jobs:
//   1. Defines the project-wide rules (timeouts, cleanup ages, categories)
//   2. Starts the PHP session safely
//   3. Holds small functions so we never repeat the same code twice
//      (escaping output, CSRF tokens, flash messages, image uploads,
//       automatic cleanup, possible-match scoring)
// =========================================================================


// ---------------------------------------------------------------- 1. RULES
const SESSION_TIMEOUT_SECONDS  = 1800;        // log out after 30 min of no activity
const CLEANUP_RESOLVED_DAYS    = 10;          // delete resolved/closed items after 10 days
const CLEANUP_UNRESPONDED_DAYS = 30;          // delete never-claimed active items after 30 days
const MAX_IMAGE_BYTES          = 3 * 1024 * 1024; // 3 MB
const MATCH_MIN_SCORE          = 6;           // how similar a found item must be to count as a "possible match"

// One list of categories used by every form and filter in the project.
const CATEGORIES = [
    'electronics' => 'Electronics',
    'accessories' => 'Accessories',
    'documents'   => 'Documents / ID cards',
    'bags'        => 'Bags',
    'other'       => 'Other',
];


// --------------------------------------------------------------- 2. SESSION
// The command-line cleanup script (cleanup.php) has no browser, so it
// doesn't need a session.
if (PHP_SAPI !== 'cli' && session_status() === PHP_SESSION_NONE) {
    // use_strict_mode: PHP refuses a session ID it didn't create itself
    ini_set('session.use_strict_mode', '1');

    session_set_cookie_params([
        'lifetime' => 0,        // cookie vanishes when the browser closes
        'path'     => '/',
        'httponly' => true,     // JavaScript cannot read the session cookie
        'samesite' => 'Lax',    // browser won't send it on cross-site POSTs
    ]);
    session_start();
}


// ------------------------------------------------------ 3. SMALL UTILITIES

/** Escape a value before printing it into HTML (prevents XSS). */
function h($value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
}

/** Send the browser to another page and stop running this one. */
function redirect(string $url): void
{
    header('Location: ' . $url);
    exit;
}

/** Read a text field from a form (POST). Always returns a string. */
function post_value(string $key, bool $trim = true): string
{
    $value = $_POST[$key] ?? '';
    if (!is_string($value)) {
        return '';          // someone sent field[]=x — ignore it
    }
    return $trim ? trim($value) : $value;
}

/** Read a value from the URL (GET). Always returns a string. */
function get_value(string $key): string
{
    $value = $_GET[$key] ?? '';
    return is_string($value) ? trim($value) : '';
}

/** "2026-08-05" -> "5 Aug 2026" */
function fmt_date(?string $date): string
{
    if (!$date) {
        return '';
    }
    $time = strtotime($date);
    return $time ? date('j M Y', $time) : '';
}


// --------------------------------------------------------- LOGIN HELPERS

function is_logged_in(): bool
{
    return !empty($_SESSION['user_id']);
}

function current_user_id(): int
{
    return (int) ($_SESSION['user_id'] ?? 0);
}


// ------------------------------------------------------------------- CSRF
// CSRF = Cross-Site Request Forgery. Imagine you're logged in here and
// visit an evil page that secretly submits a form to our delete-item.php.
// Your browser would attach your session cookie and the delete would work.
// Defence: every form carries a random secret token that only OUR pages
// know. The evil page can't read it, so its forged request is rejected.

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/** Hidden input to drop inside every <form method="post">. */
function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . h(csrf_token()) . '">';
}

/** Call at the top of every script that handles a POST. */
function verify_csrf(): void
{
    $sent = $_POST['csrf_token'] ?? '';
    // hash_equals compares in constant time (safer than ===)
    if (!is_string($sent) || !hash_equals(csrf_token(), $sent)) {
        http_response_code(403);
        exit('Security check failed (the form token was missing or expired). '
           . 'Go back, refresh the page and try again.');
    }
}


// --------------------------------------------------- FLASH MESSAGES / OLD INPUT
// A "flash" message is shown once on the next page, then forgotten.
// Pattern: a script sets a message, redirects, and the next page shows it.

function flash_set(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function flash_errors(array $errors): void
{
    foreach ($errors as $error) {
        flash_set('error', $error);
    }
}

function flash_show(): void
{
    if (empty($_SESSION['flash'])) {
        return;
    }
    echo '<div class="container flash-wrap">';
    foreach ($_SESSION['flash'] as $flash) {
        $class = ($flash['type'] === 'success') ? 'alert-success' : 'alert-error';
        echo '<div class="alert ' . $class . '" role="status">' . h($flash['message']) . '</div>';
    }
    echo '</div>';
    unset($_SESSION['flash']);
}

/** Remember what the user typed so a failed form doesn't wipe their input. */
function remember_input(array $data): void
{
    $_SESSION['old_input'] = $data;
}

/** Fetch (and forget) the remembered input. */
function pull_old(): array
{
    $old = $_SESSION['old_input'] ?? [];
    unset($_SESSION['old_input']);
    return $old;
}


// ------------------------------------------------------------ VALIDATION

/** True only for a real calendar date (YYYY-MM-DD) that is not in the future. */
function is_valid_past_date(string $date): bool
{
    $parsed = DateTime::createFromFormat('!Y-m-d', $date);   // "!" resets time to 00:00
    return $parsed !== false
        && $parsed->format('Y-m-d') === $date
        && $parsed <= new DateTime('today');
}

/** Shared by report-item-save.php and edit-item.php. Returns a list of error messages. */
function validate_item_input(string $name, string $category, string $description, string $location, string $date): array
{
    $errors = [];

    if (mb_strlen($name) < 3 || mb_strlen($name) > 150) {
        $errors[] = 'Item name must be between 3 and 150 characters.';
    }
    if (!array_key_exists($category, CATEGORIES)) {
        $errors[] = 'Choose a valid category.';
    }
    if (mb_strlen($description) < 10 || mb_strlen($description) > 2000) {
        $errors[] = 'Description must be between 10 and 2000 characters.';
    }
    if (mb_strlen($location) < 2 || mb_strlen($location) > 150) {
        $errors[] = 'Location must be between 2 and 150 characters.';
    }
    if (!is_valid_past_date($date)) {
        $errors[] = 'Choose a valid date that is not in the future.';
    }
    return $errors;
}


// ------------------------------------------------------- STATUS LABELS
// The database stores short codes ("matched"); people see friendly words.

function item_status_label(string $status): string
{
    $labels = [
        'active'   => 'Active',
        'matched'  => 'Claim pending',
        'claimed'  => 'Claim approved',
        'resolved' => 'Resolved',
        'closed'   => 'Closed',
    ];
    return $labels[$status] ?? ucfirst($status);
}

/** CSS class for the coloured badge. */
function item_status_class(array $item): string
{
    switch ($item['status']) {
        case 'active':   return $item['item_type'] === 'lost' ? 'status-lost' : 'status-found';
        case 'matched':  return 'status-pending';
        case 'claimed':  return 'status-approved';
        case 'resolved': return 'status-resolved';
        default:         return 'status-closed';
    }
}

function claim_status_class(string $status): string
{
    return 'status-' . $status;     // status-pending / status-approved / status-rejected
}


// ------------------------------------------------------- IMAGE UPLOADS

/** Make sure uploads/ exists and cannot run PHP files. Returns its path. */
function ensure_upload_dir(): string
{
    $dir = __DIR__ . '/uploads';
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    // Defence in depth: even if a script somehow landed here, Apache
    // would refuse to run it.
    $htaccess = $dir . '/.htaccess';
    if (!file_exists($htaccess)) {
        file_put_contents(
            $htaccess,
            "# Never execute scripts from the uploads folder\n"
          . "<FilesMatch \"\\.(php|phtml|php3|php4|php5|phar)$\">\n"
          . "    Require all denied\n"
          . "</FilesMatch>\n"
        );
    }
    return $dir;
}

/**
 * Validate and store an OPTIONAL uploaded image.
 *  - no file chosen  -> returns null, no error (photos are optional)
 *  - file is invalid -> returns null and adds a message to $errors
 *  - file is fine    -> saves it and returns "uploads/xxxx.jpg"
 */
function process_image_upload(string $field, array &$errors): ?string
{
    if (!isset($_FILES[$field]) || $_FILES[$field]['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    $file = $_FILES[$field];

    if ($file['error'] === UPLOAD_ERR_INI_SIZE || $file['error'] === UPLOAD_ERR_FORM_SIZE) {
        $errors[] = 'Image must be smaller than 3 MB.';
        return null;
    }
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $errors[] = 'There was a problem uploading the image. Try again.';
        return null;
    }
    if ($file['size'] > MAX_IMAGE_BYTES) {
        $errors[] = 'Image must be smaller than 3 MB.';
        return null;
    }

    // Read the file's real content to find its type. The filename and the
    // browser-reported type can both be faked; the bytes cannot.
    $finfo    = new finfo(FILEINFO_MIME_TYPE);
    $mimeType = $finfo->file($file['tmp_name']);

    // The extension we SAVE the file with comes from this list, chosen by
    // the real type — never from the name the user gave the file. That
    // stops "evil.php" being stored as a runnable script.
    $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    if (!isset($allowed[$mimeType])) {
        $errors[] = 'Upload a JPG, PNG or WEBP image.';
        return null;
    }

    $dir      = ensure_upload_dir();
    $filename = 'item_' . bin2hex(random_bytes(8)) . '.' . $allowed[$mimeType];

    if (!move_uploaded_file($file['tmp_name'], $dir . '/' . $filename)) {
        $errors[] = 'The image could not be saved. Try again.';
        return null;
    }
    return 'uploads/' . $filename;
}

/** Delete an image file from disk (safe to call with null). */
function delete_image_file(?string $path): void
{
    if ($path && strpos($path, 'uploads/') === 0 && strpos($path, '..') === false) {
        $full = __DIR__ . '/' . $path;
        if (is_file($full)) {
            unlink($full);
        }
    }
}


// ------------------------------------------------ DELETING / CLEANING ITEMS

/** Delete an item row and its photo. (Its claims go too: ON DELETE CASCADE.) */
function remove_item(PDO $pdo, array $item): void
{
    $stmt = $pdo->prepare('DELETE FROM items WHERE item_id = ?');
    $stmt->execute([$item['item_id']]);
    delete_image_file($item['image_path'] ?? null);
}

/**
 * The two automatic clean-up rules:
 *   1. resolved / closed items are removed 10 days after they ended
 *   2. active items nobody ever claimed are removed after 30 days
 * Returns how many items were removed.
 *
 * Called from the dashboard and admin pages ("lazy cleanup"), and from
 * cleanup.php if you prefer a scheduled cron job.
 */
function run_cleanup(PDO $pdo): int
{
    $resolvedDays = (int) CLEANUP_RESOLVED_DAYS;
    $staleDays    = (int) CLEANUP_UNRESPONDED_DAYS;

    $sql = "SELECT * FROM items
            WHERE (status IN ('resolved', 'closed')
                   AND COALESCE(resolved_at, created_at) < (NOW() - INTERVAL $resolvedDays DAY))
               OR (status = 'active'
                   AND created_at < (NOW() - INTERVAL $staleDays DAY))";

    $expired = $pdo->query($sql)->fetchAll();
    foreach ($expired as $item) {
        remove_item($pdo, $item);
    }
    return count($expired);
}


// ------------------------------------------------------- SMART MATCHING
// Not AI — just a transparent points system you can explain in one minute.
// A lost report and a found report earn points for being alike:
//   same category .............. 3 points
//   each shared word in name ... 2 points (max 4)
//   each shared word in place .. 1 point  (max 2)
//   found within a week of loss  2 points
// A score of MATCH_MIN_SCORE (6) or more counts as a "possible match".

/** Split text into lowercase words, dropping very short / filler words. */
function words_of(string $text): array
{
    $filler = ['the', 'and', 'with', 'for', 'near', 'from', 'that', 'this', 'was', 'has', 'have', 'not'];
    $words  = preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower($text), -1, PREG_SPLIT_NO_EMPTY);
    $words  = array_filter($words, function ($word) use ($filler) {
        return mb_strlen($word) >= 3 && !in_array($word, $filler, true);
    });
    return array_values(array_unique($words));
}

function match_score(array $lost, array $found): int
{
    $score = 0;

    if ($lost['category'] === $found['category']) {
        $score += 3;
    }

    $sharedNameWords = count(array_intersect(words_of($lost['item_name']), words_of($found['item_name'])));
    $score += min($sharedNameWords, 2) * 2;

    $sharedPlaceWords = count(array_intersect(words_of($lost['location']), words_of($found['location'])));
    $score += min($sharedPlaceWords, 2);

    $daysApart = (strtotime($found['item_date']) - strtotime($lost['item_date'])) / 86400;
    if ($daysApart >= -1 && $daysApart <= 7) {
        $score += 2;
    }

    return $score;
}

/** The best (up to 3) active found items that resemble one lost report. */
function find_possible_matches(PDO $pdo, array $lostItem, int $userId): array
{
    $stmt = $pdo->prepare(
        "SELECT * FROM items
         WHERE item_type = 'found' AND status = 'active' AND user_id != ?
         ORDER BY created_at DESC
         LIMIT 200"
    );
    $stmt->execute([$userId]);

    $matches = [];
    foreach ($stmt->fetchAll() as $found) {
        $score = match_score($lostItem, $found);
        if ($score >= MATCH_MIN_SCORE) {
            $found['match_score'] = $score;
            $matches[] = $found;
        }
    }

    usort($matches, function ($a, $b) {
        return $b['match_score'] - $a['match_score'];
    });
    return array_slice($matches, 0, 3);
}
