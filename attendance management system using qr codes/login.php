<?php
require_once __DIR__ . '/includes/auth.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('index.php');
}
if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
    flash('Invalid form submission.', 'danger');
    redirect('index.php');
}
$username = sanitize($_POST['username'] ?? '');
$password = $_POST['password'] ?? '';
$remember = isset($_POST['remember']);
$result = authenticateUser($username, $password, $remember);
if (!empty($result['error'])) {
    flash($result['error'], 'danger');
    redirect('index.php');
}
$user = currentUser();
if ($user['role'] === 'teacher') {
    redirect('teacher/dashboard.php');
}
redirect('admin/dashboard.php');
