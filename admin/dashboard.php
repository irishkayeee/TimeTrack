<?php
require_once __DIR__ . '/../includes/functions.php';
requireRole(['admin', 'superadmin']);
$pageTitle = 'Dashboard';
$pageSubtitle = 'Overview of attendance across the school.';

$hour = (int) date('G');
$greeting = $hour < 12 ? 'Good morning' : ($hour < 18 ? 'Good afternoon' : 'Good evening');
$semester = getSetting('semester', '1st Semester');
$schoolYear = getSetting('school_year', date('Y') . '-' . (date('Y') + 1));

$studentsCount = $mysqli->query('SELECT COUNT(*) FROM students')->fetch_row()[0];
$teachersCount = $mysqli->query('SELECT COUNT(*) FROM teachers')->fetch_row()[0];
$coursesCount = $mysqli->query('SELECT COUNT(*) FROM courses')->fetch_row()[0];
$roomsCount = $mysqli->query('SELECT COUNT(*) FROM rooms')->fetch_row()[0];
$attendanceToday = $mysqli->query("SELECT COUNT(*) FROM attendance WHERE date = CURDATE()")->fetch_row()[0];

$studentsList = $mysqli->query('SELECT s.student_id, s.first_name, s.last_name, c.code AS course_code, sec.room_name FROM students s LEFT JOIN courses c ON s.course_id = c.id LEFT JOIN rooms sec ON s.room_id = sec.id ORDER BY s.first_name');
$teachersList = $mysqli->query('SELECT teacher_id, first_name, last_name, subject FROM teachers ORDER BY first_name');
$coursesListDash = $mysqli->query('SELECT code, name FROM courses ORDER BY code');
$roomsListDash = $mysqli->query('SELECT year_level, room_name FROM rooms ORDER BY year_level, room_name');
$attendanceTodayList = $mysqli->query("SELECT a.status, a.time, s.first_name, s.last_name FROM attendance a LEFT JOIN students s ON a.student_id = s.id WHERE a.date = CURDATE() ORDER BY a.time DESC");

// ---- Analytics ----
$courseFilterId = intval($_GET['course_id'] ?? $_POST['course_id'] ?? 0);
$courseFilterOptions = $mysqli->query('SELECT id, code FROM courses ORDER BY code')->fetch_all(MYSQLI_ASSOC);

function sanitizeDateParam($value) {
    if (!$value) {
        return null;
    }
    $d = DateTime::createFromFormat('Y-m-d', $value);
    return ($d && $d->format('Y-m-d') === $value) ? $value : null;
}

function resolveAnalyticsDateRange($range, $customFrom, $customTo, $schoolYearSetting) {
    switch ($range) {
        case 'this_week':
            return [date('Y-m-d', strtotime('monday this week')), date('Y-m-d', strtotime('sunday this week'))];
        case 'this_month':
            return [date('Y-m-01'), date('Y-m-t')];
        case 'last_month':
            return [date('Y-m-01', strtotime('first day of last month')), date('Y-m-t', strtotime('last day of last month'))];
        case 'this_year':
            return [date('Y-01-01'), date('Y-12-31')];
        case 'school_year':
            if (preg_match('/(\d{4}).*?(\d{4})/', $schoolYearSetting, $m)) {
                return [$m[1] . '-08-01', $m[2] . '-07-31'];
            }
            return [null, null];
        case 'custom':
            return [sanitizeDateParam($customFrom), sanitizeDateParam($customTo)];
        default:
            return [null, null];
    }
}

// Builds a " WHERE ..." (or " AND ...") clause combining the Program and Range
// filters for one query. $dateCol is the attendance date column as referenced
// in that query (e.g. 'date' or 'a.date'); pass null to skip the date filter.
function analyticsFilterClause($courseCol, $courseFilterId, $dateCol, $rangeFrom, $rangeTo, $mysqli, $prefix = 'WHERE') {
    $clauses = [];
    if ($courseFilterId) {
        $clauses[] = "$courseCol = $courseFilterId";
    }
    if ($dateCol !== null) {
        if ($rangeFrom) {
            $clauses[] = "$dateCol >= '" . $mysqli->real_escape_string($rangeFrom) . "'";
        }
        if ($rangeTo) {
            $clauses[] = "$dateCol <= '" . $mysqli->real_escape_string($rangeTo) . "'";
        }
    }
    return $clauses ? " $prefix " . implode(' AND ', $clauses) : '';
}

