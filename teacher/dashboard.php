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

// Counts students explicitly enrolled in any of this teacher's classes — room/section
// membership alone no longer counts as being one of "my students".
$myStudentsCount = 0;
$countStmt = $mysqli->prepare('SELECT COUNT(DISTINCT e.student_id) FROM enrollments e JOIN subjects sub ON e.subject_id = sub.id WHERE sub.teacher_id = ?');
$countStmt->bind_param('i', $teacherId);
$countStmt->execute();
$countStmt->bind_result($myStudentsCount);
$countStmt->fetch();
$countStmt->close();

$myClassesStmt = $mysqli->prepare('SELECT sub.code, sub.name, sub.day_of_week, sub.start_time, sub.end_time, sec.room_name FROM subjects sub LEFT JOIN rooms sec ON sub.room_id = sec.id WHERE sub.teacher_id = ? ORDER BY sub.code');
$myClassesStmt->bind_param('i', $teacherId);
$myClassesStmt->execute();
$myClassesList = $myClassesStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$myClassesStmt->close();

$myStudentsStmt = $mysqli->prepare("SELECT s.student_id, s.first_name, s.last_name, GROUP_CONCAT(DISTINCT sub.code ORDER BY sub.code SEPARATOR ', ') AS classes FROM enrollments e JOIN subjects sub ON e.subject_id = sub.id JOIN students s ON e.student_id = s.id WHERE sub.teacher_id = ? GROUP BY s.id ORDER BY s.last_name, s.first_name");
$myStudentsStmt->bind_param('i', $teacherId);
$myStudentsStmt->execute();
$myStudentsList = $myStudentsStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$myStudentsStmt->close();

$dayMap = ['Mon' => 1, 'Tue' => 2, 'Wed' => 3, 'Thu' => 4, 'Fri' => 5, 'Sat' => 6, 'Sun' => 7];
$todayCode = array_search((int) date('N'), $dayMap);

$stmt = $mysqli->prepare("SELECT sub.*, sec.room_name FROM subjects sub JOIN rooms sec ON sub.room_id = sec.id WHERE sub.teacher_id = ? AND sub.day_of_week = ? AND sub.status = 'active' ORDER BY sub.start_time");
$stmt->bind_param('is', $teacherId, $todayCode);
$stmt->execute();
$todaySubjects = $stmt->get_result();

$totalCounts = ['present' => 0, 'late' => 0, 'absent' => 0, 'pending' => 0];
// Students behind today's present/late/absent counts, listed in the stat card modals.
$todayStatusLists = ['present' => [], 'late' => [], 'absent' => []];
$collectStatusLists = function ($roster, $classCode) use (&$todayStatusLists) {
    foreach ($roster['rows'] as $student) {
        if (isset($todayStatusLists[$student['display_status']])) {
            $todayStatusLists[$student['display_status']][] = [
                'name' => $student['first_name'] . ' ' . $student['last_name'],
                'class' => $classCode,
                'time' => $student['scan_time'],
            ];
        }
    }
};
$todaySubjectRows = [];
$todaySubjectIds = [];
while ($row = $todaySubjects->fetch_assoc()) {
    $roster = getLiveRosterForSubject($mysqli, $row['id']);
    foreach ($totalCounts as $key => $value) {
        $totalCounts[$key] += $roster['counts'][$key];
    }
    $collectStatusLists($roster, $row['code']);
    $row['counts'] = $roster['counts'];
    $row['is_makeup'] = false;
    $todaySubjectRows[] = $row;
    $todaySubjectIds[] = (int) $row['id'];
}
$stmt->close();

// Today's makeup sessions (one-time extra classes) sit alongside the regular
// schedule without ever changing it.
$makeupStmt = $mysqli->prepare("SELECT sub.*, sec.room_name, ms.start_time AS makeup_start_time, ms.end_time AS makeup_end_time, ms.note AS makeup_note FROM makeup_sessions ms JOIN subjects sub ON ms.subject_id = sub.id JOIN rooms sec ON sub.room_id = sec.id WHERE ms.teacher_id = ? AND ms.session_date = CURDATE()");
$makeupStmt->bind_param('i', $teacherId);
$makeupStmt->execute();
$makeupResult = $makeupStmt->get_result();
while ($row = $makeupResult->fetch_assoc()) {
    if (in_array((int) $row['id'], $todaySubjectIds, true)) {
        continue;
    }
    $row['start_time'] = $row['makeup_start_time'];
    $row['end_time'] = $row['makeup_end_time'];
    $roster = getLiveRosterForSubject($mysqli, $row['id']);
    foreach ($totalCounts as $key => $value) {
        $totalCounts[$key] += $roster['counts'][$key];
    }
    $collectStatusLists($roster, $row['code']);
    $row['counts'] = $roster['counts'];
    $row['is_makeup'] = true;
    $todaySubjectRows[] = $row;
}
$makeupStmt->close();
usort($todaySubjectRows, function ($a, $b) {
    return strcmp($a['start_time'], $b['start_time']);
});

