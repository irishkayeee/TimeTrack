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

function rootPrefix() {
    $projectRoot = realpath(__DIR__ . '/..');
    $scriptDir = realpath(dirname($_SERVER['SCRIPT_FILENAME']));
    return ($scriptDir === $projectRoot) ? '' : '../';
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
        redirect(rootPrefix() . 'landing.php?login=1');
    }
}

function requireRole($roles = []) {
    requireLogin();
    $user = currentUser();
    if (!in_array($user['role'], $roles)) {
        redirect(rootPrefix() . 'landing.php?login=1');
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

function welcomeBannerMessage() {
    if (!empty($_SESSION['welcome_banner'])) {
        $banner = $_SESSION['welcome_banner'];
        unset($_SESSION['welcome_banner']);
        return $banner;
    }
    return null;
}

function flashClassJoinCode($className, $joinCode) {
    $_SESSION['flash_class_join_code'] = ['name' => $className, 'code' => $joinCode];
}

function classJoinCodeMessage() {
    if (!empty($_SESSION['flash_class_join_code'])) {
        $data = $_SESSION['flash_class_join_code'];
        unset($_SESSION['flash_class_join_code']);
        return $data;
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

function formatDateTime($datetime) {
    return $datetime ? date('M j, Y g:i A', strtotime($datetime)) : '—';
}

function pdfEscapeText($text) {
    return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], (string) $text);
}

function generateSimpleTablePdf($title, $headers, $rows, $colWidths) {
    $pageWidth = 792;
    $pageHeight = 612;
    $marginLeft = 30;
    $marginTop = 40;
    $lineHeight = 14;
    $rowsPerPage = intval(($pageHeight - 90) / $lineHeight);

    $chunks = $rows ? array_chunk($rows, $rowsPerPage) : [[]];
    $numPages = count($chunks);
    $fontObjNum = 3 + $numPages * 2;

    $objects = [];
    $kids = [];
    for ($p = 0; $p < $numPages; $p++) {
        $kids[] = (3 + $p * 2) . ' 0 R';
    }
    $objects[1] = '<< /Type /Catalog /Pages 2 0 R >>';
    $objects[2] = '<< /Type /Pages /Kids [' . implode(' ', $kids) . "] /Count $numPages >>";

    for ($p = 0; $p < $numPages; $p++) {
        $pageObjNum = 3 + $p * 2;
        $contentObjNum = $pageObjNum + 1;
        $objects[$pageObjNum] = "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 $pageWidth $pageHeight] /Resources << /Font << /F1 $fontObjNum 0 R >> >> /Contents $contentObjNum 0 R >>";

        $y = $pageHeight - $marginTop;
        $stream = "BT\n";
        if ($p === 0) {
            $stream .= "/F1 14 Tf\n1 0 0 1 $marginLeft $y Tm\n(" . pdfEscapeText($title) . ") Tj\n";
            $y -= 24;
        }
        $stream .= "/F1 9 Tf\n";
        $x = $marginLeft;
        $headerParts = [];
        foreach ($headers as $i => $h) {
            $headerParts[] = "1 0 0 1 $x $y Tm\n(" . pdfEscapeText($h) . ") Tj\n";
            $x += $colWidths[$i];
        }
        $stream .= implode('', $headerParts);
        $y -= $lineHeight;

        foreach ($chunks[$p] as $row) {
            $x = $marginLeft;
            foreach ($row as $i => $cell) {
                $stream .= "1 0 0 1 $x $y Tm\n(" . pdfEscapeText($cell) . ") Tj\n";
                $x += $colWidths[$i];
            }
            $y -= $lineHeight;
        }
        $stream .= 'ET';
        $objects[$contentObjNum] = '<< /Length ' . strlen($stream) . " >>\nstream\n$stream\nendstream";
    }

    $objects[$fontObjNum] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>';
    ksort($objects);

    $pdf = "%PDF-1.4\n";
    $offsets = [];
    foreach ($objects as $num => $body) {
        $offsets[$num] = strlen($pdf);
        $pdf .= "$num 0 obj\n$body\nendobj\n";
    }
    $maxObj = max(array_keys($objects));
    $xrefStart = strlen($pdf);
    $pdf .= "xref\n0 " . ($maxObj + 1) . "\n0000000000 65535 f \n";
    for ($i = 1; $i <= $maxObj; $i++) {
        $pdf .= isset($offsets[$i]) ? str_pad($offsets[$i], 10, '0', STR_PAD_LEFT) . " 00000 n \n" : "0000000000 00000 f \n";
    }
    $pdf .= "trailer\n<< /Size " . ($maxObj + 1) . " /Root 1 0 R >>\nstartxref\n$xrefStart\n%%EOF";
    return $pdf;
}

function isMailConfigured() {
    return SMTP_USERNAME !== '' && SMTP_PASSWORD !== '';
}

function smtpReadResponse($socket) {
    $data = '';
    while ($line = fgets($socket, 515)) {
        $data .= $line;
        if (isset($line[3]) && $line[3] === ' ') {
            break;
        }
    }
    return $data;
}

function smtpCommand($socket, $command, $expectedCode) {
    fwrite($socket, $command . "\r\n");
    $response = smtpReadResponse($socket);
    $code = substr($response, 0, 3);
    if ($code !== (string) $expectedCode) {
        throw new Exception("SMTP error for command [$command]: $response");
    }
    return $response;
}

function sendMail($toEmail, $toName, $subject, $htmlBody) {
    if (!isMailConfigured()) {
        return false;
    }
    try {
        $socket = stream_socket_client('tcp://' . SMTP_HOST . ':' . SMTP_PORT, $errno, $errstr, 15);
        if (!$socket) {
            throw new Exception("Could not connect to SMTP host: $errstr");
        }
        smtpReadResponse($socket);
        smtpCommand($socket, 'EHLO localhost', 250);
        smtpCommand($socket, 'STARTTLS', 220);
        if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
            throw new Exception('Unable to start TLS encryption.');
        }
        smtpCommand($socket, 'EHLO localhost', 250);
        smtpCommand($socket, 'AUTH LOGIN', 334);
        smtpCommand($socket, base64_encode(SMTP_USERNAME), 334);
        smtpCommand($socket, base64_encode(SMTP_PASSWORD), 235);
        smtpCommand($socket, 'MAIL FROM:<' . SMTP_FROM_EMAIL . '>', 250);
        smtpCommand($socket, 'RCPT TO:<' . $toEmail . '>', 250);
        smtpCommand($socket, 'DATA', 354);

        $headers = [
            'From: ' . SMTP_FROM_NAME . ' <' . SMTP_FROM_EMAIL . '>',
            'To: ' . $toName . ' <' . $toEmail . '>',
            'Subject: ' . $subject,
            'MIME-Version: 1.0',
            'Content-Type: text/html; charset=UTF-8',
        ];
        $body = str_replace("\r\n.", "\r\n..", $htmlBody);
        $message = implode("\r\n", $headers) . "\r\n\r\n" . $body . "\r\n.";
        smtpCommand($socket, $message, 250);
        smtpCommand($socket, 'QUIT', 221);
        fclose($socket);
        return true;
    } catch (Exception $e) {
        error_log('sendMail failed: ' . $e->getMessage());
        return false;
    }
}

function badgeStatus($status) {
    $classes = [
        'active' => 'success',
        'inactive' => 'secondary',
        'present' => 'success',
        'absent' => 'danger',
        'late' => 'warning',
        'excused' => 'info',
        'reminder' => 'info',
        'summary' => 'primary',
        'assignment' => 'success',
        'enrolled' => 'primary'
    ];
    $class = isset($classes[$status]) ? $classes[$status] : 'secondary';
    return '<span class="badge status-badge bg-' . $class . '">' . ucfirst($status) . '</span>';
}

function logActivity($userId, $action) {
    global $mysqli;
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $userIdParam = $userId ?: null;
    $stmt = $mysqli->prepare('INSERT INTO logs (user_id, action, ip, created_at) VALUES (?, ?, ?, NOW())');
    $stmt->bind_param('iss', $userIdParam, $action, $ip);
    $stmt->execute();
    $stmt->close();
}

function notifyStudent($studentId, $type, $title, $message, $subjectId = null) {
    global $mysqli;
    $stmt = $mysqli->prepare('INSERT INTO notifications (student_id, subject_id, type, title, message, created_at) VALUES (?, ?, ?, ?, ?, NOW())');
    $stmt->bind_param('iisss', $studentId, $subjectId, $type, $title, $message);
    $stmt->execute();
    $stmt->close();
}

function notifyGuardianOfAttendance($student, $subjectName, $status, $scanTime) {
    if (empty($student['guardian_email']) || !isMailConfigured()) {
        return false;
    }
    $statusLabels = ['present' => 'present', 'late' => 'late', 'absent' => 'absent'];
    $statusLabel = $statusLabels[$status] ?? $status;
    $studentName = trim($student['first_name'] . ' ' . $student['last_name']);
    $guardianName = $student['guardian_name'] ?: 'Guardian';
    $subject = $studentName . ' was marked ' . ucfirst($statusLabel) . ' in ' . $subjectName;
    $body = '<p>Hi ' . htmlspecialchars($guardianName) . ',</p>'
        . '<p>This is to inform you that <strong>' . htmlspecialchars($studentName) . '</strong> was marked '
        . '<strong>' . htmlspecialchars(ucfirst($statusLabel)) . '</strong> in <strong>' . htmlspecialchars($subjectName) . '</strong> '
        . 'today at ' . date('g:i A', strtotime($scanTime)) . '.</p>'
        . '<p>— TimeTrack Attendance System</p>';
    return sendMail($student['guardian_email'], $guardianName, $subject, $body);
}

function unreadNotificationCount($studentId) {
    global $mysqli;
    $stmt = $mysqli->prepare('SELECT COUNT(*) FROM notifications WHERE student_id = ? AND is_read = 0');
    $stmt->bind_param('i', $studentId);
    $stmt->execute();
    $stmt->bind_result($count);
    $stmt->fetch();
    $stmt->close();
    return (int) $count;
}

function markNotificationRead($notificationId, $studentId) {
    global $mysqli;
    $stmt = $mysqli->prepare('UPDATE notifications SET is_read = 1 WHERE id = ? AND student_id = ?');
    $stmt->bind_param('ii', $notificationId, $studentId);
    $stmt->execute();
    $stmt->close();
}

function notifyTeacher($teacherId, $type, $title, $message, $subjectId = null) {
    global $mysqli;
    $stmt = $mysqli->prepare('INSERT INTO notifications (teacher_id, subject_id, type, title, message, created_at) VALUES (?, ?, ?, ?, ?, NOW())');
    $stmt->bind_param('iisss', $teacherId, $subjectId, $type, $title, $message);
    $stmt->execute();
    $stmt->close();
}

function notifyTeacherOfSubjectAssignment($mysqli, $teacherId, $code, $name, $days, $startTime) {
    $stmt = $mysqli->prepare('SELECT first_name, last_name, email FROM teachers WHERE id = ? LIMIT 1');
    $stmt->bind_param('i', $teacherId);
    $stmt->execute();
    $teacher = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$teacher) {
        return;
    }

    $teacherName = trim($teacher['first_name'] . ' ' . $teacher['last_name']);
    $dayList = implode(', ', $days);
    $timeLabel = formatTime($startTime);
    $subjectLabel = $name . ' (' . $code . ')';

    $message = 'You have been assigned to teach ' . $subjectLabel . ' on ' . $dayList . ' at ' . $timeLabel . '. Please set up your class.';
    notifyTeacher($teacherId, 'assignment', 'New Class Assignment', $message, null);

    if (!empty($teacher['email']) && isMailConfigured()) {
        $subject = 'New Class Assignment: ' . $subjectLabel;
        $body = '<p>Hi ' . htmlspecialchars($teacherName) . ',</p>'
            . '<p>You have been assigned to teach <strong>' . htmlspecialchars($subjectLabel) . '</strong>, scheduled on '
            . '<strong>' . htmlspecialchars($dayList) . '</strong> at <strong>' . htmlspecialchars($timeLabel) . '</strong>.</p>'
            . '<p>Please log in to the Teacher Portal to set up and prepare your class.</p>'
            . '<p>— TimeTrack Attendance System</p>';
        sendMail($teacher['email'], $teacherName, $subject, $body);
    }
}

