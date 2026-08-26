<?php
require_once __DIR__ . '/functions.php';

function authenticateUser($username, $password, $remember = false) {
    global $mysqli;
    $stmt = $mysqli->prepare('SELECT id, username, email, role, password_hash, failed_attempts, lock_until FROM users WHERE username = ? OR email = ? LIMIT 1');
    $stmt->bind_param('ss', $username, $username);
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
        $stmt3 = $mysqli->prepare('UPDATE users SET failed_attempts = ?, lock_until = ? WHERE id = ?');
        $stmt3->bind_param('isi', $attempts, $lockUntil, $id);
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

function requestPasswordReset($email) {
    global $mysqli;
    $stmt = $mysqli->prepare('SELECT id, username, email FROM users WHERE email = ? LIMIT 1');
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$user) {
        return false;
    }
    $otp = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $stmt = $mysqli->prepare('UPDATE users SET reset_token = ?, reset_expires = DATE_ADD(NOW(), INTERVAL 5 MINUTE) WHERE id = ?');
    $stmt->bind_param('si', $otp, $user['id']);
    $stmt->execute();
    $stmt->close();
    return ['id' => $user['id'], 'otp' => $otp, 'email' => $user['email'], 'username' => $user['username']];
}

function verifyResetOtp($userId, $otp) {
    global $mysqli;
    $stmt = $mysqli->prepare('SELECT id FROM users WHERE id = ? AND reset_token = ? AND reset_expires > NOW() LIMIT 1');
    $stmt->bind_param('is', $userId, $otp);
    $stmt->execute();
    $stmt->bind_result($id);
    $found = $stmt->fetch();
    $stmt->close();
    return $found ? $id : false;
}

function resetPasswordWithOtp($userId, $otp, $password) {
    if (!verifyResetOtp($userId, $otp)) {
        return false;
    }
    global $mysqli;
    $passwordHash = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $mysqli->prepare('UPDATE users SET password_hash = ?, reset_token = NULL, reset_expires = NULL WHERE id = ?');
    $stmt->bind_param('si', $passwordHash, $userId);
    $stmt->execute();
    $stmt->close();
    return true;
}