// ---- Analytics (scoped to this teacher's own classes) ----
$trendLabels = [];
$trendData = [];
$trendStmt = $mysqli->prepare("SELECT YEARWEEK(a.date, 1) AS yw, MIN(a.date) AS week_start, SUM(a.status IN ('present','late')) AS attended, COUNT(*) AS total FROM attendance a JOIN subjects sub ON a.subject_id = sub.id WHERE sub.teacher_id = ? AND a.date >= DATE_SUB(CURDATE(), INTERVAL 8 WEEK) GROUP BY yw ORDER BY yw");
$trendStmt->bind_param('i', $teacherId);
$trendStmt->execute();
$trendResult = $trendStmt->get_result();
while ($row = $trendResult->fetch_assoc()) {
    $trendLabels[] = date('M j', strtotime($row['week_start']));
    $trendData[] = $row['total'] ? round($row['attended'] / $row['total'] * 100) : 0;
}
$trendStmt->close();

$classLabels = [];
$classData = [];
$classStmt = $mysqli->prepare("SELECT sub.code, sec.room_name, SUM(a.status IN ('present','late')) AS attended, COUNT(a.id) AS total FROM attendance a JOIN subjects sub ON a.subject_id = sub.id JOIN rooms sec ON sub.room_id = sec.id WHERE sub.teacher_id = ? GROUP BY sub.code, sec.id ORDER BY sub.code");
$classStmt->bind_param('i', $teacherId);
$classStmt->execute();
$classResult = $classStmt->get_result();
while ($row = $classResult->fetch_assoc()) {
    $classLabels[] = $row['code'] . ' - ' . $row['room_name'];
    $classData[] = $row['total'] ? round($row['attended'] / $row['total'] * 100) : 0;
}
$classStmt->close();

$dowLabels = [];
$dowData = [];
$dowStmt = $mysqli->prepare("SELECT DAYOFWEEK(a.date) AS dnum, DAYNAME(a.date) AS dname, SUM(a.status = 'absent') AS absents FROM attendance a JOIN subjects sub ON a.subject_id = sub.id WHERE sub.teacher_id = ? GROUP BY dnum, dname ORDER BY dnum");
$dowStmt->bind_param('i', $teacherId);
$dowStmt->execute();
$dowResult = $dowStmt->get_result();
while ($row = $dowResult->fetch_assoc()) {
    $dowLabels[] = $row['dname'];
    $dowData[] = (int) $row['absents'];
}
$dowStmt->close();

$hourLabels = [];
$hourData = [];
$hourStmt = $mysqli->prepare("SELECT HOUR(a.time) AS hr, COUNT(*) AS cnt FROM attendance a JOIN subjects sub ON a.subject_id = sub.id WHERE sub.teacher_id = ? GROUP BY hr ORDER BY hr");
$hourStmt->bind_param('i', $teacherId);
$hourStmt->execute();
$hourResult = $hourStmt->get_result();
while ($row = $hourResult->fetch_assoc()) {
    $hourLabels[] = date('g A', strtotime($row['hr'] . ':00'));
    $hourData[] = (int) $row['cnt'];
}
$hourStmt->close();

$atRiskStmt = $mysqli->prepare("SELECT s.id, s.first_name, s.last_name, s.student_id, s.email, s.guardian_name, s.guardian_email, sec.room_name, SUM(a.status = 'absent') AS absents, SUM(a.status = 'late') AS lates, COUNT(a.id) AS total FROM attendance a JOIN students s ON a.student_id = s.id JOIN subjects sub ON a.subject_id = sub.id LEFT JOIN rooms sec ON s.room_id = sec.id WHERE sub.teacher_id = ? GROUP BY s.id HAVING (SUM(a.status = 'absent') + SUM(a.status = 'late')) > 0 ORDER BY (SUM(a.status = 'absent') * 2 + SUM(a.status = 'late')) DESC LIMIT 10");
$atRiskStmt->bind_param('i', $teacherId);
$atRiskStmt->execute();
$atRiskResult = $atRiskStmt->get_result();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'send_at_risk_email') {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        flash('Invalid request.', 'danger');
        redirect('dashboard.php');
    }
    $studentId = (int) ($_POST['student_id'] ?? 0);
    $emailSubject = trim($_POST['subject'] ?? '');
    $emailMessage = trim($_POST['message'] ?? '');
    $checkStmt = $mysqli->prepare("SELECT s.id, s.first_name, s.last_name, s.email, s.guardian_name, s.guardian_email FROM students s JOIN attendance a ON a.student_id = s.id JOIN subjects sub ON a.subject_id = sub.id WHERE sub.teacher_id = ? AND s.id = ? GROUP BY s.id");
    $checkStmt->bind_param('ii', $teacherId, $studentId);
    $checkStmt->execute();
    $student = $checkStmt->get_result()->fetch_assoc();
    $checkStmt->close();

    if (!$student) {
        flash('Student not found.', 'danger');
    } elseif (empty($student['email']) && empty($student['guardian_email'])) {
        flash('No email on file for this student.', 'warning');
    } elseif ($emailSubject === '' || $emailMessage === '') {
        flash('Subject and message are required.', 'danger');
    } elseif (sendComposedStudentEmail($student, $emailSubject, $emailMessage)) {
        flash('Email sent to ' . trim($student['first_name'] . ' ' . $student['last_name']) . '.', 'success');
    } else {
        flash('Could not send email. Check mail settings.', 'danger');
    }
    redirect('dashboard.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'export_at_risk_pdf') {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        flash('Invalid request.', 'danger');
        redirect('dashboard.php');
    }
    $rows = [];
    while ($row = $atRiskResult->fetch_assoc()) {
        $rows[] = [$row['student_id'], $row['first_name'] . ' ' . $row['last_name'], $row['room_name'] ?: '-', (int) $row['absents'], (int) $row['lates']];
    }
    $pdf = generateSimpleTablePdf('At-Risk Students', ['ID', 'Name', 'Room', 'Absent', 'Late'], $rows, [90, 230, 190, 100, 100]);
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="at_risk_students_' . date('Ymd') . '.pdf"');
    header('Content-Length: ' . strlen($pdf));
    echo $pdf;
    exit;
}