function unreadTeacherNotificationCount($teacherId) {
    global $mysqli;
    $stmt = $mysqli->prepare('SELECT COUNT(*) FROM notifications WHERE teacher_id = ? AND is_read = 0');
    $stmt->bind_param('i', $teacherId);
    $stmt->execute();
    $stmt->bind_result($count);
    $stmt->fetch();
    $stmt->close();
    return (int) $count;
}

function markTeacherNotificationRead($notificationId, $teacherId) {
    global $mysqli;
    $stmt = $mysqli->prepare('UPDATE notifications SET is_read = 1 WHERE id = ? AND teacher_id = ?');
    $stmt->bind_param('ii', $notificationId, $teacherId);
    $stmt->execute();
    $stmt->close();
}

function teacherNotificationExistsToday($mysqli, $teacherId, $subjectId, $type) {
    $stmt = $mysqli->prepare('SELECT id FROM notifications WHERE teacher_id = ? AND subject_id = ? AND type = ? AND DATE(created_at) = CURDATE() LIMIT 1');
    $stmt->bind_param('iis', $teacherId, $subjectId, $type);
    $stmt->execute();
    $stmt->store_result();
    $exists = $stmt->num_rows > 0;
    $stmt->close();
    return $exists;
}

function generateTeacherClassNotifications($mysqli, $teacherId) {
    $today = date('D');
    $now = time();
    $todayDate = date('Y-m-d');

    $stmt = $mysqli->prepare("SELECT id, code, name, start_time, end_time, subject_room FROM subjects WHERE teacher_id = ? AND day_of_week = ? AND status = 'active'");
    $stmt->bind_param('is', $teacherId, $today);
    $stmt->execute();
    $subjects = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    foreach ($subjects as $subject) {
        $startTs = strtotime($todayDate . ' ' . $subject['start_time']);
        $endTs = $subject['end_time'] ? strtotime($todayDate . ' ' . $subject['end_time']) : $startTs + 3600;

        if ($now >= $startTs - 900 && $now < $startTs && !teacherNotificationExistsToday($mysqli, $teacherId, $subject['id'], 'reminder')) {
            $message = $subject['name'] . ' (' . $subject['code'] . ') starts at ' . date('g:i A', $startTs) . ($subject['subject_room'] ? ' in ' . $subject['subject_room'] : '') . '.';
            notifyTeacher($teacherId, 'reminder', 'Upcoming Class', $message, $subject['id']);
        }

        if ($now >= $endTs && !teacherNotificationExistsToday($mysqli, $teacherId, $subject['id'], 'summary')) {
            $counts = ['present' => 0, 'late' => 0, 'absent' => 0, 'excused' => 0];
            $total = 0;
            $cStmt = $mysqli->prepare('SELECT status, COUNT(*) AS c FROM attendance WHERE subject_id = ? AND date = CURDATE() GROUP BY status');
            $cStmt->bind_param('i', $subject['id']);
            $cStmt->execute();
            $result = $cStmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $counts[$row['status']] = (int) $row['c'];
                $total += (int) $row['c'];
            }
            $cStmt->close();
            if ($total > 0) {
                $message = $subject['name'] . ': ' . $counts['present'] . ' present, ' . $counts['late'] . ' late, ' . $counts['absent'] . ' absent.';
                notifyTeacher($teacherId, 'summary', 'Attendance Summary', $message, $subject['id']);
            }
        }
    }
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

