<?php
require_once __DIR__ . '/../includes/functions.php';
requireRole(['student']);

$studentDbId = currentStudentId();
if ($studentDbId === false) {
    flash('Your student profile is not set up. Contact an administrator.', 'danger');
    redirect('../dashboard.php');
}

$stmt = $mysqli->prepare('SELECT section_id FROM students WHERE id = ?');
$stmt->bind_param('i', $studentDbId);
$stmt->execute();
$me = $stmt->get_result()->fetch_assoc();
$stmt->close();

$subjectId = intval($_GET['subject_id'] ?? 0);
$stmt = $mysqli->prepare("SELECT sub.*, CONCAT(t.first_name, ' ', t.last_name) AS teacher_name, t.email AS teacher_email, t.photo AS teacher_photo FROM subjects sub LEFT JOIN teachers t ON sub.teacher_id = t.id WHERE sub.id = ? LIMIT 1");
$stmt->bind_param('i', $subjectId);
$stmt->execute();
$subject = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$subject || (int) $subject['section_id'] !== (int) $me['section_id']) {
    flash('Subject not found.', 'danger');
    redirect('subjects.php');
}

$pageTitle = $subject['name'];
$pageSubtitle = $subject['code'];

$statusFilter = sanitize($_GET['status'] ?? 'all');
$query = 'SELECT * FROM attendance WHERE student_id = ? AND subject_id = ?';
$types = 'ii';
$params = [$studentDbId, $subjectId];
if (in_array($statusFilter, ['present', 'late', 'absent', 'excused'])) {
    $query .= ' AND status = ?';
    $types .= 's';
    $params[] = $statusFilter;
}
$query .= ' ORDER BY date DESC, time DESC';
$history = $mysqli->prepare($query);
$history->bind_param($types, ...$params);
$history->execute();
$historyResult = $history->get_result();

$statusMeta = [
    'present' => ['icon' => 'fa-circle-check', 'class' => 'sp-status-present', 'label' => 'Present'],
    'late' => ['icon' => 'fa-clock', 'class' => 'sp-status-late', 'label' => 'Late'],
    'absent' => ['icon' => 'fa-circle-xmark', 'class' => 'sp-status-absent', 'label' => 'Absent'],
    'excused' => ['icon' => 'fa-circle-info', 'class' => 'sp-status-excused', 'label' => 'Excused'],
];

require_once __DIR__ . '/../includes/student_header.php';
renderSubjectPageHeader($subject, 'attendance');
?>
<div class="card p-4">
    <h6 class="mb-3">Attendance History</h6>
    <form method="get" class="d-flex gap-2 mb-3 flex-wrap">
        <input type="hidden" name="subject_id" value="<?php echo $subject['id']; ?>">
        <select class="form-select sp-filter-select" disabled>
            <option>This Semester (<?php echo htmlspecialchars(getSetting('semester', '1st Semester')); ?>)</option>
        </select>
        <select class="form-select sp-filter-select" name="status" onchange="this.form.submit()">
            <option value="all" <?php echo $statusFilter === 'all' ? 'selected' : ''; ?>>All Status</option>
            <option value="present" <?php echo $statusFilter === 'present' ? 'selected' : ''; ?>>Present</option>
            <option value="late" <?php echo $statusFilter === 'late' ? 'selected' : ''; ?>>Late</option>
            <option value="absent" <?php echo $statusFilter === 'absent' ? 'selected' : ''; ?>>Absent</option>
        </select>
    </form>
    <div class="table-responsive">
        <table class="table sp-history-table mb-0">
            <thead>
                <tr><th>Date</th><th>Day</th><th>Time</th><th>Status</th><th>Remarks</th></tr>
            </thead>
            <tbody>
                <?php if ($historyResult->num_rows === 0): ?>
                    <tr><td colspan="5" class="text-center text-muted py-4">No attendance recorded yet.</td></tr>
                <?php endif; ?>
                <?php while ($row = $historyResult->fetch_assoc()): ?>
                    <?php
                    $meta = $statusMeta[$row['status']] ?? $statusMeta['excused'];
                    $remarks = '—';
                    if ($row['status'] === 'present') {
                        $remarks = 'On time';
                    } elseif ($row['status'] === 'late') {
                        $lateMinutes = max(0, round((strtotime($row['time']) - strtotime($subject['start_time'])) / 60));
                        $remarks = 'Arrived ' . formatTime($row['time']);
                    } elseif ($row['status'] === 'absent') {
                        $remarks = '—';
                    }
                    ?>
                    <tr>
                        <td><?php echo formatDate($row['date']); ?></td>
                        <td><?php echo date('D', strtotime($row['date'])); ?></td>
                        <td><?php echo formatTime($subject['start_time']); ?><?php echo $subject['end_time'] ? ' - ' . formatTime($subject['end_time']) : ''; ?></td>
                        <td class="<?php echo $meta['class']; ?>">
                            <i class="fa-solid <?php echo $meta['icon']; ?> me-1"></i>
                            <?php echo $meta['label']; ?><?php echo $row['status'] === 'late' ? ' (' . $lateMinutes . ' min)' : ''; ?>
                        </td>
                        <td class="text-muted"><?php echo htmlspecialchars($remarks); ?></td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>
<?php require_once __DIR__ . '/../includes/student_footer.php'; ?>