require_once __DIR__ . '/../includes/teacher_header.php';
?>
<div class="card p-4 mb-3 sp-greeting-card">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
        <div>
            <h4 class="mb-1"><?php echo htmlspecialchars($greeting); ?>, Prof. <?php echo htmlspecialchars($me['first_name'] ?? 'Teacher'); ?>! <span class="sp-greeting-wave">👋</span></h4>
            <p class="text-muted mb-0">Here's what's happening with your classes today.</p>
        </div>
        <div class="sp-clock-widget" aria-live="off">
            <span class="sp-clock-widget__icon"><i class="fa-regular fa-clock"></i></span>
            <div>
                <div class="sp-clock-widget__time" id="spClockTime"><?php echo date('g:i:s A'); ?></div>
                <div class="sp-clock-widget__date" id="spClockDate"><?php echo date('l, F j, Y'); ?></div>
            </div>
        </div>
    </div>
</div>
<script>
(function () {
    var timeEl = document.getElementById('spClockTime');
    var dateEl = document.getElementById('spClockDate');
    function tick() {
        var now = new Date();
        timeEl.textContent = now.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', second: '2-digit' });
        dateEl.textContent = now.toLocaleDateString('en-US', { weekday: 'long', month: 'long', day: 'numeric', year: 'numeric' });
    }
    tick();
    setInterval(tick, 1000);
})();
</script>
<?php $presentTodayPct = $myStudentsCount ? min(100, round($totalCounts['present'] / $myStudentsCount * 100)) : 0; ?>
<div class="row g-3">
    <div class="col-md-3">
        <div class="stat-card-v2 stat-card-v2--purple" data-bs-toggle="modal" data-bs-target="#myClassesModal" role="button" tabindex="0">
            <div class="stat-card-v2__top">
                <span class="stat-card-v2__icon"><i class="fa-solid fa-chalkboard"></i></span>
                <span class="stat-card-v2__label">My Classes</span>
                <i class="fa-solid fa-chevron-right stat-card-v2__chevron"></i>
            </div>
            <div class="stat-card-v2__value"><?php echo $mySubjectsCount; ?></div>
            <div class="stat-card-v2__sublabel">Active this term</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card-v2 stat-card-v2--blue" data-bs-toggle="modal" data-bs-target="#myStudentsModal" role="button" tabindex="0">
            <div class="stat-card-v2__top">
                <span class="stat-card-v2__icon"><i class="fa-solid fa-user-graduate"></i></span>
                <span class="stat-card-v2__label">My Students</span>
                <i class="fa-solid fa-chevron-right stat-card-v2__chevron"></i>
            </div>
            <div class="stat-card-v2__value"><?php echo $myStudentsCount; ?></div>
            <div class="stat-card-v2__sublabel">Across all classes</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card-v2 stat-card-v2--green" data-bs-toggle="modal" data-bs-target="#presentTodayModal" role="button" tabindex="0">
            <div class="stat-card-v2__top">
                <span class="stat-card-v2__icon"><i class="fa-solid fa-circle-check"></i></span>
                <span class="stat-card-v2__label">Present Today</span>
                <i class="fa-solid fa-chevron-right stat-card-v2__chevron"></i>
            </div>
            <div class="stat-card-v2__value"><?php echo $totalCounts['present']; ?></div>
            <div class="stat-card-v2__progress"><div class="stat-card-v2__progress-bar" style="width: <?php echo $presentTodayPct; ?>%"></div></div>
            <div class="stat-card-v2__sublabel"><?php echo $presentTodayPct; ?>% of students</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card-v2 stat-card-v2--orange" data-bs-toggle="modal" data-bs-target="#lateAbsentTodayModal" role="button" tabindex="0">
            <div class="stat-card-v2__top">
                <span class="stat-card-v2__icon"><i class="fa-solid fa-triangle-exclamation"></i></span>
                <span class="stat-card-v2__label">Late / Absent Today</span>
                <i class="fa-solid fa-chevron-right stat-card-v2__chevron"></i>
            </div>
            <div class="stat-card-v2__value"><span class="text-warning"><?php echo $totalCounts['late']; ?></span> / <span class="text-danger"><?php echo $totalCounts['absent']; ?></span></div>
            <div class="stat-card-v2__sublabel">Late / Absent</div>
        </div>
    </div>