function generateSecurePassword($length = 10) {
    $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz23456789';
    $password = '';
    $max = strlen($alphabet) - 1;
    for ($i = 0; $i < $length; $i++) {
        $password .= $alphabet[random_int(0, $max)];
    }
    return $password;
}

function ensureRoomJoinCode($mysqli, $roomId) {
    $stmt = $mysqli->prepare('SELECT join_code FROM rooms WHERE id = ? LIMIT 1');
    $stmt->bind_param('i', $roomId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    $joinCode = $row['join_code'] ?? null;

    if ($joinCode) {
        return $joinCode;
    }

    $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $max = strlen($alphabet) - 1;
    for ($attempt = 0; $attempt < 10; $attempt++) {
        $candidate = '';
        for ($i = 0; $i < 6; $i++) {
            $candidate .= $alphabet[random_int(0, $max)];
        }
        $check = $mysqli->prepare('SELECT id FROM rooms WHERE join_code = ? LIMIT 1');
        $check->bind_param('s', $candidate);
        $check->execute();
        $check->store_result();
        $isUnique = $check->num_rows === 0;
        $check->close();
        if ($isUnique) {
            $update = $mysqli->prepare('UPDATE rooms SET join_code = ? WHERE id = ?');
            $update->bind_param('si', $candidate, $roomId);
            $update->execute();
            $update->close();
            return $candidate;
        }
    }
    return null;
}

// A "class" is one or more subjects rows sharing the same teacher_id + room_id
// + code (one row per meeting day). The join code is per-class, not per-room,
// so joining it only adds that one class to a student's list via `enrollments`
// instead of swapping their whole room (and every subject in it).
function ensureClassJoinCode($mysqli, $subjectId) {
    $refStmt = $mysqli->prepare('SELECT teacher_id, room_id, code, join_code FROM subjects WHERE id = ? LIMIT 1');
    $refStmt->bind_param('i', $subjectId);
    $refStmt->execute();
    $ref = $refStmt->get_result()->fetch_assoc();
    $refStmt->close();

    if (!$ref) {
        return null;
    }
    if ($ref['join_code']) {
        return $ref['join_code'];
    }

    // A sibling day-row for the same class may already carry the code. join_code is
    // UNIQUE across the whole table, and joining by code only needs ONE row in the
    // group to have it (it then finds every sibling via teacher_id + room_id + code,
    // not via join_code) — so just return the sibling's code as-is. Writing that same
    // value onto this row too would collide with the sibling and violate the constraint.
    $siblingStmt = $mysqli->prepare('SELECT join_code FROM subjects WHERE teacher_id <=> ? AND room_id = ? AND code = ? AND join_code IS NOT NULL LIMIT 1');
    $siblingStmt->bind_param('iis', $ref['teacher_id'], $ref['room_id'], $ref['code']);
    $siblingStmt->execute();
    $siblingRow = $siblingStmt->get_result()->fetch_assoc();
    $siblingStmt->close();

    if ($siblingRow && $siblingRow['join_code']) {
        return $siblingRow['join_code'];
    }

    $joinCode = null;
    $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $max = strlen($alphabet) - 1;
    for ($attempt = 0; $attempt < 10; $attempt++) {
        $candidate = '';
        for ($i = 0; $i < 6; $i++) {
            $candidate .= $alphabet[random_int(0, $max)];
        }
        $check = $mysqli->prepare('SELECT id FROM subjects WHERE join_code = ? LIMIT 1');
        $check->bind_param('s', $candidate);
        $check->execute();
        $check->store_result();
        $isUnique = $check->num_rows === 0;
        $check->close();
        if ($isUnique) {
            $joinCode = $candidate;
            break;
        }
    }
    if (!$joinCode) {
        return null;
    }

    // Only this row gets the newly generated code — no other row in the group has
    // one yet, so there's nothing to collide with.
    $updateStmt = $mysqli->prepare('UPDATE subjects SET join_code = ? WHERE id = ?');
    $updateStmt->bind_param('si', $joinCode, $subjectId);
    $updateStmt->execute();
    $updateStmt->close();

    return $joinCode;
}

function createUserAccountFor($mysqli, $role, $baseUsername, $email, &$plainPassword) {
    $username = $baseUsername;
    $email = $email ?: (strtolower(preg_replace('/[^a-z0-9]/i', '', $baseUsername)) . '@timetrack.local');
    $plainPassword = generateSecurePassword();
    $hash = password_hash($plainPassword, PASSWORD_DEFAULT);
    $stmt = $mysqli->prepare('INSERT INTO users (username, email, password_hash, role, status, created_at) VALUES (?, ?, ?, ?, "active", NOW())');
    $stmt->bind_param('ssss', $username, $email, $hash, $role);
    // mysqli throws on a duplicate username/email (PHP 8.1+ default report mode)
    // instead of returning false, so catch it here to keep this function's
    // documented "false means try again" contract for callers.
    try {
        $stmt->execute();
    } catch (mysqli_sql_exception $e) {
        $stmt->close();
        return false;
    }
    $userId = $mysqli->insert_id;
    $stmt->close();
    return $userId;
}

function regenerateCredentials($mysqli, $userId, &$plainPassword) {
    $plainPassword = generateSecurePassword();
    $hash = password_hash($plainPassword, PASSWORD_DEFAULT);
    $stmt = $mysqli->prepare('UPDATE users SET password_hash = ?, failed_attempts = 0, lock_until = NULL, remember_token = NULL, status = "active" WHERE id = ?');
    $stmt->bind_param('si', $hash, $userId);
    $result = $stmt->execute();
    $stmt->close();
    return $result;
}

function flashCredentials($username, $password, $emailed = false) {
    $_SESSION['flash_credentials'] = ['username' => $username, 'password' => $password, 'emailed' => $emailed];
}

function flashCredentialsMessage() {
    if (!empty($_SESSION['flash_credentials'])) {
        $credentials = $_SESSION['flash_credentials'];
        unset($_SESSION['flash_credentials']);
        return $credentials;
    }
    return null;
}

function computeAttendanceStatus($startTime, $scanTime, $absentCutoff = null) {
    $diffMinutes = (strtotime($scanTime) - strtotime($startTime)) / 60;
    if ($diffMinutes <= 0) {
        return 'present';
    }
    $absentCutoff = $absentCutoff !== null ? intval($absentCutoff) : intval(getSetting('absent_cutoff_minutes', 20));
    return $diffMinutes < $absentCutoff ? 'late' : 'absent';
}

function virtualRosterStatus($startTime, $nowTime = null, $absentCutoff = null) {
    $now = $nowTime ?: date('H:i:s');
    $diffMinutes = (strtotime($now) - strtotime($startTime)) / 60;
    if ($diffMinutes < 0) {
        return 'pending';
    }
    $absentCutoff = $absentCutoff !== null ? intval($absentCutoff) : intval(getSetting('absent_cutoff_minutes', 20));
    return $diffMinutes < $absentCutoff ? 'pending' : 'absent';
}

function getLiveRosterForSubject($mysqli, $subjectId) {
    $emptyCounts = ['present' => 0, 'late' => 0, 'absent' => 0, 'pending' => 0];
    $stmt = $mysqli->prepare('SELECT id, teacher_id, room_id, start_time, day_of_week, absent_cutoff_minutes FROM subjects WHERE id = ? LIMIT 1');
    $stmt->bind_param('i', $subjectId);
    $stmt->execute();
    $subject = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$subject) {
        return ['subject' => null, 'rows' => [], 'counts' => $emptyCounts];
    }
    $query = 'SELECT s.id AS student_id, s.student_id AS student_code, s.first_name, s.last_name, s.photo,
                     a.status AS scanned_status, a.time AS scan_time
              FROM students s
              LEFT JOIN attendance a ON a.student_id = s.id AND a.subject_id = ? AND a.date = CURDATE()
              WHERE s.status = "active" AND (s.room_id = ? OR s.id IN (SELECT student_id FROM enrollments WHERE subject_id = ?))
              ORDER BY s.last_name, s.first_name';
    $stmt = $mysqli->prepare($query);
    $stmt->bind_param('iii', $subjectId, $subject['room_id'], $subjectId);
    $stmt->execute();
    $result = $stmt->get_result();
    $rows = [];
    $counts = $emptyCounts;
    while ($row = $result->fetch_assoc()) {
        $row['display_status'] = $row['scanned_status'] ?: virtualRosterStatus($subject['start_time'], null, $subject['absent_cutoff_minutes']);
        $counts[$row['display_status']]++;
        $rows[] = $row;
    }
    $stmt->close();
    return ['subject' => $subject, 'rows' => $rows, 'counts' => $counts];
}

