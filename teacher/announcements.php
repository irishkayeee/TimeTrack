<?php
require_once __DIR__ . '/../includes/functions.php';
requireRole(['teacher']);
$pageTitle = 'Announcements';
$pageSubtitle = 'Post updates for your classes.';

$teacherId = currentTeacherId();
if ($teacherId === false) {
    flash('Your teacher profile is not set up. Contact an administrator.', 'danger');
    redirect('../dashboard.php');
}

require_once __DIR__ . '/../includes/teacher_header.php';
?>
<div class="card p-4 text-center text-muted">
    <i class="fa-solid fa-bullhorn fa-2x mb-2"></i>
    <p class="mb-0">Announcements aren't available yet. This feature is coming soon.</p>
</div>
<?php require_once __DIR__ . '/../includes/teacher_footer.php'; ?>