$rangeFilter = $_GET['range'] ?? $_POST['range'] ?? '';
$customFromInput = $_GET['date_from'] ?? $_POST['date_from'] ?? '';
$customToInput = $_GET['date_to'] ?? $_POST['date_to'] ?? '';
list($rangeFrom, $rangeTo) = resolveAnalyticsDateRange($rangeFilter, $customFromInput, $customToInput, $schoolYear);
$hasDateFilter = $rangeFrom !== null || $rangeTo !== null;

$guardianCoverageSql = "SELECT COUNT(*) AS total, SUM(guardian_email IS NOT NULL AND guardian_email != '') AS with_email FROM students" . ($courseFilterId ? " WHERE course_id = $courseFilterId" : '');
$guardianCoverage = $mysqli->query($guardianCoverageSql)->fetch_assoc();
$guardianCoveragePct = $guardianCoverage['total'] ? round($guardianCoverage['with_email'] / $guardianCoverage['total'] * 100) : 0;

$trendLabels = [];
$trendData = [];
if ($hasDateFilter) {
    $trendDateClause = analyticsFilterClause('course_id', $courseFilterId, 'date', $rangeFrom, $rangeTo, $mysqli);
} else {
    $trendClauses = ['date >= DATE_SUB(CURDATE(), INTERVAL 8 WEEK)'];
    if ($courseFilterId) {
        $trendClauses[] = "course_id = $courseFilterId";
    }
    $trendDateClause = ' WHERE ' . implode(' AND ', $trendClauses);
}
$trendSql = "SELECT YEARWEEK(date, 1) AS yw, MIN(date) AS week_start, SUM(status IN ('present','late')) AS attended, COUNT(*) AS total FROM attendance $trendDateClause GROUP BY yw ORDER BY yw";
$trendResult = $mysqli->query($trendSql);
while ($row = $trendResult->fetch_assoc()) {
    $trendLabels[] = date('M j', strtotime($row['week_start']));
    $trendData[] = $row['total'] ? round($row['attended'] / $row['total'] * 100) : 0;
}

$roomLabels = [];
$roomData = [];
$roomRateSql = "SELECT sec.room_name, sec.year_level, SUM(a.status IN ('present','late')) AS attended, COUNT(a.id) AS total FROM attendance a JOIN rooms sec ON a.room_id = sec.id" . analyticsFilterClause('a.course_id', $courseFilterId, 'a.date', $rangeFrom, $rangeTo, $mysqli) . ' GROUP BY sec.id ORDER BY sec.room_name';
$roomRateResult = $mysqli->query($roomRateSql);
while ($row = $roomRateResult->fetch_assoc()) {
    $roomLabels[] = $row['room_name'];
    $roomData[] = $row['total'] ? round($row['attended'] / $row['total'] * 100) : 0;
}

$courseLabels = [];
$courseData = [];
$courseRateSql = "SELECT c.code, SUM(a.status IN ('present','late')) AS attended, COUNT(a.id) AS total FROM attendance a JOIN courses c ON a.course_id = c.id" . analyticsFilterClause('a.course_id', $courseFilterId, 'a.date', $rangeFrom, $rangeTo, $mysqli) . ' GROUP BY c.id ORDER BY c.code';
$courseRateResult = $mysqli->query($courseRateSql);
while ($row = $courseRateResult->fetch_assoc()) {
    $courseLabels[] = $row['code'];
    $courseData[] = $row['total'] ? round($row['attended'] / $row['total'] * 100) : 0;
}

$dowLabels = [];
$dowData = [];
$dowSql = "SELECT DAYOFWEEK(date) AS dnum, DAYNAME(date) AS dname, SUM(status = 'absent') AS absents FROM attendance" . analyticsFilterClause('course_id', $courseFilterId, 'date', $rangeFrom, $rangeTo, $mysqli) . ' GROUP BY dnum, dname ORDER BY dnum';
$dowResult = $mysqli->query($dowSql);
while ($row = $dowResult->fetch_assoc()) {
    $dowLabels[] = $row['dname'];
    $dowData[] = (int) $row['absents'];
}