function effectiveAbsentCutoff($subject) {
    return $subject['absent_cutoff_minutes'] !== null ? intval($subject['absent_cutoff_minutes']) : intval(getSetting('absent_cutoff_minutes', 20));
}

function currentTeacherId() {
    static $cached = 'unset';
    if ($cached !== 'unset') {
        return $cached;
    }
    global $mysqli;
    $user = currentUser();
    if (!$user) {
        return $cached = false;
    }
    $stmt = $mysqli->prepare('SELECT id FROM teachers WHERE user_id = ? LIMIT 1');
    $stmt->bind_param('i', $user['id']);
    $stmt->execute();
    $stmt->bind_result($id);
    $cached = $stmt->fetch() ? $id : false;
    $stmt->close();
    return $cached;
}

function currentStudentId() {
    static $cached = 'unset';
    if ($cached !== 'unset') {
        return $cached;
    }
    global $mysqli;
    $user = currentUser();
    if (!$user) {
        return $cached = false;
    }
    $stmt = $mysqli->prepare('SELECT id FROM students WHERE user_id = ? LIMIT 1');
    $stmt->bind_param('i', $user['id']);
    $stmt->execute();
    $stmt->bind_result($id);
    $cached = $stmt->fetch() ? $id : false;
    $stmt->close();
    return $cached;
}

