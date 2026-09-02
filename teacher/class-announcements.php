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

$pageTitle = $subject['code'];
$pageSubtitle = $subject['name'];

require_once __DIR__ . '/../includes/teacher_header.php';
?>
<a href="subjects.php" class="sp-back-link d-inline-flex mb-3"><i class="fa-solid fa-arrow-left"></i> Back to My Classes</a>

<?php renderTeacherClassHeader($subject, 'announcements'); ?>

<div class="card p-4 text-center text-muted">
    <i class="fa-solid fa-bullhorn fa-2x mb-2"></i>
    <p class="mb-0">Posting announcements for this class isn't available yet.</p>
</div>

<?php require_once __DIR__ . '/../includes/teacher_footer.php'; ?>