$teacherLabels = [];
$teacherData = [];
$teacherRateSql = "SELECT t.first_name, t.last_name, SUM(a.status IN ('present','late')) AS attended, COUNT(a.id) AS total FROM attendance a JOIN subjects sub ON a.subject_id = sub.id JOIN teachers t ON sub.teacher_id = t.id" . analyticsFilterClause('a.course_id', $courseFilterId, 'a.date', $rangeFrom, $rangeTo, $mysqli) . ' GROUP BY t.id ORDER BY t.first_name';
$teacherRateResult = $mysqli->query($teacherRateSql);
while ($row = $teacherRateResult->fetch_assoc()) {
    $teacherLabels[] = $row['first_name'] . ' ' . substr($row['last_name'], 0, 1) . '.';
    $teacherData[] = $row['total'] ? round($row['attended'] / $row['total'] * 100) : 0;
}

$hourLabels = [];
$hourData = [];
$hourSql = 'SELECT HOUR(time) AS hr, COUNT(*) AS cnt FROM attendance' . analyticsFilterClause('course_id', $courseFilterId, 'date', $rangeFrom, $rangeTo, $mysqli) . ' GROUP BY hr ORDER BY hr';
$hourResult = $mysqli->query($hourSql);
while ($row = $hourResult->fetch_assoc()) {
    $hourLabels[] = date('g A', strtotime($row['hr'] . ':00'));
    $hourData[] = (int) $row['cnt'];
}

$atRiskSql = "SELECT s.id, s.first_name, s.last_name, s.student_id, s.email, s.guardian_name, s.guardian_email, c.code AS course_code, sec.room_name, SUM(a.status = 'absent') AS absents, SUM(a.status = 'late') AS lates, COUNT(a.id) AS total FROM attendance a JOIN students s ON a.student_id = s.id LEFT JOIN courses c ON s.course_id = c.id LEFT JOIN rooms sec ON s.room_id = sec.id" . analyticsFilterClause('a.course_id', $courseFilterId, 'a.date', $rangeFrom, $rangeTo, $mysqli) . " GROUP BY s.id HAVING (SUM(a.status = 'absent') + SUM(a.status = 'late')) > 0 ORDER BY (SUM(a.status = 'absent') * 2 + SUM(a.status = 'late')) DESC LIMIT 10";
$atRiskResult = $mysqli->query($atRiskSql);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'send_at_risk_email') {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        flash('Invalid request.', 'danger');
        redirect('dashboard.php');
    }
    $studentId = (int) ($_POST['student_id'] ?? 0);
    $emailSubject = trim($_POST['subject'] ?? '');
    $emailMessage = trim($_POST['message'] ?? '');
    $checkStmt = $mysqli->prepare('SELECT id, first_name, last_name, email, guardian_name, guardian_email FROM students WHERE id = ?');
    $checkStmt->bind_param('i', $studentId);
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
        $rows[] = [$row['student_id'], $row['first_name'] . ' ' . $row['last_name'], trim(($row['course_code'] ?: '') . ' ' . ($row['room_name'] ?: '')), (int) $row['absents'], (int) $row['lates']];
    }
    $pdf = generateSimpleTablePdf('At-Risk Students', ['ID', 'Name', 'Course/Room', 'Absent', 'Late'], $rows, [80, 200, 160, 70, 70]);
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="at_risk_students_' . date('Ymd') . '.pdf"');
    header('Content-Length: ' . strlen($pdf));
    echo $pdf;
    exit;
}

require_once __DIR__ . '/../includes/admin_header.php';
?>
<div class="card p-4 mb-3 sp-greeting-card">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
        <div>
            <h4 class="mb-1"><span id="spGreetingWord"><?php echo htmlspecialchars($greeting); ?></span>, <?php echo htmlspecialchars($adminUser['role'] ?? 'admin'); ?>! <span class="sp-greeting-wave">👋</span></h4>
            <p class="text-muted mb-2">Here's what's happening across the school today.</p>
            <span class="sp-shd-pill"><i class="fa-solid fa-calendar"></i> <?php echo htmlspecialchars($semester . ', AY ' . $schoolYear); ?></span>
        </div>
        <div class="sp-clock-widget text-center">
            <div class="sp-clock-time" id="spClockTime">--:--:-- --</div>
            <div class="sp-clock-date" id="spClockDate">Loading...</div>
        </div>
    </div>