function saveStudentRecord($mysqli, $postData, $files, $allowedRoomIds = null) {
    $id = intval($postData['id'] ?? 0);
    $studentId = sanitize($postData['student_id'] ?? '');
    $firstName = sanitize($postData['first_name'] ?? '');
    $lastName = sanitize($postData['last_name'] ?? '');
    $gender = sanitize($postData['gender'] ?? '');
    $birthday = sanitize($postData['birthday'] ?? '');
    $courseId = intval($postData['course_id'] ?? 0);
    $yearLevel = sanitize($postData['year_level'] ?? '');
    $roomId = intval($postData['room_id'] ?? 0);
    $guardian = sanitize($postData['guardian'] ?? '');
    $guardianEmail = sanitize($postData['guardian_email'] ?? '');
    $phone = sanitize($postData['phone'] ?? '');
    $email = sanitize($postData['email'] ?? '');
    $status = sanitize($postData['status'] ?? 'active');

    if ($allowedRoomIds !== null && !in_array($roomId, $allowedRoomIds)) {
        return ['success' => false, 'message' => 'You can only manage students in your own rooms.', 'type' => 'danger', 'credentials' => null];
    }
    if ($id && $allowedRoomIds !== null) {
        $existingRoomId = null;
        $check = $mysqli->prepare('SELECT room_id FROM students WHERE id = ?');
        $check->bind_param('i', $id);
        $check->execute();
        $check->bind_result($existingRoomId);
        $check->fetch();
        $check->close();
        if (!in_array($existingRoomId, $allowedRoomIds)) {
            return ['success' => false, 'message' => 'You can only manage students in your own rooms.', 'type' => 'danger', 'credentials' => null];
        }
    }
    if ($studentId === '') {
        return ['success' => false, 'message' => 'Student code is required.', 'type' => 'danger', 'credentials' => null];
    }
    $dupCheck = $mysqli->prepare('SELECT id FROM students WHERE student_id = ? AND id != ? LIMIT 1');
    $dupCheck->bind_param('si', $studentId, $id);
    $dupCheck->execute();
    $dupCheck->store_result();
    $isDuplicate = $dupCheck->num_rows > 0;
    $dupCheck->close();
    if ($isDuplicate) {
        return ['success' => false, 'message' => 'That student code is already in use.', 'type' => 'danger', 'credentials' => null];
    }
    $courseIdParam = $courseId ?: null;
    $roomIdParam = $roomId ?: null;
    $birthdayParam = $birthday !== '' ? $birthday : null;
    $photo = '';
    if (!empty($files['photo']['name'])) {
        $ext = pathinfo($files['photo']['name'], PATHINFO_EXTENSION);
        $allowed = ['jpg', 'jpeg', 'png'];
        if (in_array(strtolower($ext), $allowed)) {
            $photo = 'uploads/student_' . time() . '.' . $ext;
            move_uploaded_file($files['photo']['tmp_name'], __DIR__ . '/../' . $photo);
        }
    }
    if ($id) {
        $qrToken = $studentId;
        $stmt = $mysqli->prepare('UPDATE students SET student_id = ?, first_name = ?, last_name = ?, gender = ?, birthday = ?, course_id = ?, year_level = ?, room_id = ?, guardian_name = ?, guardian_email = ?, phone = ?, email = ?, status = ?, qr_code = ?' . ($photo ? ', photo = ?' : '') . ' WHERE id = ?');
        if ($photo) {
            $stmt->bind_param('sssssisisssssssi', $studentId, $firstName, $lastName, $gender, $birthdayParam, $courseIdParam, $yearLevel, $roomIdParam, $guardian, $guardianEmail, $phone, $email, $status, $qrToken, $photo, $id);
        } else {
            $stmt->bind_param('sssssisissssssi', $studentId, $firstName, $lastName, $gender, $birthdayParam, $courseIdParam, $yearLevel, $roomIdParam, $guardian, $guardianEmail, $phone, $email, $status, $qrToken, $id);
        }
        $stmt->execute();
        $stmt->close();
        return ['success' => true, 'message' => 'Student updated successfully.', 'type' => 'success', 'credentials' => null];
    }

    $stmt = $mysqli->prepare('INSERT INTO students (student_id, first_name, last_name, gender, birthday, course_id, year_level, room_id, guardian_name, guardian_email, phone, email, status, photo, qr_code, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())');
    $qrToken = $studentId;
    $stmt->bind_param('sssssisisssssss', $studentId, $firstName, $lastName, $gender, $birthdayParam, $courseIdParam, $yearLevel, $roomIdParam, $guardian, $guardianEmail, $phone, $email, $status, $photo, $qrToken);
    $stmt->execute();
    $stmt->close();
    $newId = $mysqli->insert_id;
    $plainPassword = '';
    $userId = createUserAccountFor($mysqli, 'student', $studentId, $email, $plainPassword);
    if ($userId) {
        $stmt = $mysqli->prepare('UPDATE students SET user_id = ? WHERE id = ?');
        $stmt->bind_param('ii', $userId, $newId);
        $stmt->execute();
        $stmt->close();
        $message = 'Student added successfully.';
        $emailed = $email && emailStudentCredentials($email, trim($firstName . ' ' . $lastName), $studentId, $plainPassword);
        if ($emailed) {
            $message .= ' Login credentials were emailed to the student.';
        }
        return ['success' => true, 'message' => $message, 'type' => 'success', 'credentials' => ['username' => $studentId, 'password' => $plainPassword, 'emailed' => $emailed]];
    }
    return ['success' => true, 'message' => 'Student added, but a login account could not be created (email may already be in use).', 'type' => 'warning', 'credentials' => null];
}

