<?php
require_once __DIR__ . '/../includes/functions.php';
requireRole(['teacher']);

$teacherId = currentTeacherId();
if ($teacherId === false) {
    flash('Your teacher profile is not set up. Contact an administrator.', 'danger');
    redirect('../dashboard.php');
}

$subjectId = intval($_GET['subject_id'] ?? 0);
$stmt = $mysqli->prepare('SELECT sub.*, sec.room_name, sec.year_level, c.code AS course_code, c.name AS course_name
    FROM subjects sub
    JOIN rooms sec ON sub.room_id = sec.id
    JOIN courses c ON sec.course_id = c.id
    WHERE sub.id = ? AND sub.teacher_id = ? LIMIT 1');
$stmt->bind_param('ii', $subjectId, $teacherId);
$stmt->execute();
$subject = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$subject) {
    flash('Class not found.', 'danger');
    redirect('subjects.php');
}

$redirectTarget = 'class-makeups.php?subject_id=' . $subjectId;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        flash('Invalid form submission.', 'danger');
        redirect($redirectTarget);
    }
    $action = $_POST['action'] ?? '';

    if ($action === 'add_makeup_session') {
        $sessionDate = sanitize($_POST['session_date'] ?? '');
        $startTime = sanitize($_POST['start_time'] ?? '');
        $endTime = sanitize($_POST['end_time'] ?? '');
        $endTimeParam = $endTime ?: null;
        $note = trim(sanitize($_POST['note'] ?? ''));
        $noteParam = $note !== '' ? $note : null;

        $dateObj = DateTime::createFromFormat('Y-m-d', $sessionDate);
        if (!$dateObj || $dateObj->format('Y-m-d') !== $sessionDate || !$startTime) {
            flash('Please provide a valid date and start time.', 'danger');
            redirect($redirectTarget);
        }

        $ins = $mysqli->prepare('INSERT INTO makeup_sessions (subject_id, teacher_id, session_date, start_time, end_time, note) VALUES (?, ?, ?, ?, ?, ?)');
        $ins->bind_param('iissss', $subjectId, $teacherId, $sessionDate, $startTime, $endTimeParam, $noteParam);
        $ins->execute();
        $ins->close();

        // Notify everyone currently enrolled in this specific class — a makeup
        // session is additive and never touches the recurring weekly schedule.
        $subjectLabel = $subject['code'] . ' - ' . $subject['name'];
        $notifMessage = 'A makeup class for ' . $subjectLabel . ' has been scheduled on ' . formatDate($sessionDate) . ' at ' . formatTime($startTime) . '.';
        $studentsStmt = $mysqli->prepare('SELECT student_id FROM enrollments WHERE subject_id = ?');
        $studentsStmt->bind_param('i', $subjectId);
        $studentsStmt->execute();
        $enrolledIds = $studentsStmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $studentsStmt->close();
        foreach ($enrolledIds as $enrolledRow) {
            $notifStmt = $mysqli->prepare("INSERT INTO notifications (student_id, subject_id, type, title, message, is_read, created_at) VALUES (?, ?, 'makeup_class', 'Makeup Class Scheduled', ?, 0, NOW())");
            $notifStmt->bind_param('iis', $enrolledRow['student_id'], $subjectId, $notifMessage);
            $notifStmt->execute();
            $notifStmt->close();
        }

        // Also post it as a class announcement (tagged 'makeup' so the teacher can't
        // edit/delete the text independently of the session it describes), to every
        // meeting-day row of this class (same teacher+room+code).
        $announceTitle = 'Makeup Class Scheduled';
        $announceMessage = 'A makeup class has been scheduled on ' . formatDate($sessionDate) . ' at ' . formatTime($startTime) . ($endTimeParam ? ' - ' . formatTime($endTimeParam) : '') . '.' . ($noteParam ? ' ' . $noteParam : '');
        $classRowsStmt = $mysqli->prepare('SELECT b.id FROM subjects a JOIN subjects b ON a.teacher_id <=> b.teacher_id AND a.room_id = b.room_id AND a.code = b.code WHERE a.id = ?');
        $classRowsStmt->bind_param('i', $subjectId);
        $classRowsStmt->execute();
        $classRowIds = array_column($classRowsStmt->get_result()->fetch_all(MYSQLI_ASSOC), 'id');
        $classRowsStmt->close();
        foreach ($classRowIds as $rowId) {
            $announceStmt = $mysqli->prepare("INSERT INTO class_announcements (subject_id, teacher_id, source, title, message) VALUES (?, ?, 'makeup', ?, ?)");
            $announceStmt->bind_param('iiss', $rowId, $teacherId, $announceTitle, $announceMessage);
            $announceStmt->execute();
            $announceStmt->close();
        }

        flash('Makeup class scheduled, students notified, and an announcement was posted.', 'success');
        redirect($redirectTarget);
    }

    if ($action === 'delete_makeup_session' && !empty($_POST['makeup_id'])) {
        $makeupId = intval($_POST['makeup_id']);
        $del = $mysqli->prepare('DELETE FROM makeup_sessions WHERE id = ? AND subject_id = ? AND teacher_id = ?');
        $del->bind_param('iii', $makeupId, $subjectId, $teacherId);
        $del->execute();
        $del->close();
        flash('Makeup class removed.', 'success');
        redirect($redirectTarget);
    }
}

