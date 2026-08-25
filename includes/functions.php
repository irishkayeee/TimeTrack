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

function notifyStudent($studentId, $type, $title, $message, $subjectId = null) {
    global $mysqli;
    $stmt = $mysqli->prepare('INSERT INTO notifications (student_id, subject_id, type, title, message, created_at) VALUES (?, ?, ?, ?, ?, NOW())');
    $stmt->bind_param('iisss', $studentId, $subjectId, $type, $title, $message);
    $stmt->execute();
    $stmt->close();
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

function ensureSectionJoinCode($mysqli, $sectionId) {
    $stmt = $mysqli->prepare('SELECT join_code FROM sections WHERE id = ? LIMIT 1');
    $stmt->bind_param('i', $sectionId);
    $stmt->execute();
    $stmt->bind_result($joinCode);
    $stmt->fetch();
    $stmt->close();

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
        $check = $mysqli->prepare('SELECT id FROM sections WHERE join_code = ? LIMIT 1');
        $check->bind_param('s', $candidate);
        $check->execute();
        $check->store_result();
        $isUnique = $check->num_rows === 0;
        $check->close();
        if ($isUnique) {
            $update = $mysqli->prepare('UPDATE sections SET join_code = ? WHERE id = ?');
            $update->bind_param('si', $candidate, $sectionId);
            $update->execute();
            $update->close();
            return $candidate;
        }
    }
    return null;
}