function emailStudentCredentials($email, $studentName, $studentNumber, $plainPassword) {
    if (!isMailConfigured()) {
        return false;
    }
    $subject = 'Your TimeTrack Student Account';
    $body = '<p>Hi ' . htmlspecialchars($studentName) . ',</p>'
        . '<p>An account has been created for you on TimeTrack. Here are your login credentials:</p>'
        . '<p><strong>Student Number:</strong> ' . htmlspecialchars($studentNumber) . '<br>'
        . '<strong>Password:</strong> ' . htmlspecialchars($plainPassword) . '</p>'
        . '<p>Please log in and change your password as soon as possible.</p>'
        . '<p>— TimeTrack Attendance System</p>';
    return sendMail($email, $studentName, $subject, $body);
}

function emailTeacherCredentials($email, $teacherName, $teacherNumber, $plainPassword) {
    if (!isMailConfigured()) {
        return false;
    }
    $subject = 'Your TimeTrack Teacher Account';
    $body = '<p>Hi ' . htmlspecialchars($teacherName) . ',</p>'
        . '<p>An account has been created for you on TimeTrack. Here are your login credentials:</p>'
        . '<p><strong>Teacher ID:</strong> ' . htmlspecialchars($teacherNumber) . '<br>'
        . '<strong>Password:</strong> ' . htmlspecialchars($plainPassword) . '</p>'
        . '<p>Please log in and change your password as soon as possible.</p>'
        . '<p>— TimeTrack Attendance System</p>';
    return sendMail($email, $teacherName, $subject, $body);
}

function findStudentByCode($mysqli, $code) {
    $stmt = $mysqli->prepare('SELECT s.id, s.student_id, s.first_name, s.last_name, s.room_id, s.status, c.code AS course_code, sec.room_name FROM students s LEFT JOIN courses c ON s.course_id = c.id LEFT JOIN rooms sec ON s.room_id = sec.id WHERE s.student_id = ? LIMIT 1');
    $stmt->bind_param('s', $code);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ?: null;
}

// A student can view a subject if it's in their home room, or if they were individually
// enrolled into it (e.g. via a join code or a teacher's Enroll Student action) even
// though it belongs to a different room. Use this instead of a raw room_id compare on
// every student-facing subject page, or cross-enrolled students hit "Subject not found".
function studentCanAccessSubject($mysqli, $studentDbId, $subject, $studentRoomId) {
    if (!$subject) {
        return false;
    }
    if ((int) $subject['room_id'] === (int) $studentRoomId) {
        return true;
    }
    $check = $mysqli->prepare('SELECT id FROM enrollments WHERE student_id = ? AND subject_id = ? LIMIT 1');
    $check->bind_param('ii', $studentDbId, $subject['id']);
    $check->execute();
    $check->store_result();
    $has = $check->num_rows > 0;
    $check->close();
    return $has;
}

// Enrolling always scopes to this one subject via `enrollments` — it never touches the
// student's room_id, so being enrolled in one class never makes them appear in every
// other subject that happens to share the same room. A room_id match (home room) is
// still honored as "already enrolled" since the roster/attendance queries also check it.
function enrollStudentInRoom($mysqli, $studentDbId, $targetRoomId, $subjectId) {
    $currentRoomId = null;
    $stmt = $mysqli->prepare('SELECT room_id FROM students WHERE id = ?');
    $stmt->bind_param('i', $studentDbId);
    $stmt->execute();
    $stmt->bind_result($currentRoomId);
    $found = $stmt->fetch();
    $stmt->close();
    if (!$found) {
        return 'not_found';
    }
    if ($currentRoomId !== null && (int) $currentRoomId === (int) $targetRoomId) {
        return 'already_enrolled';
    }
    $check = $mysqli->prepare('SELECT id FROM enrollments WHERE student_id = ? AND subject_id = ? LIMIT 1');
    $check->bind_param('ii', $studentDbId, $subjectId);
    $check->execute();
    $check->store_result();
    $alreadyInClass = $check->num_rows > 0;
    $check->close();
    if ($alreadyInClass) {
        return 'already_enrolled';
    }
    $insert = $mysqli->prepare('INSERT IGNORE INTO enrollments (student_id, subject_id) VALUES (?, ?)');
    $insert->bind_param('ii', $studentDbId, $subjectId);
    $insert->execute();
    $insert->close();
    return 'success';
}

function unenrollStudentFromRoom($mysqli, $studentDbId, $allowedRoomIds) {
    $existingRoomId = null;
    $check = $mysqli->prepare('SELECT room_id FROM students WHERE id = ?');
    $check->bind_param('i', $studentDbId);
    $check->execute();
    $check->bind_result($existingRoomId);
    $found = $check->fetch();
    $check->close();
    if (!$found || !in_array($existingRoomId, $allowedRoomIds)) {
        return false;
    }
    $stmt = $mysqli->prepare('UPDATE students SET room_id = NULL WHERE id = ?');
    $stmt->bind_param('i', $studentDbId);
    $stmt->execute();
    $stmt->close();
    return true;
}

