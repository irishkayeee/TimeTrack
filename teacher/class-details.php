<?php
require_once __DIR__ . '/../includes/functions.php';
requireRole(['teacher']);

$teacherId = currentTeacherId();
if ($teacherId === false) {
    flash('Your teacher profile is not set up. Contact an administrator.', 'danger');
    redirect('../dashboard.php');
}

$subjectId = intval($_GET['id'] ?? 0);
$stmt = $mysqli->prepare('SELECT sub.*, sec.section_name, sec.year_level, c.code AS course_code, c.name AS course_name
    FROM subjects sub
    JOIN sections sec ON sub.section_id = sec.id
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

$pageTitle = $subject['code'];
$pageSubtitle = $subject['name'];

$today = date('Y-m-d');
$attStmt = $mysqli->prepare('SELECT a.*, s.student_id AS student_code, CONCAT(s.first_name, " ", s.last_name) AS student_name
    FROM attendance a
    JOIN students s ON a.student_id = s.id
    WHERE a.subject_id = ? AND a.date = ?
    ORDER BY a.time DESC');
$attStmt->bind_param('is', $subjectId, $today);
$attStmt->execute();
$todayAttendance = $attStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$attStmt->close();

require_once __DIR__ . '/../includes/teacher_header.php';
?>
<a href="subjects.php" class="sp-back-link d-inline-flex mb-3"><i class="fa-solid fa-arrow-left"></i> Back to My Classes</a>

<?php renderTeacherClassHeader($subject, 'details'); ?>

<div class="card p-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h6 class="mb-0 text-uppercase small fw-bold"><i class="fa-solid fa-clipboard-check me-1"></i> Today's Attendance</h6>
        <a href="attendance.php?subject_id=<?php echo $subject['id']; ?>" class="small text-decoration-none">View Full Records <i class="fa-solid fa-arrow-right ms-1"></i></a>
    </div>
    <?php if (empty($todayAttendance)): ?>
        <p class="text-muted small mb-0">No attendance recorded yet today.</p>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead class="table-light">
                    <tr><th class="py-3">ID</th><th class="py-3">Student</th><th class="py-3">Time</th><th class="py-3">Status</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($todayAttendance as $r): ?>
                        <tr>
                            <td class="py-3"><?php echo htmlspecialchars($r['student_code']); ?></td>
                            <td class="py-3"><?php echo htmlspecialchars($r['student_name']); ?></td>
                            <td class="py-3"><?php echo formatTime($r['time']); ?></td>
                            <td class="py-3"><?php echo badgeStatus($r['status']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/teacher_footer.php'; ?>