</div>
<script>
    function spUpdateClock() {
        var now = new Date();
        var timeEl = document.getElementById('spClockTime');
        var dateEl = document.getElementById('spClockDate');
        if (timeEl) {
            timeEl.textContent = now.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true, timeZone: 'Asia/Manila' });
        }
        if (dateEl) {
            dateEl.textContent = now.toLocaleDateString('en-US', { weekday: 'long', month: 'long', day: 'numeric', year: 'numeric', timeZone: 'Asia/Manila' });
        }
        var greetingEl = document.getElementById('spGreetingWord');
        if (greetingEl) {
            var h = now.getHours();
            greetingEl.textContent = h < 12 ? 'Good morning' : (h < 18 ? 'Good afternoon' : 'Good evening');
        }
    }
    spUpdateClock();
    setInterval(spUpdateClock, 1000);
</script>
<?php $attendanceTodayPct = $studentsCount ? min(100, round($attendanceToday / $studentsCount * 100)) : 0; ?>
<div class="row g-3">
    <div class="col-6 col-lg-3">
        <div class="stat-card-v2 stat-card-v2--green" data-bs-toggle="modal" data-bs-target="#studentsListModal">
            <div class="stat-card-v2__top">
                <span class="stat-card-v2__icon"><i class="fa-solid fa-user-graduate"></i></span>
                <span class="stat-card-v2__label">Students</span>
                <i class="fa-solid fa-chevron-right stat-card-v2__chevron"></i>
            </div>
            <div class="stat-card-v2__value"><?php echo $studentsCount; ?></div>
            <div class="stat-card-v2__sublabel">Total enrolled</div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="stat-card-v2 stat-card-v2--blue" data-bs-toggle="modal" data-bs-target="#teachersListModal">
            <div class="stat-card-v2__top">
                <span class="stat-card-v2__icon"><i class="fa-solid fa-chalkboard-user"></i></span>
                <span class="stat-card-v2__label">Teachers</span>
                <i class="fa-solid fa-chevron-right stat-card-v2__chevron"></i>
            </div>
            <div class="stat-card-v2__value"><?php echo $teachersCount; ?></div>
            <div class="stat-card-v2__sublabel">Total active</div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="stat-card-v2 stat-card-v2--purple" data-bs-toggle="modal" data-bs-target="#coursesListModal">
            <div class="stat-card-v2__top">
                <span class="stat-card-v2__icon"><i class="fa-solid fa-graduation-cap"></i></span>
                <span class="stat-card-v2__label">Courses</span>
                <i class="fa-solid fa-chevron-right stat-card-v2__chevron"></i>
            </div>
            <div class="stat-card-v2__value"><?php echo $coursesCount; ?></div>
            <div class="stat-card-v2__sublabel">Offered this term</div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="stat-card-v2 stat-card-v2--orange" data-bs-toggle="modal" data-bs-target="#roomsListModal">
            <div class="stat-card-v2__top">
                <span class="stat-card-v2__icon"><i class="fa-solid fa-people-group"></i></span>
                <span class="stat-card-v2__label">Rooms</span>
                <i class="fa-solid fa-chevron-right stat-card-v2__chevron"></i>
            </div>
            <div class="stat-card-v2__value"><?php echo $roomsCount; ?></div>
            <div class="stat-card-v2__sublabel">Available</div>
        </div>
    </div>
</div>

