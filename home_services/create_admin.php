<?php
/*
 * create_admin.php - creates the FIRST administrator account.
 * Run it ONCE from the command line (it refuses to run in a browser):
 *   C:\xampp\php\php.exe C:\xampp\htdocs\home_services\create_admin.php
 * Afterwards you may delete this file.
 */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require __DIR__ . '/config/database.php';

function ask($label) { echo $label . ': '; return trim((string)fgets(STDIN)); }

$name = ask('Admin full name');
$email = strtolower(ask('Admin email'));
$phone = ask('Admin phone');
$password = ask('Admin password (min 10 characters; it is visible while typing)');

if (strlen($name) < 2 || !filter_var($email, FILTER_VALIDATE_EMAIL) || !preg_match('/^[0-9+\-\s]{7,15}$/', $phone) || strlen($password) < 10) {
    exit("Invalid details. Name, valid email, phone (7-15 digits) and a password of 10+ characters are required.\n");
}
$exists = $pdo->prepare('SELECT id FROM users WHERE email = ?');
$exists->execute([$email]);
if ($exists->fetch()) { exit("That email is already registered.\n"); }

$pdo->prepare("INSERT INTO users (name, email, phone, password, role, address) VALUES (?,?,?,?, 'admin', 'Head Office')")
    ->execute([$name, $email, $phone, password_hash($password, PASSWORD_DEFAULT)]);
echo "Administrator created. Log in at http://localhost/home_services/admin/admin_login.php\n";