</div>
<div class="row g-3 mt-1">
    <div class="col-12">
        <div class="card p-4">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h6 class="mb-0 sp-script-title"><i class="fa-solid fa-calendar-day me-1"></i> Today's Sessions</h6>
                <span class="text-muted small"><?php echo date('M j, Y (l)'); ?></span>
            </div>
            <?php if (empty($todaySubjectRows)): ?>
                <p class="text-muted small mb-0">No sessions scheduled today.</p>
            <?php else: ?>
                <div class="row g-3">
                    <?php foreach ($todaySubjectRows as $row): $theme = subjectTheme($row); ?>
                        <div class="col-sm-6 col-lg-4 col-xl-3">
                            <div class="sp-subject-card sp-subject-card--compact">
                                <div class="sp-subject-band <?php echo $theme['band_class']; ?>" style="<?php echo $theme['band_style']; ?>">
                                    <i class="fa-solid <?php echo $theme['icon']; ?> sp-subject-icon"></i>
                                    <span class="sp-subject-code"><?php echo htmlspecialchars($row['code']); ?></span>
                                    <span class="sp-subject-name"><?php echo htmlspecialchars($row['name']); ?></span>
                                    <?php if ($row['is_makeup']): ?><span class="badge bg-warning text-dark ms-1">Makeup</span><?php endif; ?>
                                </div>
                                <div class="sp-subject-body">
                                    <div class="sp-subject-teacher">
                                        <div class="sp-subject-meta">
                                            <span class="sp-subject-prof"><?php echo htmlspecialchars($row['room_name']); ?></span>
                                            <?php echo $row['is_makeup'] ? 'Makeup Class' : htmlspecialchars($row['day_of_week']); ?> | <?php echo formatTime($row['start_time']); ?>
                                        </div>
                                        <i class="fa-solid fa-circle-user sp-subject-avatar text-secondary"></i>
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

<div class="row g-3 mt-1">
    <div class="col-lg-12">
        <div class="card sp-risk-card">
            <div class="sp-risk-head">
                <div class="sp-risk-title">
                    <span class="sp-risk-title__icon"><i class="fa-solid fa-clipboard-list"></i></span>
                    <div>
                        <h5 class="sp-risk-title__text sp-script-title">At-Risk Students</h5>
                        <div class="sp-risk-title__sub">Most late/absent in your classes</div>
                    </div>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <span class="sp-risk-total"><i class="fa-solid fa-user-clock"></i> Total: <?php echo (int) $atRiskResult->num_rows; ?></span>
                    <?php if ($atRiskResult->num_rows > 0): ?>
                        <form method="post" class="d-inline-block">
                            <input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>">
                            <input type="hidden" name="action" value="export_at_risk_pdf">
                            <button type="submit" class="btn btn-sm sp-export-btn" title="Export as PDF"><i class="fa-solid fa-file-pdf"></i></button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
            <?php if ($atRiskResult->num_rows === 0): ?>
                <p class="text-muted small mb-0">No at-risk students right now.</p>
            <?php else: ?>
                <div class="sp-risk-table-wrap table-responsive">
                    <table class="sp-risk-table">
                        <thead>
                            <tr>
                                <th>Student</th>
                                <th>Room</th>
                                <th class="text-center">Absent</th>
                                <th class="text-center">Late</th>
                                <th class="text-center">Records</th>
                                <th class="text-end">Attendance</th>
                                <th class="text-center">Email</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($row = $atRiskResult->fetch_assoc()): ?>
                                <?php $riskRate = $row['total'] ? round(($row['total'] - $row['absents']) / $row['total'] * 100) : 0; ?>
                                <?php $recordsUrl = 'attendance.php?student=' . (int) $row['id']; ?>
                                <?php $riskEmail = $row['email'] ?: $row['guardian_email']; ?>
                                <tr class="sp-risk-row" data-href="<?php echo $recordsUrl; ?>" tabindex="0" role="link" title="View attendance records">
                                    <td>
                                        <a href="<?php echo $recordsUrl; ?>" class="sp-risk-name"><?php echo htmlspecialchars(trim($row['first_name'] . ' ' . $row['last_name'])); ?></a>
                                        <div class="sp-risk-meta"><?php echo htmlspecialchars($row['student_id'] ?: '-'); ?></div>
                                    </td>
                                    <td>
                                        <?php if ($row['room_name']): ?>
                                            <span class="sp-risk-pill sp-risk-pill--room"><i class="fa-solid fa-door-open"></i> <?php echo htmlspecialchars($row['room_name']); ?></span>
                                        <?php else: ?>
                                            <span class="text-muted">&mdash;</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center"><span class="sp-risk-pill sp-risk-pill--absent"><?php echo (int) $row['absents']; ?></span></td>
                                    <td class="text-center"><span class="sp-risk-pill sp-risk-pill--late"><?php echo (int) $row['lates']; ?></span></td>
                                    <td class="text-center"><span class="sp-risk-pill sp-risk-pill--neutral"><?php echo (int) $row['total']; ?></span></td>
                                    <td class="text-end sp-risk-rate"><?php echo $riskRate; ?>%</td>
                                    <td class="text-center">
                                        <?php if ($riskEmail): ?>
                                            <button type="button" class="btn btn-sm sp-export-btn sp-email-trigger"
                                                data-student-id="<?php echo (int) $row['id']; ?>"
                                                data-student-name="<?php echo htmlspecialchars(trim($row['first_name'] . ' ' . $row['last_name'])); ?>"
                                                data-email="<?php echo htmlspecialchars($riskEmail); ?>"
                                                data-absents="<?php echo (int) $row['absents']; ?>"
                                                data-lates="<?php echo (int) $row['lates']; ?>"
                                                title="Compose email to <?php echo htmlspecialchars($riskEmail); ?>">
                                                <i class="fa-solid fa-envelope"></i>
                                            </button>
                                        <?php else: ?>
                                            <span class="text-muted" title="No email on file">&mdash;</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<h4 class="mt-4 mb-3 sp-script-title">Analytics</h4>
