<?php
/* includes/functions.php - small helpers used by every page */
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax']);
    session_start();
}

// Fallback so the project still works if the PHP mbstring extension is disabled
if (!function_exists('mb_strlen')) {
    function mb_strlen($s) { return preg_match_all('/./su', (string)$s); }
}

function e($value) { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }   // prevents XSS
function url($path = '') { return BASE_URL . '/' . ltrim($path, '/'); }
function redirect($path) { header('Location: ' . url($path)); exit; }

function set_flash($type, $msg) { $_SESSION['flash'][] = ['type' => $type, 'msg' => $msg]; }
function get_flash() { $f = $_SESSION['flash'] ?? []; unset($_SESSION['flash']); return $f; }

// CSRF protection: every POST form includes csrf_field() and calls verify_csrf()
function csrf_token() {
    if (empty($_SESSION['csrf'])) { $_SESSION['csrf'] = bin2hex(random_bytes(32)); }
    return $_SESSION['csrf'];
}
function csrf_field() { return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">'; }
function verify_csrf() {
    $ok = isset($_POST['csrf_token'], $_SESSION['csrf']) && hash_equals($_SESSION['csrf'], (string)$_POST['csrf_token']);
    if (!$ok) {
        http_response_code(400);
        die('Invalid or expired form token. Go back, refresh the page and try again.');
    }
}

function clean($v) { return trim(preg_replace('/\s+/', ' ', (string)$v)); }           // one-line text
function money($n) { return CURRENCY . number_format((float)$n, 2); }
function booking_code($id) { return 'HS-' . str_pad((string)$id, 5, '0', STR_PAD_LEFT); }
function is_valid_phone($p) { return (bool)preg_match('/^[0-9+\-\s]{7,15}$/', $p); }
function nav_active($file) { return basename($_SERVER['SCRIPT_NAME']) === $file ? ' active' : ''; }

function status_badge($status) {
    $map = ['Pending' => 'warning text-dark', 'Accepted' => 'info text-dark', 'In Progress' => 'primary',
            'Completed' => 'success', 'Cancelled' => 'secondary', 'Rejected' => 'danger'];
    return '<span class="badge bg-' . ($map[$status] ?? 'secondary') . '">' . e($status) . '</span>';
}

const BOOKING_STATUSES = ['Pending', 'Accepted', 'In Progress', 'Completed', 'Cancelled', 'Rejected'];
const SERVICE_ICONS = [
    'bi-droplet-fill' => 'Water drop', 'bi-lightning-charge-fill' => 'Lightning', 'bi-stars' => 'Sparkles',
    'bi-snow' => 'Snowflake', 'bi-paint-bucket' => 'Paint bucket', 'bi-hammer' => 'Hammer',
    'bi-gear-wide-connected' => 'Gears', 'bi-bug-fill' => 'Bug', 'bi-tools' => 'Tools',
    'bi-house-gear-fill' => 'House', 'bi-wrench-adjustable' => 'Wrench', 'bi-brush' => 'Brush',
];