function createUserAccountFor($mysqli, $role, $baseUsername, $email, &$plainPassword) {
    $username = $baseUsername;
    $email = $email ?: (strtolower(preg_replace('/[^a-z0-9]/i', '', $baseUsername)) . '@timetrack.local');
    $plainPassword = generateSecurePassword();
    $hash = password_hash($plainPassword, PASSWORD_DEFAULT);
    $stmt = $mysqli->prepare('INSERT INTO users (username, email, password_hash, role, status, created_at) VALUES (?, ?, ?, ?, "active", NOW())');
    $stmt->bind_param('ssss', $username, $email, $hash, $role);
    if (!$stmt->execute()) {
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

function flashCredentials($username, $password) {
    $_SESSION['flash_credentials'] = ['username' => $username, 'password' => $password];
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
    $stmt = $mysqli->prepare('SELECT id, teacher_id, section_id, start_time, day_of_week, absent_cutoff_minutes FROM subjects WHERE id = ? LIMIT 1');
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
              WHERE s.section_id = ? AND s.status = "active"
              ORDER BY s.last_name, s.first_name';
    $stmt = $mysqli->prepare($query);
    $stmt->bind_param('ii', $subjectId, $subject['section_id']);
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

function saveStudentRecord($mysqli, $postData, $files, $allowedSectionIds = null) {
    $id = intval($postData['id'] ?? 0);
    $studentId = sanitize($postData['student_id'] ?? '');
    $firstName = sanitize($postData['first_name'] ?? '');
    $lastName = sanitize($postData['last_name'] ?? '');
    $gender = sanitize($postData['gender'] ?? '');
    $birthday = sanitize($postData['birthday'] ?? '');
    $courseId = intval($postData['course_id'] ?? 0);
    $yearLevel = sanitize($postData['year_level'] ?? '');
    $sectionId = intval($postData['section_id'] ?? 0);
    $guardian = sanitize($postData['guardian'] ?? '');
    $phone = sanitize($postData['phone'] ?? '');
    $email = sanitize($postData['email'] ?? '');
    $status = sanitize($postData['status'] ?? 'active');

    if ($allowedSectionIds !== null && !in_array($sectionId, $allowedSectionIds)) {
        return ['success' => false, 'message' => 'You can only manage students in your own sections.', 'type' => 'danger', 'credentials' => null];
    }
    if ($id && $allowedSectionIds !== null) {
        $existingSectionId = null;
        $check = $mysqli->prepare('SELECT section_id FROM students WHERE id = ?');
        $check->bind_param('i', $id);
        $check->execute();
        $check->bind_result($existingSectionId);
        $check->fetch();
        $check->close();
        if (!in_array($existingSectionId, $allowedSectionIds)) {
            return ['success' => false, 'message' => 'You can only manage students in your own sections.', 'type' => 'danger', 'credentials' => null];
        }
    }
    if (!$studentId) {
        $studentId = 'S' . time() . rand(10, 99);
    }
    $courseIdParam = $courseId ?: null;
    $sectionIdParam = $sectionId ?: null;
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
        $stmt = $mysqli->prepare('UPDATE students SET student_id = ?, first_name = ?, last_name = ?, gender = ?, birthday = ?, course_id = ?, year_level = ?, section_id = ?, guardian_name = ?, phone = ?, email = ?, status = ?, qr_code = ?' . ($photo ? ', photo = ?' : '') . ' WHERE id = ?');
        if ($photo) {
            $stmt->bind_param('sssssisissssssi', $studentId, $firstName, $lastName, $gender, $birthdayParam, $courseIdParam, $yearLevel, $sectionIdParam, $guardian, $phone, $email, $status, $qrToken, $photo, $id);
        } else {
            $stmt->bind_param('sssssisisssssi', $studentId, $firstName, $lastName, $gender, $birthdayParam, $courseIdParam, $yearLevel, $sectionIdParam, $guardian, $phone, $email, $status, $qrToken, $id);
        }
        $stmt->execute();
        $stmt->close();
        return ['success' => true, 'message' => 'Student updated successfully.', 'type' => 'success', 'credentials' => null];
    }

    $stmt = $mysqli->prepare('INSERT INTO students (student_id, first_name, last_name, gender, birthday, course_id, year_level, section_id, guardian_name, phone, email, status, photo, qr_code, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())');
    $qrToken = $studentId;
    $stmt->bind_param('sssssisissssss', $studentId, $firstName, $lastName, $gender, $birthdayParam, $courseIdParam, $yearLevel, $sectionIdParam, $guardian, $phone, $email, $status, $photo, $qrToken);
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
        return ['success' => true, 'message' => 'Student added successfully.', 'type' => 'success', 'credentials' => ['username' => $studentId, 'password' => $plainPassword]];
    }
    return ['success' => true, 'message' => 'Student added, but a login account could not be created (email may already be in use).', 'type' => 'warning', 'credentials' => null];
}

function deleteStudentRecord($mysqli, $studentDbId, $allowedSectionIds = null) {
    if ($allowedSectionIds !== null) {
        $existingSectionId = null;
        $check = $mysqli->prepare('SELECT section_id FROM students WHERE id = ?');
        $check->bind_param('i', $studentDbId);
        $check->execute();
        $check->bind_result($existingSectionId);
        $found = $check->fetch();
        $check->close();
        if (!$found || !in_array($existingSectionId, $allowedSectionIds)) {
            return false;
        }
    }
    $linkedUserId = null;
    $stmt = $mysqli->prepare('SELECT user_id FROM students WHERE id = ?');
    $stmt->bind_param('i', $studentDbId);
    $stmt->execute();
    $stmt->bind_result($linkedUserId);
    $stmt->fetch();
    $stmt->close();
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
                    <span><i class="fa-solid fa-location-dot"></i> <?php echo htmlspecialchars($subject['room'] ?: 'No room set'); ?></span>
                    <?php if ($subject['credit_units']): ?><span><i class="fa-solid fa-award"></i> <?php echo (int) $subject['credit_units']; ?> units</span><?php endif; ?>
                </div>
                <div class="d-flex flex-wrap gap-3">
                    <span class="sp-shd-pill"><i class="fa-solid fa-graduation-cap"></i> <?php echo htmlspecialchars($subject['course_code'] . ' - ' . $subject['course_name']); ?></span>
                    <span class="sp-shd-pill"><i class="fa-solid fa-user-group"></i> <?php echo htmlspecialchars($subject['year_level'] . ' - ' . $subject['section_name']); ?></span>
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
                <?php if ($subject['room']): ?>
                    <span class="sp-shd-pill"><i class="fa-solid fa-location-dot"></i> <?php echo htmlspecialchars($subject['room']); ?></span>
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
                <a href="mailto:<?php echo htmlspecialchars($subject['teacher_email']); ?>" class="btn btn-outline-success rounded-pill btn-sm"><i class="fa-solid fa-envelope me-1"></i> Email Instructor</a>
            <?php endif; ?>
        </div>
    </div>

    <div class="sp-detail-tabs">
        <a href="subject-details.php?id=<?php echo $subject['id']; ?>" class="<?php echo $activeTab === 'overview' ? 'active' : ''; ?>"><i class="fa-solid fa-chart-simple"></i> Overview</a>
        <a href="attendance.php?subject_id=<?php echo $subject['id']; ?>" class="<?php echo $activeTab === 'attendance' ? 'active' : ''; ?>"><i class="fa-solid fa-clock-rotate-left"></i> Attendance History</a>
        <a href="subject-announcements.php?id=<?php echo $subject['id']; ?>" class="<?php echo $activeTab === 'announcements' ? 'active' : ''; ?>"><i class="fa-solid fa-comment-dots"></i> Announcements</a>
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
