<?php
require_once __DIR__ . '/functions.php';

function authenticateUser($username, $password, $remember = false) {
    global $mysqli;
    $stmt = $mysqli->prepare('SELECT id, username, email, role, password_hash, failed_attempts, lock_until FROM users WHERE username = ? LIMIT 1');
    $stmt->bind_param('s', $username);
    $stmt->execute();
    $stmt->bind_result($id, $user, $email, $role, $hash, $attempts, $lockUntil);
    if ($stmt->fetch()) {
        if ($lockUntil && strtotime($lockUntil) > time()) {
            return ['error' => 'Account locked. Please try again later.'];
        }
        if (password_verify($password, $hash)) {
            $stmt->close();
            $stmt2 = $mysqli->prepare('UPDATE users SET failed_attempts = 0, lock_until = NULL WHERE id = ?');
            $stmt2->bind_param('i', $id);
            $stmt2->execute();
            $stmt2->close();
            $_SESSION['user'] = ['id' => $id, 'username' => $user, 'email' => $email, 'role' => $role];
            if ($remember) {
                createRememberToken($id);
            }
            logActivity($id, 'Logged in');
            return ['success' => true];
        }
        $stmt->close();
        $attempts++;
        $lockUntil = null;
        if ($attempts >= 5) {
            $lockUntil = date('Y-m-d H:i:s', strtotime('+15 minutes'));
        }
        $stmt3 = $mysqli->prepare('UPDATE users SET failed_attempts = ?, lock_until = ? WHERE username = ?');
        $stmt3->bind_param('iss', $attempts, $lockUntil, $username);
        $stmt3->execute();
        $stmt3->close();
        return ['error' => 'Invalid credentials.'];
    }
    $stmt->close();
    return ['error' => 'Invalid credentials.'];
}

function logoutUser() {
    if (!empty($_SESSION['user'])) {
        logActivity($_SESSION['user']['id'], 'Logged out');
    }
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
    setcookie('remember_me', '', time() - 3600, '/', '', false, true);
}

function requestPasswordReset($username) {
    global $mysqli;
    $token = bin2hex(random_bytes(20));
    $stmt = $mysqli->prepare('UPDATE users SET reset_token = ?, reset_expires = DATE_ADD(NOW(), INTERVAL 30 MINUTE) WHERE username = ?');
    $stmt->bind_param('ss', $token, $username);
    $stmt->execute();
    $updated = $stmt->affected_rows > 0;
    $stmt->close();
    return $updated ? $token : false;
}

function verifyResetToken($token) {
    global $mysqli;
    $stmt = $mysqli->prepare('SELECT id FROM users WHERE reset_token = ? AND reset_expires > NOW() LIMIT 1');
    $stmt->bind_param('s', $token);
    $stmt->execute();
    $stmt->bind_result($id);
    if ($stmt->fetch()) {
        $stmt->close();
        return $id;
    }
    $stmt->close();
    return false;
}

function resetPassword($token, $password) {
    global $mysqli;
    $userId = verifyResetToken($token);
    if ($userId) {
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $mysqli->prepare('UPDATE users SET password_hash = ?, reset_token = NULL, reset_expires = NULL WHERE id = ?');
        $stmt->bind_param('si', $passwordHash, $userId);
        $stmt->execute();
        $stmt->close();
        return true;
    }
    return false;
}
