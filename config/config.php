<?php
// Database connection and base settings

// Keep error display consistent across machines regardless of each PHP install's
// own php.ini defaults. Real errors/warnings still show; harmless deprecation
// notices (e.g. htmlspecialchars(null) on PHP 8.1+) are hidden everywhere.
error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);
ini_set('display_errors', '1');

session_start();

define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'attendance_system');
define('BASE_URL', 'http://localhost/QR%20CODE%20SYSTEM');

// Gmail SMTP settings for outgoing email (password reset, etc.)
// 1. Turn on 2-Step Verification on the Google account: myaccount.google.com/security
// 2. Create an App Password: myaccount.google.com/apppasswords (choose "Mail")
// 3. Paste the 16-character app password below (remove spaces) — NOT the regular Gmail password.
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USERNAME', 'timetrack04@gmail.com');
define('SMTP_PASSWORD', 'nsruiuzhbwttkbts'); // App Password for timetrack04@gmail.com
define('SMTP_FROM_EMAIL', SMTP_USERNAME);
define('SMTP_FROM_NAME', 'TimeTrack');

date_default_timezone_set('Asia/Manila');

$mysqli = new mysqli(DB_HOST, DB_USER, DB_PASS);
if ($mysqli->connect_error) {
    die('Database connection failed: ' . $mysqli->connect_error);
}

$createDbQuery = "CREATE DATABASE IF NOT EXISTS `" . DB_NAME . "` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci";
if (!$mysqli->query($createDbQuery)) {
    die('Unable to create or access database: ' . $mysqli->error);
}

$mysqli->select_db(DB_NAME);
$mysqli->set_charset('utf8mb4');
$mysqli->query("SET time_zone = '+08:00'");