$stmt = $mysqli->prepare('SELECT * FROM makeup_sessions WHERE subject_id = ? ORDER BY session_date DESC, start_time DESC');
$stmt->bind_param('i', $subjectId);
$stmt->execute();
$makeups = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$pageTitle = $subject['code'];
$pageSubtitle = $subject['name'];

require_once __DIR__ . '/../includes/teacher_header.php';
$today = date('Y-m-d');
?>
<a href="subjects.php" class="sp-back-link d-inline-flex mb-3"><i class="fa-solid fa-arrow-left"></i> Back to My Classes</a>

<?php renderTeacherClassHeader($subject, 'makeups'); ?>

<div class="card p-4 mb-3">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h6 class="mb-0">Makeup Classes</h6>
        <button type="button" class="btn btn-primary btn-sm rounded-pill px-3" data-bs-toggle="modal" data-bs-target="#addMakeupModal"><i class="fa-solid fa-calendar-plus me-1"></i> Add Makeup Class</button>
    </div>

    <?php if (empty($makeups)): ?>
    <div class="sp-empty-state">
        <div class="sp-empty-state-icon"><i class="fa-solid fa-calendar-plus"></i></div>
        <h5 class="sp-empty-state-title">No Makeup Classes Yet</h5>
        <p class="sp-empty-state-text">Schedule a one-time extra session for this class — your regular weekly schedule stays untouched, and every enrolled student gets notified.</p>
    </div>
    <?php else: ?>
    <div class="d-flex flex-column gap-3">
        <?php foreach ($makeups as $m): ?>
        <div class="card p-3 sp-announce-card">
            <div class="d-flex align-items-start justify-content-between gap-3 flex-wrap">
                <div class="d-flex align-items-start gap-3 flex-grow-1">
                    <span class="sp-announce-icon"><i class="fa-solid fa-calendar-plus"></i></span>
                    <div>
                        <strong class="d-block mb-1">
                            <?php echo htmlspecialchars(formatDate($m['session_date'])); ?>
                            <?php if ($m['session_date'] === $today): ?><span class="badge bg-success ms-1">Today</span><?php endif; ?>
                        </strong>
                        <p class="mb-2 text-muted"><?php echo htmlspecialchars(formatTime($m['start_time'])); ?><?php echo $m['end_time'] ? ' - ' . htmlspecialchars(formatTime($m['end_time'])) : ''; ?></p>
                        <?php if ($m['note']): ?><p class="mb-0 small text-muted"><?php echo htmlspecialchars($m['note']); ?></p><?php endif; ?>
                    </div>
                </div>
                <div class="d-flex gap-2 flex-shrink-0">
                    <a href="../admin/scanner.php?subject_id=<?php echo $subjectId; ?>" class="btn btn-sm btn-primary"><i class="fa-solid fa-qrcode"></i> Take Attendance</a>
                    <form method="post" onsubmit="return confirm('Remove this makeup class?');">
                        <input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>">
                        <input type="hidden" name="action" value="delete_makeup_session">
                        <input type="hidden" name="makeup_id" value="<?php echo (int) $m['id']; ?>">
                        <button type="submit" class="btn btn-sm btn-outline-danger" title="Remove"><i class="fa-solid fa-trash"></i></button>
                    </form>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<div class="modal fade" id="addMakeupModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-4">
            <div class="modal-header">
                <h5 class="modal-title">Add Makeup Class</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="post">
                <input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>">
                <input type="hidden" name="action" value="add_makeup_session">
                <div class="modal-body row g-3">
                    <p class="text-muted small mb-0">Schedules a one-time extra session — your regular weekly schedule stays untouched, and every enrolled student is notified.</p>
                    <div class="col-md-6">
                        <label class="form-label">Date</label>
                        <input type="date" class="form-control" name="session_date" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Start Time</label>
                        <input type="time" class="form-control" name="start_time" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">End Time</label>
                        <input type="time" class="form-control" name="end_time">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Note <span class="text-muted fw-normal">(optional)</span></label>
                        <input type="text" class="form-control" name="note" maxlength="255" placeholder="e.g. Makeup for the class suspension on Sept 15">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Makeup Class</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/teacher_footer.php'; ?>
