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

$countStmt = $mysqli->prepare("SELECT COUNT(*) AS total, SUM(status IN ('present','late')) AS attended, SUM(status = 'present') AS present, SUM(status = 'late') AS late, SUM(status = 'absent') AS absent FROM attendance WHERE student_id = ? AND subject_id = ?");
$countStmt->bind_param('ii', $studentDbId, $subjectId);
$countStmt->execute();
$counts = $countStmt->get_result()->fetch_assoc();
$countStmt->close();
$total = (int) $counts['total'];
$present = (int) $counts['present'];
$late = (int) $counts['late'];
$absent = (int) $counts['absent'];
$rate = $total ? round((($present + $late) / $total) * 100, 1) : null;

$dayMap = ['Mon' => 1, 'Tue' => 2, 'Wed' => 3, 'Thu' => 4, 'Fri' => 5, 'Sat' => 6, 'Sun' => 7];
$targetDow = $dayMap[$subject['day_of_week']] ?? 1;
$todayDow = (int) date('N');
$daysUntil = ($targetDow - $todayDow + 7) % 7;
$nextDate = date('Y-m-d', strtotime("+{$daysUntil} days"));
$nextDateTime = strtotime($nextDate . ' ' . $subject['start_time']);
if ($nextDateTime < time()) {
    $nextDate = date('Y-m-d', strtotime('+7 days'));
    $daysUntil = 7;
    $nextDateTime = strtotime($nextDate . ' ' . $subject['start_time']);
}
$minutesUntil = round(($nextDateTime - time()) / 60);

$absentCutoff = effectiveAbsentCutoff($subject);

require_once __DIR__ . '/../includes/student_header.php';
renderSubjectPageHeader($subject, 'overview');
?>
        <div class="card p-4 mb-3">
            <h6 class="mb-3">Attendance Overview</h6>
            <div class="row g-3">
                <div class="col-6 col-md-3">
                    <div class="text-success fw-bold fs-4"><?php echo $rate === null ? '—' : $rate . '%'; ?></div>
                    <div class="text-muted small">Attendance Rate</div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="text-success fw-bold fs-4"><?php echo $present; ?></div>
                    <div class="text-muted small">Present</div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="text-warning fw-bold fs-4"><?php echo $late; ?></div>
                    <div class="text-muted small">Late</div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="text-danger fw-bold fs-4"><?php echo $absent; ?></div>
                    <div class="text-muted small">Absent</div>
                </div>
            </div>
        </div>

        <div class="row g-3 mb-3">
            <div class="col-md-6">
                <div class="card p-4 h-100">
                    <h6 class="mb-3">Next Class</h6>
                    <p class="mb-1"><i class="fa-solid fa-calendar-day me-2 text-muted"></i><?php echo formatDate($nextDate); ?></p>
                    <p class="mb-1"><i class="fa-solid fa-clock me-2 text-muted"></i><?php echo formatTime($subject['start_time']); ?><?php echo $subject['end_time'] ? ' - ' . formatTime($subject['end_time']) : ''; ?></p>
                    <?php if ($subject['subject_room']): ?>
                        <p class="mb-2"><i class="fa-solid fa-location-dot me-2 text-muted"></i><?php echo htmlspecialchars($subject['subject_room']); ?></p>
                    <?php endif; ?>
                    <?php if ($daysUntil === 0 && $minutesUntil >= 0): ?>
                        <span class="badge bg-primary rounded-pill">Class starts in <?php echo $minutesUntil; ?> minutes</span>
                    <?php endif; ?>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card p-4 h-100">
                    <h6 class="mb-3"><i class="fa-solid fa-thumbtack me-1 text-muted"></i> Important Note</h6>
                    <?php if (!empty($subject['important_note'])): ?>
                        <p class="mb-0 small"><?php echo nl2br(htmlspecialchars($subject['important_note'])); ?></p>
                    <?php else: ?>
                        <p class="mb-0 small text-muted">No notes from your teacher yet.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="card p-4">
            <h6 class="mb-3">Attendance Policy</h6>
            <div class="sp-policy-item">
                <span class="sp-policy-dot bg-success"></span>
                <span>On time or earlier: marked as <strong>Present</strong></span>
            </div>
            <div class="sp-policy-item">
                <span class="sp-policy-dot bg-warning"></span>
                <span>1&ndash;<?php echo $absentCutoff - 1; ?> minutes late: marked as <strong>Late</strong></span>
            </div>
            <div class="sp-policy-item mb-0">
                <span class="sp-policy-dot bg-danger"></span>
                <span><?php echo $absentCutoff; ?>+ minutes late or no scan: automatically marked as <strong>Absent</strong></span>
            </div>
        </div>
<?php require_once __DIR__ . '/../includes/student_footer.php'; ?>
