<?php
require_once __DIR__ . '/includes/auth.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('landing.php?login=1');
}
if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
    flash('Invalid form submission.', 'danger');
    redirect('landing.php?login=1');
}
$username = sanitize($_POST['username'] ?? '');
$password = $_POST['password'] ?? '';
$remember = isset($_POST['remember']);
$result = authenticateUser($username, $password, $remember);
if (!empty($result['error'])) {
    flash($result['error'], 'danger');
    redirect('landing.php?login=1');
}
$user = currentUser();
$welcomeName = $user['username'];
if ($user['role'] === 'teacher') {
    $stmt = $mysqli->prepare('SELECT first_name FROM teachers WHERE user_id = ? LIMIT 1');
    $stmt->bind_param('i', $user['id']);
    $stmt->execute();
    $stmt->bind_result($firstName);
    if ($stmt->fetch()) {
        $welcomeName = $firstName;
    }
    $stmt->close();
} elseif ($user['role'] === 'student') {
    $stmt = $mysqli->prepare('SELECT first_name FROM students WHERE user_id = ? LIMIT 1');
    $stmt->bind_param('i', $user['id']);
    $stmt->execute();
    $stmt->bind_result($firstName);
    if ($stmt->fetch()) {
        $welcomeName = $firstName;
    }
    $stmt->close();
} else {
    $welcomeName = ucfirst($user['role']);
}
$_SESSION['welcome_banner'] = [
    'name' => $welcomeName,
    'message' => 'Great to see you again. Everything\'s ready when you are.',
];

if ($user['role'] === 'teacher') {
    redirect('teacher/dashboard.php');
}
if ($user['role'] === 'student') {
    redirect('student/dashboard.php');
}
redirect('admin/dashboard.php');