<div class="row g-3 mt-1">
    <div class="col-md-6">
        <div class="stat-card-v2 stat-card-v2--teal" data-bs-toggle="modal" data-bs-target="#attendanceTodayListModal">
            <div class="stat-card-v2__top">
                <span class="stat-card-v2__icon"><i class="fa-solid fa-clipboard-check"></i></span>
                <span class="stat-card-v2__label">Attendance Today</span>
                <i class="fa-solid fa-chevron-right stat-card-v2__chevron"></i>
            </div>
            <div class="stat-card-v2__value"><?php echo $attendanceToday; ?></div>
            <div class="stat-card-v2__progress"><div class="stat-card-v2__progress-bar" style="width: <?php echo $attendanceTodayPct; ?>%"></div></div>
            <div class="stat-card-v2__sublabel"><?php echo $attendanceTodayPct; ?>% of students</div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="stat-card-v2 stat-card-v2--rose">
            <div class="stat-card-v2__top">
                <span class="stat-card-v2__icon"><i class="fa-solid fa-envelope-circle-check"></i></span>
                <span class="stat-card-v2__label">Guardian Email Coverage</span>
            </div>
            <div class="stat-card-v2__value"><?php echo $guardianCoveragePct; ?>%</div>
            <div class="stat-card-v2__progress"><div class="stat-card-v2__progress-bar" style="width: <?php echo $guardianCoveragePct; ?>%"></div></div>
            <div class="stat-card-v2__sublabel"><?php echo (int) $guardianCoverage['with_email']; ?> of <?php echo (int) $guardianCoverage['total']; ?> students</div>
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
                        <div class="sp-risk-title__sub">Most late/absent across all classes</div>
                    </div>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <span class="sp-risk-total"><i class="fa-solid fa-user-clock"></i> Total: <?php echo (int) $atRiskResult->num_rows; ?></span>
                    <?php if ($atRiskResult->num_rows > 0): ?>
                        <form method="post" class="d-inline-block">
                            <input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>">
                            <input type="hidden" name="action" value="export_at_risk_pdf">
                            <input type="hidden" name="course_id" value="<?php echo $courseFilterId; ?>">
                            <input type="hidden" name="range" value="<?php echo htmlspecialchars($rangeFilter); ?>">
                            <input type="hidden" name="date_from" value="<?php echo htmlspecialchars($customFromInput); ?>">
                            <input type="hidden" name="date_to" value="<?php echo htmlspecialchars($customToInput); ?>">
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
                                <th>Course/Room</th>
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
                                <?php $riskEmail = $row['email'] ?: $row['guardian_email']; ?>
                                <?php $riskPlace = trim(($row['course_code'] ?: '') . ' ' . ($row['room_name'] ?: '')); ?>
                                <tr class="sp-risk-row">
                                    <td>
                                        <span class="sp-risk-name"><?php echo htmlspecialchars(trim($row['first_name'] . ' ' . $row['last_name'])); ?></span>
                                        <div class="sp-risk-meta"><?php echo htmlspecialchars($row['student_id'] ?: '-'); ?></div>
                                    </td>
                                    <td>
                                        <?php if ($riskPlace !== ''): ?>
                                            <span class="sp-risk-pill sp-risk-pill--room"><i class="fa-solid fa-door-open"></i> <?php echo htmlspecialchars($riskPlace); ?></span>
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

