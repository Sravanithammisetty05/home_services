<?php
/*
 * config/database.php - the ONE place that holds database settings.
 * Defaults match a fresh XAMPP install (user "root", empty password).
 * For a real server, set environment variables instead of editing credentials here:
 * HS_DB_HOST, HS_DB_NAME, HS_DB_USER, HS_DB_PASS, HS_BASE_URL, HS_TZ
 */
define('DB_HOST', getenv('HS_DB_HOST') ?: 'localhost');
define('DB_NAME', getenv('HS_DB_NAME') ?: 'home_services');
define('DB_USER', getenv('HS_DB_USER') ?: 'root');
define('DB_PASS', getenv('HS_DB_PASS') !== false ? getenv('HS_DB_PASS') : '');
define('BASE_URL', rtrim(getenv('HS_BASE_URL') ?: '/home_services', '/')); // URL path of the project
define('CURRENCY', '₹');                                                   // change to $ etc. if needed
date_default_timezone_set(getenv('HS_TZ') ?: 'Asia/Kolkata');

try {
    $pdo = new PDO(
        'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );
} catch (PDOException $e) {
    error_log('DB connection failed: ' . $e->getMessage());
    http_response_code(500);
    die('Database connection failed. Start MySQL in XAMPP and import database.sql in phpMyAdmin.');
}
