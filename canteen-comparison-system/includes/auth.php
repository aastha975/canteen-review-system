<?php
// ============================================================
// auth.php
// Session handling, registration, login/logout, and role-based
// access control helpers used across the app.
// ============================================================

require_once __DIR__ . '/db_connect.php';

const VALID_ROLES = ['student', 'faculty', 'canteen_admin', 'super_admin'];

/**
 * Starts a session with hardened cookie settings.
 * Call this at the top of every public-facing PHP file.
 */
function start_secure_session(): void {
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'httponly' => true,   // JS can't read the session cookie
        'samesite' => 'Lax',  // basic CSRF mitigation
    ]);

    session_start();

    // Simple idle-timeout: log out after 30 minutes of inactivity
    $timeoutSeconds = 1800;
    if (isset($_SESSION['last_activity']) &&
        (time() - $_SESSION['last_activity']) > $timeoutSeconds) {
        logout_user();
    }
    $_SESSION['last_activity'] = time();
}

/**
 * Registers a new user. Returns the new user_id on success.
 * Throws InvalidArgumentException on bad input (caller should
 * catch this and show a friendly form error).
 */
function register_user(string $name, string $email, string $password, string $role, ?string $department = null): int {
    $name  = trim($name);
    $email = trim(strtolower($email));

    if ($name === '' || strlen($name) > 100) {
        throw new InvalidArgumentException('Please enter a valid name.');
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new InvalidArgumentException('Please enter a valid email address.');
    }
    if (strlen($password) < 8) {
        throw new InvalidArgumentException('Password must be at least 8 characters.');
    }
    if (!in_array($role, VALID_ROLES, true)) {
        throw new InvalidArgumentException('Invalid role.');
    }

    // Public registration should only ever create students/faculty —
    // canteen_admin and super_admin accounts are created by a super_admin
    // through the admin panel, not the open registration form.
    if (!in_array($role, ['student', 'faculty'], true)) {
        throw new InvalidArgumentException('That role cannot self-register.');
    }

    $pdo = get_db_connection();

    $check = $pdo->prepare('SELECT 1 FROM users WHERE email = :email');
    $check->execute(['email' => $email]);
    if ($check->fetch()) {
        throw new InvalidArgumentException('An account with that email already exists.');
    }

    $hash = password_hash($password, PASSWORD_BCRYPT);

    $stmt = $pdo->prepare(
        'INSERT INTO users (name, email, password_hash, role, department)
         VALUES (:name, :email, :hash, :role, :department)
         RETURNING user_id'
    );
    $stmt->execute([
        'name'       => $name,
        'email'      => $email,
        'hash'       => $hash,
        'role'       => $role,
        'department' => $department,
    ]);

    return (int) $stmt->fetchColumn();
}

/**
 * Attempts to log a user in. Returns true and populates $_SESSION
 * on success; returns false on bad credentials (deliberately does
 * NOT distinguish "no such email" from "wrong password" in its
 * response, to avoid leaking which emails are registered).
 */
function attempt_login(string $email, string $password): bool {
    $email = trim(strtolower($email));
    $pdo = get_db_connection();

    $stmt = $pdo->prepare(
        'SELECT user_id, name, email, password_hash, role, department
         FROM users WHERE email = :email'
    );
    $stmt->execute(['email' => $email]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password_hash'])) {
        return false;
    }

    // Prevent session fixation
    session_regenerate_id(true);

    $_SESSION['user_id']    = (int) $user['user_id'];
    $_SESSION['name']       = $user['name'];
    $_SESSION['email']      = $user['email'];
    $_SESSION['role']       = $user['role'];
    $_SESSION['department'] = $user['department'];

    return true;
}

function logout_user(): void {
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params['path'], $params['domain'],
            $params['secure'], $params['httponly']);
    }

    session_destroy();
}

function current_user(): ?array {
    if (empty($_SESSION['user_id'])) {
        return null;
    }
    return [
        'user_id'    => $_SESSION['user_id'],
        'name'       => $_SESSION['name'],
        'email'      => $_SESSION['email'],
        'role'       => $_SESSION['role'],
        'department' => $_SESSION['department'],
    ];
}

function is_logged_in(): bool {
    return !empty($_SESSION['user_id']);
}

/**
 * Redirects to login if not authenticated. Call at the top of any
 * page that requires a logged-in user.
 */
function require_login(): void {
    if (!is_logged_in()) {
        header('Location: /login.php');
        exit;
    }
}

/**
 * Redirects to login if not authenticated, or shows 403 if the
 * user's role isn't in the allowed list. Call at the top of
 * role-restricted pages, e.g. require_role(['canteen_admin']).
 */
function require_role(array $allowedRoles): void {
    require_login();

    if (!in_array($_SESSION['role'], $allowedRoles, true)) {
        http_response_code(403);
        echo 'You do not have permission to view this page.';
        exit;
    }
}

/**
 * For canteen_admin pages: confirms the logged-in admin actually
 * owns the canteen (cid) they're trying to act on, using the
 * canteens.admin_user_id link. Prevents an admin from editing a
 * different canteen by guessing/changing a cid in the URL.
 */
function require_owns_canteen(int $cid): void {
    require_role(['canteen_admin', 'super_admin']);

    if ($_SESSION['role'] === 'super_admin') {
        return; // super_admin can manage any canteen
    }

    $pdo = get_db_connection();
    $stmt = $pdo->prepare('SELECT 1 FROM canteens WHERE cid = :cid AND admin_user_id = :uid');
    $stmt->execute(['cid' => $cid, 'uid' => $_SESSION['user_id']]);

    if (!$stmt->fetch()) {
        http_response_code(403);
        echo 'You do not manage this canteen.';
        exit;
    }
}

/**
 * CSRF protection: generates a per-session token once, reuses it
 * for the rest of the session. Call csrf_field() inside every form
 * that POSTs, and require_csrf() at the top of every handler that
 * accepts POST.
 */
function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/** Echoes a ready-to-use hidden input for forms. */
function csrf_field(): string {
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrf_token()) . '">';
}

/**
 * Verifies the CSRF token on an incoming POST request. Call this
 * as the very first line of handling any $_POST data. Uses a
 * timing-safe comparison so the check itself can't leak the token.
 */
function require_csrf(): void {
    $submitted = $_POST['csrf_token'] ?? '';
    $expected  = $_SESSION['csrf_token'] ?? '';

    if ($expected === '' || !hash_equals($expected, $submitted)) {
        http_response_code(403);
        die('Your session expired or this request could not be verified. Please go back and try again.');
    }
}
