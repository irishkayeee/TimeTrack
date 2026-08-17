<?php
require_once __DIR__ . '/../includes/functions.php';
requireRole(['teacher']);
$pageTitle = 'Dashboard';
$pageSubtitle = 'Your teaching overview for today.';

$teacherId = currentTeacherId();
if ($teacherId === false) {
    flash('Your teacher profile is not set up. Contact an administrator.', 'danger');
    redirect('../dashboard.php');
}

$stmt = $mysqli->prepare('SELECT first_name FROM teachers WHERE id = ?');
$stmt->bind_param('i', $teacherId);
$stmt->execute();
$me = $stmt->get_result()->fetch_assoc();
$stmt->close();

$hour = (int) date('G');
$greeting = $hour < 12 ? 'Good morning' : ($hour < 18 ? 'Good afternoon' : 'Good evening');

$mySubjectsCount = 0;
$stmt = $mysqli->prepare('SELECT COUNT(*) FROM subjects WHERE teacher_id = ?');
$stmt->bind_param('i', $teacherId);
$stmt->execute();
$stmt->bind_result($mySubjectsCount);
$stmt->fetch();
$stmt->close();

$myStudentsCount = 0;
$sectionStmt = $mysqli->prepare('SELECT DISTINCT sec.id FROM subjects sub JOIN sections sec ON sub.section_id = sec.id WHERE sub.teacher_id = ?');
$sectionStmt->bind_param('i', $teacherId);
$sectionStmt->execute();
$sectionResult = $sectionStmt->get_result();
$allowedSections = [];
while ($row = $sectionResult->fetch_assoc()) {
    $allowedSections[] = (int) $row['id'];
}
$sectionStmt->close();
if ($allowedSections) {
    $placeholders = implode(',', array_fill(0, count($allowedSections), '?'));
    $types = str_repeat('i', count($allowedSections));
    $countStmt = $mysqli->prepare("SELECT COUNT(*) FROM students WHERE section_id IN ($placeholders)");
    $countStmt->bind_param($types, ...$allowedSections);
    $countStmt->execute();
    $countStmt->bind_result($myStudentsCount);
    $countStmt->fetch();
    $countStmt->close();
}

$dayMap = ['Mon' => 1, 'Tue' => 2, 'Wed' => 3, 'Thu' => 4, 'Fri' => 5, 'Sat' => 6, 'Sun' => 7];
$todayCode = array_search((int) date('N'), $dayMap);

$stmt = $mysqli->prepare("SELECT sub.*, sec.section_name FROM subjects sub JOIN sections sec ON sub.section_id = sec.id WHERE sub.teacher_id = ? AND sub.day_of_week = ? AND sub.status = 'active' ORDER BY sub.start_time");
$stmt->bind_param('is', $teacherId, $todayCode);
$stmt->execute();
$todaySubjects = $stmt->get_result();

$totalCounts = ['present' => 0, 'late' => 0, 'absent' => 0, 'pending' => 0];
$todaySubjectRows = [];
while ($row = $todaySubjects->fetch_assoc()) {
    $roster = getLiveRosterForSubject($mysqli, $row['id']);
    foreach ($totalCounts as $key => $value) {
        $totalCounts[$key] += $roster['counts'][$key];
    }
    $row['counts'] = $roster['counts'];
    $todaySubjectRows[] = $row;
}
$stmt->close();

require_once __DIR__ . '/../includes/teacher_header.php';
?>
<div class="card p-4 mb-3">
    <h4 class="mb-1"><?php echo htmlspecialchars($greeting); ?>, <?php echo htmlspecialchars($me['first_name'] ?? 'Teacher'); ?>! 👋</h4>
    <p class="text-muted mb-0">Here's what's happening with your classes today.</p>
</div>
<div class="row g-3">
    <div class="col-md-3">
        <div class="card p-3">
            <h6 class="text-muted small text-uppercase mb-2">My Classes</h6>
            <h2 class="mb-0"><?php echo $mySubjectsCount; ?></h2>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card p-3">
            <h6 class="text-muted small text-uppercase mb-2">My Students</h6>
            <h2 class="mb-0"><?php echo $myStudentsCount; ?></h2>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card p-3">
            <h6 class="text-muted small text-uppercase mb-2">Present Today</h6>
            <h2 class="mb-0 text-success"><?php echo $totalCounts['present']; ?></h2>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card p-3">
            <h6 class="text-muted small text-uppercase mb-2">Late / Absent Today</h6>
            <h2 class="mb-0"><span class="text-warning"><?php echo $totalCounts['late']; ?></span> / <span class="text-danger"><?php echo $totalCounts['absent']; ?></span></h2>
        </div>
    </div>
</div>
<div class="row g-3 mt-1">
    <div class="col-12">
        <div class="card p-4">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h6 class="mb-0 text-uppercase small fw-bold"><i class="fa-solid fa-calendar-day me-1"></i> Today's Sessions</h6>
                <span class="text-muted small"><?php echo date('M j, Y (l)'); ?></span>
            </div>
            <?php if (empty($todaySubjectRows)): ?>
                <p class="text-muted small mb-0">No sessions scheduled today.</p>
            <?php else: ?>
                <div class="row g-3">
                    <?php foreach ($todaySubjectRows as $row): $theme = subjectTheme($row['id']); ?>
                        <div class="col-md-4">
                            <div class="sp-subject-card">
                                <div class="sp-subject-band" style="background: <?php echo $theme['color']; ?>;">
                                    <i class="fa-solid <?php echo $theme['icon']; ?> sp-subject-icon"></i>
                                    <span class="sp-subject-code"><?php echo htmlspecialchars($row['code']); ?></span>
                                    <span class="sp-subject-name"><?php echo htmlspecialchars($row['name']); ?></span>
                                </div>
                                <div class="sp-subject-body">
                                    <div class="sp-subject-teacher">
                                        <div class="sp-subject-meta">
                                            <span class="sp-subject-prof"><?php echo htmlspecialchars($row['section_name']); ?></span>
                                            <?php echo htmlspecialchars($row['day_of_week']); ?> | <?php echo formatTime($row['start_time']); ?>
                                        </div>
                                        <i class="fa-solid fa-circle-user sp-subject-avatar fa-2x text-secondary"></i>
                                    </div>
                                    <div class="sp-subject-actions">
                                        <a href="class-details.php?id=<?php echo $row['id']; ?>"><i class="fa-solid fa-file-lines"></i>Details</a>
                                        <a href="../admin/scanner.php?subject_id=<?php echo $row['id']; ?>"><i class="fa-solid fa-qrcode"></i>Take Attendance</a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/../includes/teacher_footer.php'; ?>
