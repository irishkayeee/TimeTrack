<?php
require_once __DIR__ . '/../config/config.php';

function sanitize($value) {
    global $mysqli;
    return htmlspecialchars(trim($mysqli->real_escape_string($value)), ENT_QUOTES, 'UTF-8');
}

function redirect($url) {
    header('Location: ' . $url);
    exit;
}

function currentUser() {
    return isset($_SESSION['user']) ? $_SESSION['user'] : null;
}

function isLoggedIn() {
    return !empty($_SESSION['user']);
}

function isAdmin() {
    $user = currentUser();
    return $user && in_array($user['role'], ['superadmin', 'admin']);
}

function isTeacher() {
    $user = currentUser();
    return $user && $user['role'] === 'teacher';
}

function requireLogin() {
    if (!isLoggedIn()) {
        redirect('index.php');
    }
}

function requireRole($roles = []) {
    requireLogin();
    $user = currentUser();
    if (!in_array($user['role'], $roles)) {
        redirect('index.php');
    }
}

function csrfToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCsrf($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

function flash($message, $type = 'success') {
    $_SESSION['flash'] = ['message' => $message, 'type' => $type];
}

function flashMessage() {
    if (!empty($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

function getSetting($key, $default = '') {
    global $mysqli;
    $stmt = $mysqli->prepare('SELECT value FROM settings WHERE name = ? LIMIT 1');
    $stmt->bind_param('s', $key);
    $stmt->execute();
    $stmt->bind_result($value);
    if ($stmt->fetch()) {
        $stmt->close();
        return $value;
    }
    $stmt->close();
    return $default;
}

function formatDate($date) {
    return date('F j, Y', strtotime($date));
}

function formatTime($time) {
    return date('h:i A', strtotime($time));
}

function badgeStatus($status) {
    $classes = [
        'active' => 'success',
        'inactive' => 'secondary',
        'present' => 'success',
        'absent' => 'danger',
        'late' => 'warning',
        'excused' => 'info'
    ];
    $class = isset($classes[$status]) ? $classes[$status] : 'secondary';
    return '<span class="badge bg-' . $class . '">' . ucfirst($status) . '</span>';
}

function logActivity($userId, $action) {
    global $mysqli;
    $ip = $_SERVER['REMOTE_ADDR'];
    $stmt = $mysqli->prepare('INSERT INTO logs (user_id, action, ip, created_at) VALUES (?, ?, ?, NOW())');
    $stmt->bind_param('iss', $userId, $action, $ip);
    $stmt->execute();
    $stmt->close();
}

function createRememberToken($userId) {
    global $mysqli;
    $token = bin2hex(random_bytes(32));
    $hash = password_hash($token, PASSWORD_DEFAULT);
    $stmt = $mysqli->prepare('UPDATE users SET remember_token = ? WHERE id = ?');
    $stmt->bind_param('si', $hash, $userId);
    $stmt->execute();
    $stmt->close();
    setcookie('remember_me', $userId . ':' . $token, time() + 86400 * 30, '/', '', false, true);
}

function checkRememberMe() {
    global $mysqli;
    if (isLoggedIn()) {
        return;
    }
    if (empty($_COOKIE['remember_me'])) {
        return;
    }
    list($userId, $token) = explode(':', $_COOKIE['remember_me']);
    if (!$userId || !$token) {
        return;
    }
    $stmt = $mysqli->prepare('SELECT id, username, email, role, remember_token FROM users WHERE id = ? LIMIT 1');
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $stmt->bind_result($id, $username, $email, $role, $rememberHash);
    if ($stmt->fetch()) {
        if ($rememberHash && password_verify($token, $rememberHash)) {
            $_SESSION['user'] = ['id' => $id, 'username' => $username, 'email' => $email, 'role' => $role];
        }
    }
    $stmt->close();
}