// Removes a student's individual `enrollments` link to one class (added via
// enrollStudentInRoom), without touching their home room.
function removeClassEnrollment($mysqli, $studentDbId, $subjectId) {
    $stmt = $mysqli->prepare('DELETE FROM enrollments WHERE student_id = ? AND subject_id = ?');
    $stmt->bind_param('ii', $studentDbId, $subjectId);
    $stmt->execute();
    $affected = $stmt->affected_rows > 0;
    $stmt->close();
    return $affected;
}

function deleteStudentRecord($mysqli, $studentDbId, $allowedRoomIds = null) {
    if ($allowedRoomIds !== null) {
        $existingRoomId = null;
        $check = $mysqli->prepare('SELECT room_id FROM students WHERE id = ?');
        $check->bind_param('i', $studentDbId);
        $check->execute();
        $check->bind_result($existingRoomId);
        $found = $check->fetch();
        $check->close();
        if (!$found || !in_array($existingRoomId, $allowedRoomIds)) {
            return false;
        }
    }
    $stmt = $mysqli->prepare('SELECT user_id FROM students WHERE id = ?');
    $stmt->bind_param('i', $studentDbId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    $linkedUserId = $row['user_id'] ?? null;
    if ($linkedUserId) {
        $stmt = $mysqli->prepare("UPDATE users SET status = 'inactive' WHERE id = ?");
        $stmt->bind_param('i', $linkedUserId);
        $stmt->execute();
        $stmt->close();
    }
    $stmt = $mysqli->prepare('DELETE FROM students WHERE id = ?');
    $stmt->bind_param('i', $studentDbId);
    $stmt->execute();
    $stmt->close();
    return true;
}

function notifyTeacherOfScheduleChange($mysqli, $teacherId, $code, $name, $days, $startTime, $endTime = null) {
    $stmt = $mysqli->prepare('SELECT first_name, last_name, email FROM teachers WHERE id = ? LIMIT 1');
    $stmt->bind_param('i', $teacherId);
    $stmt->execute();
    $teacher = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$teacher) {
        return;
    }

    $teacherName = trim($teacher['first_name'] . ' ' . $teacher['last_name']);
    $dayList = implode(', ', $days);
    $timeLabel = formatTime($startTime) . ($endTime ? ' - ' . formatTime($endTime) : '');
    $subjectLabel = $name . ' (' . $code . ')';

    $message = 'The schedule for ' . $subjectLabel . ' has been updated. It is now on ' . $dayList . ' at ' . $timeLabel . '.';
    notifyTeacher($teacherId, 'schedule_update', 'Class Schedule Updated', $message, null);

    if (!empty($teacher['email']) && isMailConfigured()) {
        $subject = 'Schedule Updated: ' . $subjectLabel;
        $body = '<p>Hi ' . htmlspecialchars($teacherName) . ',</p>'
            . '<p>The schedule for <strong>' . htmlspecialchars($subjectLabel) . '</strong> has been updated. It is now on '
            . '<strong>' . htmlspecialchars($dayList) . '</strong> at <strong>' . htmlspecialchars($timeLabel) . '</strong>.</p>'
            . '<p>Please review the updated schedule in the Teacher Portal.</p>'
            . '<p>— TimeTrack Attendance System</p>';
        sendMail($teacher['email'], $teacherName, $subject, $body);
    }
}

function renderTeacherClassHeader($subject, $activeTab) {
    $theme = subjectTheme($subject['id']);
    ?>
    <div class="card p-4 mb-3 sp-greeting-card d-flex flex-column justify-content-center" style="min-height: 220px;">
        <div class="d-flex align-items-start gap-3 flex-wrap">
            <div class="sp-mc-icon-box" style="--mc-color: <?php echo $theme['color']; ?>; width:56px; height:56px; font-size:1.4rem;"><i class="fa-solid <?php echo $theme['icon']; ?>"></i></div>
            <div class="flex-grow-1">
                <h4 class="mb-3"><?php echo htmlspecialchars($subject['code']); ?> — <?php echo htmlspecialchars($subject['name']); ?></h4>
                <div class="sp-mc-meta mb-3" style="gap: 1.25rem;">
                    <span><i class="fa-solid fa-calendar"></i> <?php echo htmlspecialchars($subject['day_of_week']); ?> | <?php echo formatTime($subject['start_time']); ?><?php echo $subject['end_time'] ? ' - ' . formatTime($subject['end_time']) : ''; ?></span>
                    <span><i class="fa-solid fa-location-dot"></i> <?php echo htmlspecialchars($subject['subject_room'] ?: 'No room set'); ?></span>
                    <?php if ($subject['credit_units']): ?><span><i class="fa-solid fa-award"></i> <?php echo (int) $subject['credit_units']; ?> units</span><?php endif; ?>
                </div>
                <div class="d-flex flex-wrap gap-3">
                    <span class="sp-shd-pill"><i class="fa-solid fa-graduation-cap"></i> <?php echo htmlspecialchars($subject['course_code'] . ' - ' . $subject['course_name']); ?></span>
                    <span class="sp-shd-pill"><i class="fa-solid fa-user-group"></i> <?php echo htmlspecialchars($subject['year_level'] . ' - ' . $subject['room_name']); ?></span>
                </div>
            </div>
            <span class="badge <?php echo $subject['status'] === 'active' ? 'sp-mc-badge sp-mc-badge-ongoing' : 'sp-mc-badge sp-mc-badge-inactive'; ?>" style="font-size: 0.95rem; padding: 0.5rem 1.1rem;"><?php echo $subject['status'] === 'active' ? 'Active' : 'Inactive'; ?></span>
        </div>
        <?php if (!empty($subject['important_note'])): ?>
            <div class="alert alert-warning mt-3 mb-0"><i class="fa-solid fa-circle-exclamation me-1"></i> <?php echo nl2br(htmlspecialchars($subject['important_note'])); ?></div>
        <?php endif; ?>
    </div>

    <div class="sp-detail-tabs">
        <?php if ($activeTab !== 'details'): ?><a href="class-details.php?id=<?php echo $subject['id']; ?>"><i class="fa-solid fa-file-lines"></i> Details</a><?php endif; ?>
        <?php if ($activeTab !== 'attendance'): ?><a href="attendance.php?subject_id=<?php echo $subject['id']; ?>"><i class="fa-solid fa-clipboard-check"></i> Attendance</a><?php endif; ?>
        <?php if ($activeTab !== 'announcements'): ?><a href="class-announcements.php?subject_id=<?php echo $subject['id']; ?>"><i class="fa-solid fa-bullhorn"></i> Announcements</a><?php endif; ?>
    </div>
    <?php
}

