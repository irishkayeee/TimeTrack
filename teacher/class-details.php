<?php
require_once __DIR__ . '/../includes/functions.php';
requireRole(['teacher']);

$teacherId = currentTeacherId();
if ($teacherId === false) {
    flash('Your teacher profile is not set up. Contact an administrator.', 'danger');
    redirect('../dashboard.php');
}

$subjectId = intval($_GET['id'] ?? 0);
$stmt = $mysqli->prepare('SELECT sub.*, sec.section_name FROM subjects sub JOIN sections sec ON sub.section_id = sec.id WHERE sub.id = ? AND sub.teacher_id = ? LIMIT 1');
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
$theme = subjectTheme($subject['id']);

require_once __DIR__ . '/../includes/teacher_header.php';
?>
<a href="subjects.php" class="text-decoration-none small d-inline-block mb-3"><i class="fa-solid fa-arrow-left me-1"></i> Back to My Classes</a>

<div class="card p-4 mb-3">
    <div class="d-flex align-items-start gap-3 flex-wrap">
        <div class="sp-mc-icon-box" style="--mc-color: <?php echo $theme['color']; ?>; width:56px; height:56px; font-size:1.4rem;"><i class="fa-solid <?php echo $theme['icon']; ?>"></i></div>
        <div class="flex-grow-1">
            <h4 class="mb-1"><?php echo htmlspecialchars($subject['code']); ?> — <?php echo htmlspecialchars($subject['name']); ?></h4>
            <div class="sp-mc-meta">
                <span><i class="fa-solid fa-calendar"></i> <?php echo htmlspecialchars($subject['day_of_week']); ?> | <?php echo formatTime($subject['start_time']); ?><?php echo $subject['end_time'] ? ' - ' . formatTime($subject['end_time']) : ''; ?></span>
                <span><i class="fa-solid fa-location-dot"></i> <?php echo htmlspecialchars($subject['room'] ?: 'No room set'); ?></span>
                <span><i class="fa-solid fa-user-group"></i> <?php echo htmlspecialchars($subject['section_name']); ?></span>
                <?php if ($subject['credit_units']): ?><span><i class="fa-solid fa-award"></i> <?php echo (int) $subject['credit_units']; ?> units</span><?php endif; ?>
            </div>
        </div>
        <span class="badge <?php echo $subject['status'] === 'active' ? 'sp-mc-badge sp-mc-badge-ongoing' : 'sp-mc-badge sp-mc-badge-inactive'; ?>"><?php echo $subject['status'] === 'active' ? 'Active' : 'Inactive'; ?></span>
    </div>
    <?php if ($subject['important_note']): ?>
        <div class="alert alert-warning mt-3 mb-0"><i class="fa-solid fa-circle-exclamation me-1"></i> <?php echo nl2br(htmlspecialchars($subject['important_note'])); ?></div>
    <?php endif; ?>
</div>

<div class="sp-detail-tabs">
    <a href="attendance.php?subject_id=<?php echo $subject['id']; ?>"><i class="fa-solid fa-clipboard-check"></i> Attendance</a>
    <a href="students.php?subject_id=<?php echo $subject['id']; ?>"><i class="fa-solid fa-users"></i> Students</a>
</div>

<?php require_once __DIR__ . '/../includes/teacher_footer.php'; ?>
