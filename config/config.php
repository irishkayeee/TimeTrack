<?php
// Database connection and base settings
session_start();

define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'attendance_system');
define('BASE_URL', 'http://localhost/attendance-system');

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
