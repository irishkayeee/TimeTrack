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

if (!$subject || (int) $subject['room_id'] !== (int) $me['room_id']) {
    flash('Subject not found.', 'danger');
    redirect('subjects.php');
}

$pageTitle = $subject['name'];
$pageSubtitle = $subject['code'];

require_once __DIR__ . '/../includes/student_header.php';
renderSubjectPageHeader($subject, 'announcements');
?>
<div class="card p-4 text-center text-muted">
    <i class="fa-solid fa-comment-dots fa-2x mb-2"></i>
    <p class="mb-0">No announcements posted yet.</p>
</div>
<?php require_once __DIR__ . '/../includes/student_footer.php'; ?>
