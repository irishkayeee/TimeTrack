<?php
require_once __DIR__ . '/../includes/functions.php';
requireRole(['student']);
$pageTitle = 'Notifications';
$pageSubtitle = 'Stay updated on your attendance.';

$studentDbId = currentStudentId();
if ($studentDbId === false) {
    flash('Your student profile is not set up. Contact an administrator.', 'danger');
    redirect('../dashboard.php');
}

$stmt = $mysqli->prepare('SELECT n.*, s.name AS subject_name, s.code AS subject_code FROM notifications n LEFT JOIN subjects s ON n.subject_id = s.id WHERE n.student_id = ? ORDER BY n.created_at DESC');
$stmt->bind_param('i', $studentDbId);
$stmt->execute();
$notifications = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$update = $mysqli->prepare('UPDATE notifications SET is_read = 1 WHERE student_id = ? AND is_read = 0');
$update->bind_param('i', $studentDbId);
$update->execute();
$update->close();

$notifIcons = ['present' => 'fa-circle-check', 'late' => 'fa-clock', 'absent' => 'fa-triangle-exclamation'];

require_once __DIR__ . '/../includes/student_header.php';
?>
<?php if (empty($notifications)): ?>
<div class="card p-4 text-center text-muted">
    <i class="fa-solid fa-bell fa-2x mb-2"></i>
    <p class="mb-0">No notifications yet.</p>
</div>
<?php else: ?>
<div class="d-flex flex-column gap-2">
    <?php foreach ($notifications as $n): ?>
    <div class="card p-3">
        <div class="d-flex align-items-start gap-3">
            <i class="fa-solid <?php echo $notifIcons[$n['type']] ?? 'fa-bell'; ?> fa-lg mt-1"></i>
            <div class="flex-grow-1">
                <div class="d-flex align-items-center gap-2">
                    <strong><?php echo htmlspecialchars($n['title']); ?></strong>
                    <?php echo badgeStatus($n['type']); ?>
                    <?php if (!$n['is_read']): ?>
                        <span class="badge bg-primary">New</span>
                    <?php endif; ?>
                </div>
                <p class="mb-1 text-muted"><?php echo htmlspecialchars($n['message']); ?></p>
                <small class="text-muted">
                    <?php echo $n['subject_code'] ? htmlspecialchars($n['subject_code']) . ' &middot; ' : ''; ?>
                    <?php echo date('F j, Y g:i A', strtotime($n['created_at'])); ?>
                </small>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>
<?php require_once __DIR__ . '/../includes/student_footer.php'; ?>
