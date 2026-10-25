<?php
/* includes/auth.php - login state and role checks (needs config/database.php + functions.php first) */

function is_logged_in() { return !empty($_SESSION['user_id']); }
function current_role() { return $_SESSION['role'] ?? null; }

function dashboard_for($role) {
    if ($role === 'admin') return 'admin/dashboard.php';
    if ($role === 'worker') return 'worker/dashboard.php';
    return 'customer/dashboard.php';
}

function login_user($user) {
    session_regenerate_id(true);                 // stops session fixation
    $_SESSION['user_id'] = (int)$user['id'];
    $_SESSION['name']    = $user['name'];
    $_SESSION['role']    = $user['role'];
}

function logout_user() {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}

/* Checks credentials. Returns [user, null] on success or [null, "error message"]. */
function attempt_login($pdo, $email, $password, $admin_only) {
    $st = $pdo->prepare('SELECT * FROM users WHERE email = ?');
    $st->execute([$email]);
    $user = $st->fetch();
    if (!$user || !password_verify($password, $user['password'])) {
        return [null, 'Invalid email or password.'];
    }
    // admins may only sign in on the admin page; customers/workers only on the public page
    if (($user['role'] === 'admin') !== $admin_only) {
        return [null, 'Invalid email or password.'];
    }
    if ($user['status'] !== 'active') {
        return [null, 'This account has been blocked. Please contact support.'];
    }
    if ($user['role'] === 'worker') {
        $w = $pdo->prepare('SELECT approval_status FROM worker_profiles WHERE user_id = ?');
        $w->execute([$user['id']]);
        if ($w->fetchColumn() === 'deactivated') {
            return [null, 'Your worker account has been deactivated. Please contact support.'];
        }
    }
    return [$user, null];
}

/* Call at the top of every protected page: require_role('customer') etc. */
function require_role($role) {
    global $pdo;
    if (!is_logged_in()) {
        set_flash('warning', 'Please log in to continue.');
        redirect($role === 'admin' ? 'admin/admin_login.php' : 'login.php');
    }
    // re-check the account on every request so blocked/deactivated users are signed out at once
    $st = $pdo->prepare('SELECT u.status, u.role, wp.approval_status FROM users u
                         LEFT JOIN worker_profiles wp ON wp.user_id = u.id WHERE u.id = ?');
    $st->execute([$_SESSION['user_id']]);
    $row = $st->fetch();
    if (!$row || $row['status'] !== 'active' || $row['approval_status'] === 'deactivated') {
        logout_user();
        session_start();
        set_flash('danger', 'Your session has ended. Please log in again.');
        redirect('login.php');
    }
    if ($row['role'] !== $role) {
        set_flash('danger', 'Access denied: you do not have permission to open that page.');
        redirect(dashboard_for($row['role']));
    }
}