<div class="row g-3">
    <div class="col-lg-6">
        <div class="card p-3 h-100">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h6 class="mb-0">Attendance Rate Trend <span class="text-muted small fw-normal">(last 8 weeks)</span></h6>
                <div class="d-flex align-items-center gap-2">
                    <?php if ($trendData): ?>
                        <button type="button" class="btn btn-sm sp-export-btn" id="spExportTrendPdf" title="Export as PDF"><i class="fa-solid fa-file-pdf"></i></button>
                    <?php endif; ?>
                    <button type="button" class="btn btn-sm sp-export-btn sp-expand-btn" title="Expand"><i class="fa-solid fa-expand"></i></button>
                </div>
            </div>
            <?php if ($trendData): ?>
                <div class="sp-chart-box" style="height:280px;"><canvas id="trendChart"></canvas></div>
                <?php echo renderChartInsights(insightsForTrend($trendLabels, $trendData)); ?>
            <?php else: ?>
                <p class="text-muted small mb-0">Not enough data yet.</p>
            <?php endif; ?>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card p-3 h-100">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h6 class="mb-0">Peak Absence Days</h6>
                <div class="d-flex align-items-center gap-2">
                    <?php if ($dowData): ?>
                        <button type="button" class="btn btn-sm sp-export-btn" id="spExportDowPdf" title="Export as PDF"><i class="fa-solid fa-file-pdf"></i></button>
                    <?php endif; ?>
                    <button type="button" class="btn btn-sm sp-export-btn sp-expand-btn" title="Expand"><i class="fa-solid fa-expand"></i></button>
                </div>
            </div>
            <?php if ($dowData): ?>
                <div class="sp-chart-box" style="height:280px;"><canvas id="dowChart"></canvas></div>
                <?php echo renderChartInsights(insightsForPeak($dowLabels, $dowData, 'peak absence day', 'absences')); ?>
            <?php else: ?>
                <p class="text-muted small mb-0">Not enough data yet.</p>
            <?php endif; ?>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card p-3 h-100">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h6 class="mb-0">Attendance Rate by Class</h6>
                <div class="d-flex align-items-center gap-2">
                    <?php if ($classData): ?>
                        <button type="button" class="btn btn-sm sp-export-btn" id="spExportClassPdf" title="Export as PDF"><i class="fa-solid fa-file-pdf"></i></button>
                    <?php endif; ?>
                    <button type="button" class="btn btn-sm sp-export-btn sp-expand-btn" title="Expand"><i class="fa-solid fa-expand"></i></button>
                </div>
            </div>
            <?php if ($classData): ?>
                <div class="sp-chart-box" style="height:280px;"><canvas id="classChart"></canvas></div>
                <?php echo renderChartInsights(insightsForRateRanking($classLabels, $classData, 'class')); ?>
            <?php else: ?>
                <p class="text-muted small mb-0">Not enough data yet.</p>
            <?php endif; ?>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card p-3 h-100">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h6 class="mb-0">Peak Scan Times</h6>
                <div class="d-flex align-items-center gap-2">
                    <?php if ($hourData): ?>
                        <button type="button" class="btn btn-sm sp-export-btn" id="spExportHourPdf" title="Export as PDF"><i class="fa-solid fa-file-pdf"></i></button>
                    <?php endif; ?>
                    <button type="button" class="btn btn-sm sp-export-btn sp-expand-btn" title="Expand"><i class="fa-solid fa-expand"></i></button>
                </div>
            </div>
            <?php if ($hourData): ?>
                <div class="sp-chart-box" style="height:280px;"><canvas id="hourChart"></canvas></div>
                <?php echo renderChartInsights(insightsForPeak($hourLabels, $hourData, 'busiest scan hour', 'scans')); ?>
            <?php else: ?>
                <p class="text-muted small mb-0">Not enough data yet.</p>
            <?php endif; ?>
        </div>
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.0/chart.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script>
// At-risk rows open that student's attendance records
document.querySelectorAll('.sp-risk-row').forEach(function (row) {
    row.addEventListener('click', function (e) {
        if (e.target.closest('a, button')) return;
        window.location.href = row.dataset.href;
    });
    row.addEventListener('keydown', function (e) {
        if (e.key === 'Enter') window.location.href = row.dataset.href;
    });
});
</script>
<script>
// Expand/collapse a dashboard card into a full-screen view
(function () {
    var backdrop = document.createElement('div');
    backdrop.className = 'sp-card-backdrop';
    document.body.appendChild(backdrop);

    function setExpanded(card, expanded) {
        var btn = card.querySelector('.sp-expand-btn');
        card.classList.toggle('sp-card-expanded', expanded);
        backdrop.classList.toggle('show', expanded);
        document.body.classList.toggle('overflow-hidden', expanded);
        if (btn) {
            btn.title = expanded ? 'Collapse' : 'Expand';
            btn.querySelector('i').className = 'fa-solid ' + (expanded ? 'fa-compress' : 'fa-expand');
        }
    }

    function collapseAll() {
        document.querySelectorAll('.sp-card-expanded').forEach(function (card) { setExpanded(card, false); });
    }

    document.querySelectorAll('.sp-expand-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var card = btn.closest('.card');
            var expanded = card.classList.contains('sp-card-expanded');
            collapseAll();
            if (!expanded) setExpanded(card, true);
        });
    });
    backdrop.addEventListener('click', collapseAll);
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') collapseAll(); });
})();
</script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    function greenShade(t) {
        var light = [199, 230, 209];
        var dark = [20, 83, 45];
        t = Math.max(0, Math.min(1, t));
        var r = Math.round(light[0] + (dark[0] - light[0]) * t);
        var g = Math.round(light[1] + (dark[1] - light[1]) * t);
        var b = Math.round(light[2] + (dark[2] - light[2]) * t);
        return 'rgb(' + r + ',' + g + ',' + b + ')';
    }
    function greenShadesForPercent(data) {
        return data.map(function (v) { return greenShade(v / 100); });
    }
    function greenShadesForCount(data) {
        var max = Math.max.apply(null, data.concat([1]));
        return data.map(function (v) { return greenShade(v / max); });
    }
    Chart.defaults.font.family = "'Inter', 'Segoe UI', sans-serif";

    function spExportChartPdf(chartInstance, title) {
        if (!chartInstance || typeof window.jspdf === 'undefined') return;
        var jsPDF = window.jspdf.jsPDF;
        var imgData = chartInstance.toBase64Image('image/png', 1);
        var doc = new jsPDF({ orientation: 'landscape', unit: 'pt', format: 'a4' });
        var pageWidth = doc.internal.pageSize.getWidth();
        var pageHeight = doc.internal.pageSize.getHeight();

        doc.setFontSize(16);
        doc.setTextColor(20, 83, 45);
        doc.text('TimeTrack — ' + title, 40, 40);
        doc.setFontSize(10);
        doc.setTextColor(120, 120, 120);
        doc.text('<?php echo htmlspecialchars(addslashes($schoolName)); ?> — Generated ' + new Date().toLocaleString('en-US', { timeZone: 'Asia/Manila' }), 40, 58);

        var imgWidth = pageWidth - 80;
        var imgHeight = chartInstance.height * (imgWidth / chartInstance.width);
        if (imgHeight > pageHeight - 100) {
            imgHeight = pageHeight - 100;
            imgWidth = chartInstance.width * (imgHeight / chartInstance.height);
        }
        doc.addImage(imgData, 'PNG', 40, 80, imgWidth, imgHeight);
        doc.save(title.replace(/\s+/g, '_').toLowerCase() + '.pdf');
    }

    function spWireExportBtn(btnId, chartInstance, title) {
        var btn = document.getElementById(btnId);
        if (btn) {
            btn.addEventListener('click', function () {
                spExportChartPdf(chartInstance, title);
            });
        }
    }

    function spAxisTitle(text) {
        return { display: true, text: text, color: '#4b5563', font: { size: 12, weight: '600' }, padding: 12 };
    }

    <?php if ($trendData): ?>
    var trendChart = new Chart(document.getElementById('trendChart'), {
        type: 'line',
        data: {
            labels: <?php echo json_encode($trendLabels); ?>,
            datasets: [{
                label: 'Attendance Rate',
                data: <?php echo json_encode($trendData); ?>,
                borderColor: '#2f7d4f',
                backgroundColor: 'rgba(47,125,79,0.12)',
                fill: true,
                tension: 0.35,
                pointBackgroundColor: '#2f7d4f',
                pointRadius: 4
            }]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            layout: { padding: { top: 20, right: 10, left: 4, bottom: 4 } },
            clip: false,
            plugins: { legend: { display: false } },
            scales: {
                x: { title: spAxisTitle('Week') },
                y: { min: 0, max: 100, ticks: { callback: function (v) { return v + '%'; } }, title: spAxisTitle('Rate (%)') }
            }
        }
    });
    spWireExportBtn('spExportTrendPdf', trendChart, 'Attendance Rate Trend');
    <?php endif; ?>

    <?php if ($dowData): ?>
    var dowChart = new Chart(document.getElementById('dowChart'), {
        type: 'bar',
        data: {
            labels: <?php echo json_encode($dowLabels); ?>,
            datasets: [{ label: 'Absences', data: <?php echo json_encode($dowData); ?>, backgroundColor: greenShadesForCount(<?php echo json_encode($dowData); ?>), borderRadius: 6, maxBarThickness: 40 }]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                x: { title: spAxisTitle('Day of Week') },
                y: { beginAtZero: true, ticks: { precision: 0 }, title: spAxisTitle('Absences') }
            }
        }
    });
    spWireExportBtn('spExportDowPdf', dowChart, 'Peak Absence Days');
    <?php endif; ?>

    <?php if ($classData): ?>
    var classChart = new Chart(document.getElementById('classChart'), {
        type: 'bar',
        data: {
            labels: <?php echo json_encode($classLabels); ?>,
            datasets: [{ label: 'Attendance Rate', data: <?php echo json_encode($classData); ?>, backgroundColor: greenShadesForPercent(<?php echo json_encode($classData); ?>), borderRadius: 6, maxBarThickness: 40 }]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                x: { title: spAxisTitle('Class') },
                y: { min: 0, max: 100, ticks: { callback: function (v) { return v + '%'; } }, title: spAxisTitle('Rate (%)') }
            }
        }
    });
    spWireExportBtn('spExportClassPdf', classChart, 'Attendance Rate by Class');
    <?php endif; ?>

    <?php if ($hourData): ?>
    var hourChart = new Chart(document.getElementById('hourChart'), {
        type: 'bar',
        data: {
            labels: <?php echo json_encode($hourLabels); ?>,
            datasets: [{ label: 'Scans', data: <?php echo json_encode($hourData); ?>, backgroundColor: greenShadesForCount(<?php echo json_encode($hourData); ?>), borderRadius: 6, maxBarThickness: 40 }]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                x: { title: spAxisTitle('Time of Day') },
                y: { beginAtZero: true, ticks: { precision: 0 }, title: spAxisTitle('Scans') }
            }
        }
    });
    spWireExportBtn('spExportHourPdf', hourChart, 'Peak Scan Times');
    <?php endif; ?>
});
</script>
<div class="modal fade" id="myClassesModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content rounded-4">
            <div class="modal-header">
                <h5 class="modal-title">My Classes (<?php echo $mySubjectsCount; ?>)</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <ul class="list-group list-group-flush">
                    <?php if (empty($myClassesList)): ?>
                        <li class="list-group-item text-muted small">No classes assigned yet.</li>
                    <?php else: ?>
                        <?php foreach ($myClassesList as $row): ?>
                            <li class="list-group-item d-flex justify-content-between align-items-center gap-3">
                                <span>
                                    <strong><?php echo htmlspecialchars($row['code']); ?></strong> <span class="text-muted small"><?php echo htmlspecialchars($row['name']); ?></span>
                                    <span class="d-block text-muted small"><?php echo htmlspecialchars($row['room_name'] ?: '-'); ?></span>
                                </span>
                                <span class="text-muted small text-end"><?php echo htmlspecialchars($row['day_of_week']); ?> | <?php echo formatTime($row['start_time']); ?> - <?php echo formatTime($row['end_time']); ?></span>
                            </li>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="myStudentsModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content rounded-4">
            <div class="modal-header">
                <h5 class="modal-title">My Students (<?php echo $myStudentsCount; ?>)</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <ul class="list-group list-group-flush">
                    <?php if (empty($myStudentsList)): ?>
                        <li class="list-group-item text-muted small">No students enrolled yet.</li>
                    <?php else: ?>
                        <?php foreach ($myStudentsList as $row): ?>
                            <li class="list-group-item d-flex justify-content-between align-items-center gap-3">
                                <span>
                                    <?php echo htmlspecialchars($row['first_name'] . ' ' . $row['last_name']); ?>
                                    <span class="d-block text-muted small"><?php echo htmlspecialchars($row['student_id']); ?></span>
                                </span>
                                <span class="text-muted small text-end"><?php echo htmlspecialchars($row['classes']); ?></span>
                            </li>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="presentTodayModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content rounded-4">
            <div class="modal-header">
                <h5 class="modal-title">Present Today (<?php echo $totalCounts['present']; ?>)</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <ul class="list-group list-group-flush">
                    <?php if (empty($todayStatusLists['present'])): ?>
                        <li class="list-group-item text-muted small">No students marked present yet today.</li>
                    <?php else: ?>
                        <?php foreach ($todayStatusLists['present'] as $row): ?>
                            <li class="list-group-item d-flex justify-content-between align-items-center gap-3">
                                <span><?php echo htmlspecialchars($row['name']); ?> <span class="text-muted small"><?php echo htmlspecialchars($row['class']); ?></span></span>
                                <span class="badge bg-success"><?php echo $row['time'] ? formatTime($row['time']) : 'Present'; ?></span>
                            </li>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="lateAbsentTodayModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content rounded-4">
            <div class="modal-header">
                <h5 class="modal-title">Late / Absent Today (<?php echo $totalCounts['late']; ?> / <?php echo $totalCounts['absent']; ?>)</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <h6 class="text-uppercase small fw-bold text-warning mb-2">Late (<?php echo $totalCounts['late']; ?>)</h6>
                <ul class="list-group list-group-flush mb-4">
                    <?php if (empty($todayStatusLists['late'])): ?>
                        <li class="list-group-item text-muted small">No late students today.</li>
                    <?php else: ?>
                        <?php foreach ($todayStatusLists['late'] as $row): ?>
                            <li class="list-group-item d-flex justify-content-between align-items-center gap-3">
                                <span><?php echo htmlspecialchars($row['name']); ?> <span class="text-muted small"><?php echo htmlspecialchars($row['class']); ?></span></span>
                                <span class="badge bg-warning text-dark"><?php echo $row['time'] ? formatTime($row['time']) : 'Late'; ?></span>
                            </li>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </ul>
                <h6 class="text-uppercase small fw-bold text-danger mb-2">Absent (<?php echo $totalCounts['absent']; ?>)</h6>
                <ul class="list-group list-group-flush">
                    <?php if (empty($todayStatusLists['absent'])): ?>
                        <li class="list-group-item text-muted small">No absent students today.</li>
                    <?php else: ?>
                        <?php foreach ($todayStatusLists['absent'] as $row): ?>
                            <li class="list-group-item d-flex justify-content-between align-items-center gap-3">
                                <span><?php echo htmlspecialchars($row['name']); ?> <span class="text-muted small"><?php echo htmlspecialchars($row['class']); ?></span></span>
                                <span class="badge bg-danger"><?php echo $row['time'] ? formatTime($row['time']) : 'Absent'; ?></span>
                            </li>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="spEmailComposeModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-4">
            <form method="post" id="spEmailComposeForm">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fa-solid fa-envelope me-2"></i>Compose Email</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>">
                    <input type="hidden" name="action" value="send_at_risk_email">
                    <input type="hidden" name="student_id" id="spEmailStudentId">
                    <div class="mb-3">
                        <label class="form-label">To</label>
                        <input type="text" class="form-control" id="spEmailTo" disabled>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Subject</label>
                        <input type="text" class="form-control" name="subject" id="spEmailSubject" maxlength="150" required>
                    </div>
                    <div class="mb-0">
                        <label class="form-label">Message</label>
                        <textarea class="form-control" name="message" id="spEmailMessage" rows="6" maxlength="3000" required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal" id="spEmailCancelBtn">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="spEmailSendBtn"><i class="fa-solid fa-paper-plane me-1"></i>Send</button>
                </div>
            </form>
        </div>
    </div>