<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mt-4 mb-3">
    <h4 class="mb-0 sp-script-title">Analytics</h4>
    <form method="get" class="d-flex align-items-center flex-wrap gap-2 mb-0" id="analyticsFilterForm">
        <label class="small text-muted mb-0" for="analyticsCourseFilter">Program:</label>
        <select class="form-select form-select-sm sp-filter-select" name="course_id" id="analyticsCourseFilter" onchange="this.form.submit()" style="width:auto;">
            <option value="0">All Programs</option>
            <?php foreach ($courseFilterOptions as $courseOpt): ?>
                <option value="<?php echo $courseOpt['id']; ?>" <?php echo $courseFilterId === (int) $courseOpt['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($courseOpt['code']); ?></option>
            <?php endforeach; ?>
        </select>
        <label class="small text-muted mb-0 ms-1" for="analyticsRangeFilter">Range:</label>
        <select class="form-select form-select-sm sp-filter-select" name="range" id="analyticsRangeFilter" style="width:auto;">
            <option value="">All Time</option>
            <option value="this_week" <?php echo $rangeFilter === 'this_week' ? 'selected' : ''; ?>>This Week</option>
            <option value="this_month" <?php echo $rangeFilter === 'this_month' ? 'selected' : ''; ?>>This Month</option>
            <option value="last_month" <?php echo $rangeFilter === 'last_month' ? 'selected' : ''; ?>>Last Month</option>
            <option value="this_year" <?php echo $rangeFilter === 'this_year' ? 'selected' : ''; ?>>This Year</option>
            <option value="school_year" <?php echo $rangeFilter === 'school_year' ? 'selected' : ''; ?>>School Year (<?php echo htmlspecialchars($schoolYear); ?>)</option>
            <option value="custom" <?php echo $rangeFilter === 'custom' ? 'selected' : ''; ?>>Specific Date Range...</option>
        </select>
        <span class="d-flex align-items-center gap-2" id="analyticsCustomRangeFields" style="<?php echo $rangeFilter === 'custom' ? '' : 'display:none;'; ?>">
            <input type="date" class="form-control form-control-sm" name="date_from" value="<?php echo htmlspecialchars($customFromInput); ?>" style="width:auto;">
            <span class="text-muted small">to</span>
            <input type="date" class="form-control form-control-sm" name="date_to" value="<?php echo htmlspecialchars($customToInput); ?>" style="width:auto;">
            <button type="submit" class="btn btn-sm btn-primary">Apply</button>
        </span>
    </form>
</div>
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
                <h6 class="mb-0">Attendance Rate by Room</h6>
                <div class="d-flex align-items-center gap-2">
                    <?php if ($roomData): ?>
                        <button type="button" class="btn btn-sm sp-export-btn" id="spExportRoomPdf" title="Export as PDF"><i class="fa-solid fa-file-pdf"></i></button>
                    <?php endif; ?>
                    <button type="button" class="btn btn-sm sp-export-btn sp-expand-btn" title="Expand"><i class="fa-solid fa-expand"></i></button>
                </div>
            </div>
            <?php if ($roomData): ?>
                <div class="sp-chart-box" style="height:280px;"><canvas id="roomChart"></canvas></div>
                <?php echo renderChartInsights(insightsForRateRanking($roomLabels, $roomData, 'room')); ?>
            <?php else: ?>
                <p class="text-muted small mb-0">Not enough data yet.</p>
            <?php endif; ?>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card p-3 h-100">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h6 class="mb-0">Attendance Rate by Course</h6>
                <div class="d-flex align-items-center gap-2">
                    <?php if ($courseData): ?>
                        <button type="button" class="btn btn-sm sp-export-btn" id="spExportCoursePdf" title="Export as PDF"><i class="fa-solid fa-file-pdf"></i></button>
                    <?php endif; ?>
                    <button type="button" class="btn btn-sm sp-export-btn sp-expand-btn" title="Expand"><i class="fa-solid fa-expand"></i></button>
                </div>
            </div>
            <?php if ($courseData): ?>
                <div class="sp-chart-box" style="height:280px;"><canvas id="courseChart"></canvas></div>
                <?php echo renderChartInsights(insightsForRateRanking($courseLabels, $courseData, 'course')); ?>
            <?php else: ?>
                <p class="text-muted small mb-0">Not enough data yet.</p>
            <?php endif; ?>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card p-3 h-100">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h6 class="mb-0">Attendance Rate by Teacher</h6>
                <div class="d-flex align-items-center gap-2">
                    <?php if ($teacherData): ?>
                        <button type="button" class="btn btn-sm sp-export-btn" id="spExportTeacherPdf" title="Export as PDF"><i class="fa-solid fa-file-pdf"></i></button>
                    <?php endif; ?>
                    <button type="button" class="btn btn-sm sp-export-btn sp-expand-btn" title="Expand"><i class="fa-solid fa-expand"></i></button>
                </div>
            </div>
            <?php if ($teacherData): ?>
                <div class="sp-chart-box" style="height:280px;"><canvas id="teacherChart"></canvas></div>
                <?php echo renderChartInsights(insightsForRateRanking($teacherLabels, $teacherData, 'teacher')); ?>
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

<div class="modal fade" id="studentsListModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content rounded-4">
            <div class="modal-header">
                <h5 class="modal-title">Students (<?php echo $studentsCount; ?>)</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <ul class="list-group list-group-flush">
                    <?php if ($studentsList->num_rows === 0): ?>
                        <li class="list-group-item text-muted small">No students yet.</li>
                    <?php else: ?>
                        <?php while ($row = $studentsList->fetch_assoc()): ?>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <span><?php echo htmlspecialchars($row['first_name'] . ' ' . $row['last_name']); ?></span>
                                <span class="text-muted small"><?php echo htmlspecialchars(trim(($row['course_code'] ?: '') . ' ' . ($row['room_name'] ?: ''))); ?></span>
                            </li>
                        <?php endwhile; ?>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="teachersListModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content rounded-4">
            <div class="modal-header">
                <h5 class="modal-title">Teachers (<?php echo $teachersCount; ?>)</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <ul class="list-group list-group-flush">
                    <?php if ($teachersList->num_rows === 0): ?>
                        <li class="list-group-item text-muted small">No teachers yet.</li>
                    <?php else: ?>
                        <?php while ($row = $teachersList->fetch_assoc()): ?>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <span><?php echo htmlspecialchars($row['first_name'] . ' ' . $row['last_name']); ?></span>
                                <span class="text-muted small"><?php echo htmlspecialchars($row['subject']); ?></span>
                            </li>
                        <?php endwhile; ?>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="coursesListModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content rounded-4">
            <div class="modal-header">
                <h5 class="modal-title">Courses (<?php echo $coursesCount; ?>)</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <ul class="list-group list-group-flush">
                    <?php if ($coursesListDash->num_rows === 0): ?>
                        <li class="list-group-item text-muted small">No courses yet.</li>
                    <?php else: ?>
                        <?php while ($row = $coursesListDash->fetch_assoc()): ?>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <span><?php echo htmlspecialchars($row['name']); ?></span>
                                <span class="text-muted small"><?php echo htmlspecialchars($row['code']); ?></span>
                            </li>
                        <?php endwhile; ?>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="roomsListModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content rounded-4">
            <div class="modal-header">
                <h5 class="modal-title">Rooms (<?php echo $roomsCount; ?>)</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <ul class="list-group list-group-flush">
                    <?php if ($roomsListDash->num_rows === 0): ?>
                        <li class="list-group-item text-muted small">No rooms yet.</li>
                    <?php else: ?>
                        <?php while ($row = $roomsListDash->fetch_assoc()): ?>
                            <li class="list-group-item"><?php echo htmlspecialchars($row['year_level'] . ' - ' . $row['room_name']); ?></li>
                        <?php endwhile; ?>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="attendanceTodayListModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content rounded-4">
            <div class="modal-header">
                <h5 class="modal-title">Attendance Today (<?php echo $attendanceToday; ?>)</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <ul class="list-group list-group-flush">
                    <?php if ($attendanceTodayList->num_rows === 0): ?>
                        <li class="list-group-item text-muted small">No attendance recorded yet today.</li>
                    <?php else: ?>
                        <?php while ($row = $attendanceTodayList->fetch_assoc()): ?>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <span><?php echo htmlspecialchars($row['first_name'] . ' ' . $row['last_name']); ?></span>
                                <span class="d-flex align-items-center gap-2">
                                    <?php echo badgeStatus($row['status']); ?>
                                    <span class="text-muted small"><?php echo formatTime($row['time']); ?></span>
                                </span>
                            </li>
                        <?php endwhile; ?>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </div>
</div>
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.0/chart.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
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
    var analyticsRangeFilter = document.getElementById('analyticsRangeFilter');
    var analyticsCustomRangeFields = document.getElementById('analyticsCustomRangeFields');
    if (analyticsRangeFilter) {
        analyticsRangeFilter.addEventListener('change', function () {
            if (this.value === 'custom') {
                analyticsCustomRangeFields.style.display = 'flex';
            } else {
                analyticsCustomRangeFields.style.display = 'none';
                this.form.submit();
            }
        });
    }

    function greenShade(t) {
        // Light green (low value) fading up to a deep green (high value). t is 0-1.
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

    <?php if ($roomData): ?>
    var roomChart = new Chart(document.getElementById('roomChart'), {
        type: 'bar',
        data: {
            labels: <?php echo json_encode($roomLabels); ?>,
            datasets: [{ label: 'Attendance Rate', data: <?php echo json_encode($roomData); ?>, backgroundColor: greenShadesForPercent(<?php echo json_encode($roomData); ?>), borderRadius: 6, maxBarThickness: 40 }]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                x: { title: spAxisTitle('Room') },
                y: { min: 0, max: 100, ticks: { callback: function (v) { return v + '%'; } }, title: spAxisTitle('Rate (%)') }
            }
        }
    });
    spWireExportBtn('spExportRoomPdf', roomChart, 'Attendance Rate by Room');
    <?php endif; ?>

    <?php if ($courseData): ?>
    var courseChart = new Chart(document.getElementById('courseChart'), {
        type: 'bar',
        data: {
            labels: <?php echo json_encode($courseLabels); ?>,
            datasets: [{ label: 'Attendance Rate', data: <?php echo json_encode($courseData); ?>, backgroundColor: greenShadesForPercent(<?php echo json_encode($courseData); ?>), borderRadius: 6, maxBarThickness: 40 }]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                x: { title: spAxisTitle('Course') },
                y: { min: 0, max: 100, ticks: { callback: function (v) { return v + '%'; } }, title: spAxisTitle('Rate (%)') }
            }
        }
    });
    spWireExportBtn('spExportCoursePdf', courseChart, 'Attendance Rate by Course');
    <?php endif; ?>

    <?php if ($teacherData): ?>
    var teacherChart = new Chart(document.getElementById('teacherChart'), {
        type: 'bar',
        data: {
            labels: <?php echo json_encode($teacherLabels); ?>,
            datasets: [{ label: 'Attendance Rate', data: <?php echo json_encode($teacherData); ?>, backgroundColor: greenShadesForPercent(<?php echo json_encode($teacherData); ?>), borderRadius: 6, maxBarThickness: 40 }]
        },
        options: {
            indexAxis: 'y',
            responsive: true, maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                x: { min: 0, max: 100, ticks: { callback: function (v) { return v + '%'; } }, title: spAxisTitle('Rate (%)') },
                y: { title: spAxisTitle('Teacher') }
            }
        }
    });
    spWireExportBtn('spExportTeacherPdf', teacherChart, 'Attendance Rate by Teacher');
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
<div class="modal fade" id="spEmailComposeModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-4 border-0 sp-compose-modal">
            <form method="post" id="spEmailComposeForm">
                <div class="sp-compose-header">
                    <button type="button" class="sp-compose-close" data-bs-dismiss="modal" aria-label="Close"><i class="fa-solid fa-xmark"></i></button>
                    <h5 class="sp-compose-title">Compose Email</h5>
                    <p class="sp-compose-subtitle">Send a message straight to this student's inbox.</p>
                </div>
                <div class="sp-compose-icon"><i class="fa-solid fa-envelope"></i></div>
                <div class="sp-compose-body">
                    <input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>">
                    <input type="hidden" name="action" value="send_at_risk_email">
                    <input type="hidden" name="student_id" id="spEmailStudentId">
                    <div class="sp-compose-field">
                        <i class="fa-solid fa-user"></i>
                        <input type="text" id="spEmailTo" aria-label="Recipient" disabled>
                    </div>
                    <div class="sp-compose-field">
                        <i class="fa-solid fa-tag"></i>
                        <input type="text" name="subject" id="spEmailSubject" aria-label="Subject" placeholder="Subject" maxlength="150" required>
                    </div>
                    <div class="sp-compose-field sp-compose-field--textarea">
                        <i class="fa-solid fa-pen"></i>
                        <textarea name="message" id="spEmailMessage" aria-label="Message" placeholder="Your message" rows="6" maxlength="3000" required></textarea>
                    </div>
                </div>
                <div class="sp-compose-footer">
                    <button type="button" class="btn sp-compose-cancel" data-bs-dismiss="modal" id="spEmailCancelBtn">Cancel</button>
                    <button type="submit" class="btn sp-compose-send" id="spEmailSendBtn"><i class="fa-solid fa-paper-plane me-2"></i>Send Message</button>
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
    var modalEl = document.getElementById('spEmailComposeModal');
    var closeBtn = modalEl.querySelector('[data-bs-dismiss="modal"]:not(#spEmailCancelBtn)');
    var idleHtml = sendBtn.innerHTML;
    var isSending = false;

    function setSending(sending) {
        isSending = sending;
        sendBtn.disabled = sending;
        cancelBtn.disabled = sending;
        if (closeBtn) closeBtn.disabled = sending;
        modalEl.classList.toggle('sp-compose-sending', sending);
        sendBtn.innerHTML = sending
            ? '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Sending...'
            : idleHtml;
    }

    // Once sending has started the modal can't be closed (Esc, backdrop click or the X)
    modalEl.addEventListener('hide.bs.modal', function (e) {
        if (isSending) e.preventDefault();
    });

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
            'Please make it a habit to attend classes on time. Reach out to the school office if you have any concerns.';
        var modal = new bootstrap.Modal(document.getElementById('spEmailComposeModal'));
        modal.show();
    });
});
</script>
<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>
