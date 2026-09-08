<?php
require_once __DIR__ . '/../includes/functions.php';
requireRole(['student']);

$studentDbId = currentStudentId();
if ($studentDbId === false) {
    flash('Your student profile is not set up. Contact an administrator.', 'danger');
    redirect('../dashboard.php');
}

$stmt = $mysqli->prepare('SELECT room_id FROM students WHERE id = ?');
$stmt->bind_param('i', $studentDbId);
$stmt->execute();
$me = $stmt->get_result()->fetch_assoc();
$stmt->close();

$subjectId = intval($_GET['id'] ?? 0);
$stmt = $mysqli->prepare("SELECT sub.*, CONCAT(t.first_name, ' ', t.last_name) AS teacher_name, t.email AS teacher_email, t.photo AS teacher_photo FROM subjects sub LEFT JOIN teachers t ON sub.teacher_id = t.id WHERE sub.id = ? LIMIT 1");
$stmt->bind_param('i', $subjectId);
$stmt->execute();
$subject = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!studentCanAccessSubject($mysqli, $studentDbId, $subject, $me['room_id'])) {
    flash('Subject not found.', 'danger');
    redirect('subjects.php');
}

$subjectRoomId = (int) $subject['room_id'];
$stmt = $mysqli->prepare('SELECT DISTINCT s.id, s.student_id, s.first_name, s.last_name, s.photo, s.year_level, c.code AS course_code
    FROM students s
    LEFT JOIN courses c ON s.course_id = c.id
    LEFT JOIN enrollments e ON e.student_id = s.id AND e.subject_id = ?
    WHERE (s.room_id = ? OR e.id IS NOT NULL) AND s.id != ?
    ORDER BY s.first_name, s.last_name');
$stmt->bind_param('iii', $subjectId, $subjectRoomId, $studentDbId);
$stmt->execute();
$classmates = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$pageTitle = $subject['name'];
$pageSubtitle = $subject['code'];

require_once __DIR__ . '/../includes/student_header.php';
renderSubjectPageHeader($subject, 'classmates');
?>
<?php if (empty($classmates)): ?>
<div class="card sp-empty-state">
    <div class="sp-empty-state-icon"><i class="fa-solid fa-user-group"></i></div>
    <h5 class="sp-empty-state-title">No Classmates Yet</h5>
    <p class="sp-empty-state-text">No one else is enrolled in this class yet.</p>
</div>
<?php else: ?>
<div class="card p-4">
    <h6 class="mb-3"><?php echo count($classmates); ?> Classmate<?php echo count($classmates) === 1 ? '' : 's'; ?></h6>
    <div class="row g-3">
        <?php foreach ($classmates as $cm): ?>
        <div class="col-md-6 col-lg-4">
            <div class="sp-classmate-card">
                <?php if (!empty($cm['photo'])): ?>
                    <img src="../<?php echo htmlspecialchars($cm['photo']); ?>" class="sp-announce-avatar" alt="">
                <?php else: ?>
                    <div class="sp-announce-avatar sp-announce-avatar-fallback"><i class="fa-solid fa-user"></i></div>
                <?php endif; ?>
                <div class="flex-grow-1 min-w-0">
                    <div class="fw-semibold text-truncate"><?php echo htmlspecialchars(trim($cm['first_name'] . ' ' . $cm['last_name'])); ?></div>
                    <div class="text-muted small text-truncate"><?php echo htmlspecialchars(trim(($cm['course_code'] ?: '') . ($cm['year_level'] ? ' · ' . $cm['year_level'] : '')) ?: '—'); ?></div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>
<?php require_once __DIR__ . '/../includes/student_footer.php'; ?>