</div>
<script>
// Show a "Sending..." loading state while the email is being sent
(function () {
    var form = document.getElementById('spEmailComposeForm');
    var sendBtn = document.getElementById('spEmailSendBtn');
    var cancelBtn = document.getElementById('spEmailCancelBtn');
    var idleHtml = sendBtn.innerHTML;

    function setSending(sending) {
        sendBtn.disabled = sending;
        cancelBtn.disabled = sending;
        sendBtn.innerHTML = sending
            ? '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Sending...'
            : idleHtml;
    }

    form.addEventListener('submit', function (e) {
        if (sendBtn.disabled) { e.preventDefault(); return; }
        setSending(true);
    });
    // Reset if the page is restored from the back/forward cache
    window.addEventListener('pageshow', function () { setSending(false); });
})();
</script>
<script>
document.querySelectorAll('.sp-email-trigger').forEach(function (btn) {
    btn.addEventListener('click', function () {
        var name = btn.dataset.studentName;
        var email = btn.dataset.email;
        var absents = btn.dataset.absents;
        var lates = btn.dataset.lates;
        document.getElementById('spEmailStudentId').value = btn.dataset.studentId;
        document.getElementById('spEmailTo').value = name + ' <' + email + '>';
        document.getElementById('spEmailSubject').value = 'Attendance Reminder for ' + name;
        document.getElementById('spEmailMessage').value =
            'Hi ' + name + ',\n\n' +
            'This is a reminder regarding your attendance record: ' + absents + ' absence(s) and ' + lates + ' late scan(s).\n\n' +
            'Please make it a habit to attend classes on time. Reach out to me if you have any concerns.';
        var modal = new bootstrap.Modal(document.getElementById('spEmailComposeModal'));
        modal.show();
    });
});
</script>
<?php require_once __DIR__ . '/../includes/teacher_footer.php'; ?>