function renderSubjectPageHeader($subject, $activeTab) {
    $theme = subjectTheme($subject['id']);
    ?>
    <a href="subjects.php" class="sp-back-link"><i class="fa-solid fa-arrow-left"></i> Back to Subjects</a>
    <div class="sp-subject-header" style="border-left-color: <?php echo $theme['color']; ?>;">
        <div class="sp-subject-header-bg-dots"></div>
        <div class="sp-subject-header-bg-wave"></div>
        <div class="sp-subject-header-icon-box"><i class="fa-solid <?php echo $theme['icon']; ?>"></i></div>
        <div class="sp-subject-header-info">
            <div class="sp-subject-header-title">
                <span class="sp-shd-code"><?php echo htmlspecialchars($subject['code']); ?></span>
                <span class="sp-shd-sep">|</span>
                <span class="sp-shd-name"><?php echo htmlspecialchars($subject['name']); ?></span>
            </div>
            <div class="sp-subject-header-badges">
                <span class="sp-shd-pill"><i class="fa-solid fa-calendar-days"></i> <?php echo htmlspecialchars($subject['day_of_week']); ?> | <?php echo formatTime($subject['start_time']); ?><?php echo $subject['end_time'] ? ' - ' . formatTime($subject['end_time']) : ''; ?></span>
                <?php if ($subject['subject_room']): ?>
                    <span class="sp-shd-pill"><i class="fa-solid fa-location-dot"></i> <?php echo htmlspecialchars($subject['subject_room']); ?></span>
                <?php endif; ?>
            </div>
        </div>
        <div class="sp-subject-header-prof-block">
            <div class="sp-subject-header-photo-wrap">
                <?php if (!empty($subject['teacher_photo'])): ?>
                    <img src="../<?php echo htmlspecialchars($subject['teacher_photo']); ?>" class="sp-subject-header-photo" alt="">
                <?php else: ?>
                    <div class="sp-subject-header-photo-fallback"><i class="fa-solid fa-user"></i></div>
                <?php endif; ?>
                <span class="sp-subject-header-dot"></span>
            </div>
            <div class="sp-subject-header-prof-name"><?php echo htmlspecialchars($subject['teacher_name'] ? 'Prof. ' . $subject['teacher_name'] : 'Unassigned'); ?></div>
            <div class="sp-subject-header-prof-role">Instructor</div>
            <?php if (!empty($subject['teacher_email'])): ?>
                <a href="https://mail.google.com/mail/?view=cm&fs=1&to=<?php echo urlencode($subject['teacher_email']); ?>" target="_blank" rel="noopener" class="btn btn-outline-success rounded-pill btn-sm"><i class="fa-solid fa-envelope me-1"></i> Email Instructor</a>
            <?php endif; ?>
        </div>
    </div>

    <div class="sp-detail-tabs">
        <a href="subject-details.php?id=<?php echo $subject['id']; ?>" class="<?php echo $activeTab === 'overview' ? 'active' : ''; ?>"><i class="fa-solid fa-chart-simple"></i> Overview</a>
        <a href="attendance.php?subject_id=<?php echo $subject['id']; ?>" class="<?php echo $activeTab === 'attendance' ? 'active' : ''; ?>"><i class="fa-solid fa-clock-rotate-left"></i> Attendance History</a>
        <a href="subject-announcements.php?id=<?php echo $subject['id']; ?>" class="<?php echo $activeTab === 'announcements' ? 'active' : ''; ?>"><i class="fa-solid fa-comment-dots"></i> Announcements</a>
        <a href="subject-classmates.php?id=<?php echo $subject['id']; ?>" class="<?php echo $activeTab === 'classmates' ? 'active' : ''; ?>"><i class="fa-solid fa-user-group"></i> Classmates</a>
    </div>
    <?php
}

function subjectTheme($subjectId) {
    $bands = ['#2f6fed', '#f2994a', '#8e44ad', '#149c6d', '#2f6fed', '#3c4b5a'];
    $icons = ['fa-database', 'fa-code', 'fa-cubes', 'fa-comments', 'fa-diagram-project', 'fa-chart-column'];
    return ['color' => $bands[$subjectId % count($bands)], 'icon' => $icons[$subjectId % count($icons)]];
}

function subjectQrText($studentQrToken, $subjectId) {
    return $studentQrToken . '|SUBJ' . $subjectId;
}

function ensureQrDirectory($directory) {
    if (!is_dir($directory)) {
        return mkdir($directory, 0755, true);
    }
    return is_writable($directory);
}

function isValidPngFile($filePath) {
    if (!file_exists($filePath) || !is_readable($filePath)) {
        return false;
    }
    $handle = fopen($filePath, 'rb');
    if (!$handle) {
        return false;
    }
    $signature = fread($handle, 8);
    fclose($handle);
    return $signature === "\x89PNG\x0D\x0A\x1A\x0A";
}

function generateStudentQrFile($text, $filePath) {
    require_once __DIR__ . '/../phpqrcode/qrlib.php';
    if (!is_dir(dirname($filePath)) && !mkdir(dirname($filePath), 0755, true)) {
        return false;
    }
    if (file_exists($filePath) && !isValidPngFile($filePath)) {
        @unlink($filePath);
    }
    return QRcode::png($text, $filePath, 'H', 8, 2);
}
