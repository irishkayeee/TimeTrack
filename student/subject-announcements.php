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

$stmt = $mysqli->prepare('SELECT * FROM class_announcements WHERE subject_id = ? ORDER BY created_at DESC');
$stmt->bind_param('i', $subjectId);
$stmt->execute();
$announcements = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$pageTitle = $subject['name'];
$pageSubtitle = $subject['code'];

require_once __DIR__ . '/../includes/student_header.php';
renderSubjectPageHeader($subject, 'announcements');
?>
<?php if (empty($announcements)): ?>
<div class="card sp-empty-state">
    <div class="sp-empty-state-icon"><i class="fa-solid fa-comment-dots"></i></div>
    <h5 class="sp-empty-state-title">No Announcements Yet</h5>
    <p class="sp-empty-state-text">Your instructor hasn't posted anything for this class yet. Check back soon for updates.</p>
</div>
<?php else: ?>
<div class="d-flex flex-column gap-3">
    <?php foreach ($announcements as $a): ?>
    <div class="card p-3 sp-announce-card">
        <div class="d-flex align-items-start gap-3">
            <?php if (!empty($subject['teacher_photo'])): ?>
                <img src="../<?php echo htmlspecialchars($subject['teacher_photo']); ?>" class="sp-announce-avatar" alt="">
            <?php else: ?>
                <div class="sp-announce-avatar sp-announce-avatar-fallback"><i class="fa-solid fa-user"></i></div>
            <?php endif; ?>
            <div class="flex-grow-1">
                <div class="d-flex align-items-center justify-content-between gap-2 mb-1 flex-wrap">
                    <strong><?php echo htmlspecialchars($a['title']); ?></strong>
                    <small class="text-muted">
                        <?php echo formatDateTime($a['created_at']); ?><?php if ($a['updated_at']): ?> · Edited<?php endif; ?>
                    </small>
                </div>
                <p class="mb-1 text-muted small"><?php echo htmlspecialchars($subject['teacher_name'] ?: 'Instructor'); ?></p>
                <p class="mb-0 sp-announce-message"><?php echo nl2br(htmlspecialchars($a['message'])); ?></p>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>
<?php require_once __DIR__ . '/../includes/student_footer.php'; ?>
