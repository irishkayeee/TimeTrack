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

$guardianCoverageSql = "SELECT COUNT(*) AS total, SUM(guardian_email IS NOT NULL AND guardian_email != '') AS with_email FROM students" . ($courseFilterId ? " WHERE course_id = $courseFilterId" : '');
$guardianCoverage = $mysqli->query($guardianCoverageSql)->fetch_assoc();
$guardianCoveragePct = $guardianCoverage['total'] ? round($guardianCoverage['with_email'] / $guardianCoverage['total'] * 100) : 0;

$trendLabels = [];
$trendData = [];
$trendSql = "SELECT YEARWEEK(date, 1) AS yw, MIN(date) AS week_start, SUM(status IN ('present','late')) AS attended, COUNT(*) AS total FROM attendance WHERE date >= DATE_SUB(CURDATE(), INTERVAL 8 WEEK)" . ($courseFilterId ? " AND course_id = $courseFilterId" : '') . ' GROUP BY yw ORDER BY yw';
$trendResult = $mysqli->query($trendSql);
while ($row = $trendResult->fetch_assoc()) {
    $trendLabels[] = date('M j', strtotime($row['week_start']));
    $trendData[] = $row['total'] ? round($row['attended'] / $row['total'] * 100) : 0;
}

$roomLabels = [];
$roomData = [];
$roomRateSql = "SELECT sec.room_name, sec.year_level, SUM(a.status IN ('present','late')) AS attended, COUNT(a.id) AS total FROM attendance a JOIN rooms sec ON a.room_id = sec.id" . ($courseFilterId ? " WHERE a.course_id = $courseFilterId" : '') . ' GROUP BY sec.id ORDER BY sec.room_name';
$roomRateResult = $mysqli->query($roomRateSql);
while ($row = $roomRateResult->fetch_assoc()) {
    $roomLabels[] = $row['room_name'];
    $roomData[] = $row['total'] ? round($row['attended'] / $row['total'] * 100) : 0;
}

$courseLabels = [];
$courseData = [];
$courseRateSql = "SELECT c.code, SUM(a.status IN ('present','late')) AS attended, COUNT(a.id) AS total FROM attendance a JOIN courses c ON a.course_id = c.id" . ($courseFilterId ? " WHERE a.course_id = $courseFilterId" : '') . ' GROUP BY c.id ORDER BY c.code';
$courseRateResult = $mysqli->query($courseRateSql);
while ($row = $courseRateResult->fetch_assoc()) {
    $courseLabels[] = $row['code'];
    $courseData[] = $row['total'] ? round($row['attended'] / $row['total'] * 100) : 0;
}

$dowLabels = [];
$dowData = [];
$dowSql = "SELECT DAYOFWEEK(date) AS dnum, DAYNAME(date) AS dname, SUM(status = 'absent') AS absents FROM attendance" . ($courseFilterId ? " WHERE course_id = $courseFilterId" : '') . ' GROUP BY dnum, dname ORDER BY dnum';
$dowResult = $mysqli->query($dowSql);
while ($row = $dowResult->fetch_assoc()) {
    $dowLabels[] = $row['dname'];
    $dowData[] = (int) $row['absents'];
}

$teacherLabels = [];
$teacherData = [];
$teacherRateSql = "SELECT t.first_name, t.last_name, SUM(a.status IN ('present','late')) AS attended, COUNT(a.id) AS total FROM attendance a JOIN subjects sub ON a.subject_id = sub.id JOIN teachers t ON sub.teacher_id = t.id" . ($courseFilterId ? " WHERE a.course_id = $courseFilterId" : '') . ' GROUP BY t.id ORDER BY t.first_name';
$teacherRateResult = $mysqli->query($teacherRateSql);
while ($row = $teacherRateResult->fetch_assoc()) {
    $teacherLabels[] = $row['first_name'] . ' ' . substr($row['last_name'], 0, 1) . '.';
    $teacherData[] = $row['total'] ? round($row['attended'] / $row['total'] * 100) : 0;
}

$hourLabels = [];
$hourData = [];
$hourSql = 'SELECT HOUR(time) AS hr, COUNT(*) AS cnt FROM attendance' . ($courseFilterId ? " WHERE course_id = $courseFilterId" : '') . ' GROUP BY hr ORDER BY hr';
$hourResult = $mysqli->query($hourSql);
while ($row = $hourResult->fetch_assoc()) {
    $hourLabels[] = date('g A', strtotime($row['hr'] . ':00'));
    $hourData[] = (int) $row['cnt'];
}

$enrollLabels = [];
$enrollData = [];
$enrollSql = "SELECT DATE_FORMAT(created_at, '%Y-%m') AS ym, COUNT(*) AS cnt FROM students WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)" . ($courseFilterId ? " AND course_id = $courseFilterId" : '') . ' GROUP BY ym ORDER BY ym';
$enrollResult = $mysqli->query($enrollSql);
while ($row = $enrollResult->fetch_assoc()) {
    $enrollLabels[] = date('M Y', strtotime($row['ym'] . '-01'));
    $enrollData[] = (int) $row['cnt'];
}

$atRiskSql = "SELECT s.id, s.first_name, s.last_name, s.student_id, c.code AS course_code, sec.room_name, SUM(a.status = 'absent') AS absents, SUM(a.status = 'late') AS lates, COUNT(a.id) AS total FROM attendance a JOIN students s ON a.student_id = s.id LEFT JOIN courses c ON s.course_id = c.id LEFT JOIN rooms sec ON s.room_id = sec.id" . ($courseFilterId ? " WHERE a.course_id = $courseFilterId" : '') . " GROUP BY s.id HAVING (SUM(a.status = 'absent') + SUM(a.status = 'late')) > 0 ORDER BY (SUM(a.status = 'absent') * 2 + SUM(a.status = 'late')) DESC LIMIT 10";
$atRiskResult = $mysqli->query($atRiskSql);

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
            <h4 class="mb-1"><span id="spGreetingWord"><?php echo htmlspecialchars($greeting); ?></span>, <?php echo htmlspecialchars($adminUser['role'] ?? 'admin'); ?>! 👋</h4>
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
            timeEl.textContent = now.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true });
        }
        if (dateEl) {
            dateEl.textContent = now.toLocaleDateString('en-US', { weekday: 'long', month: 'long', day: 'numeric', year: 'numeric' });
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
<div class="row g-3">
    <div class="col-6 col-lg">
        <div class="card p-3 text-center stat-card-clickable" data-bs-toggle="modal" data-bs-target="#studentsListModal">
            <div class="d-flex align-items-center justify-content-center gap-2 mb-2">
                <span class="stat-icon"><i class="fa-solid fa-user-graduate"></i></span>
                <h6 class="text-muted small text-uppercase mb-0">Students</h6>
            </div>
            <h2 class="mb-0"><?php echo $studentsCount; ?></h2>
        </div>
    </div>
    <div class="col-6 col-lg">
        <div class="card p-3 text-center stat-card-clickable" data-bs-toggle="modal" data-bs-target="#teachersListModal">
            <div class="d-flex align-items-center justify-content-center gap-2 mb-2">
                <span class="stat-icon"><i class="fa-solid fa-chalkboard-user"></i></span>
                <h6 class="text-muted small text-uppercase mb-0">Teachers</h6>
            </div>
            <h2 class="mb-0"><?php echo $teachersCount; ?></h2>
        </div>
    </div>
    <div class="col-6 col-lg">
        <div class="card p-3 text-center stat-card-clickable" data-bs-toggle="modal" data-bs-target="#coursesListModal">
            <div class="d-flex align-items-center justify-content-center gap-2 mb-2">
                <span class="stat-icon"><i class="fa-solid fa-graduation-cap"></i></span>
                <h6 class="text-muted small text-uppercase mb-0">Courses</h6>
            </div>
            <h2 class="mb-0"><?php echo $coursesCount; ?></h2>
        </div>
    </div>
    <div class="col-6 col-lg">
        <div class="card p-3 text-center stat-card-clickable" data-bs-toggle="modal" data-bs-target="#roomsListModal">
            <div class="d-flex align-items-center justify-content-center gap-2 mb-2">
                <span class="stat-icon"><i class="fa-solid fa-people-group"></i></span>
                <h6 class="text-muted small text-uppercase mb-0">Rooms</h6>
            </div>
            <h2 class="mb-0"><?php echo $roomsCount; ?></h2>
        </div>
    </div>
    <div class="col-6 col-lg">
        <div class="card p-3 text-center stat-card-clickable" data-bs-toggle="modal" data-bs-target="#attendanceTodayListModal">
            <div class="d-flex align-items-center justify-content-center gap-2 mb-2">
                <span class="stat-icon"><i class="fa-solid fa-clipboard-check"></i></span>
                <h6 class="text-muted small text-uppercase mb-0">Attendance Today</h6>
            </div>
            <h2 class="mb-0"><?php echo $attendanceToday; ?></h2>
        </div>
    </div>
</div>

<div class="row g-3 mt-1">
    <div class="col-6 col-lg-4">
        <div class="card p-3 text-center">
            <div class="d-flex align-items-center justify-content-center gap-2 mb-2">
                <span class="stat-icon"><i class="fa-solid fa-envelope-circle-check"></i></span>
                <h6 class="text-muted small text-uppercase mb-0">Guardian Email Coverage</h6>
            </div>
            <h2 class="mb-0"><?php echo $guardianCoveragePct; ?>%</h2>
            <p class="text-muted small mb-0"><?php echo (int) $guardianCoverage['with_email']; ?> of <?php echo (int) $guardianCoverage['total']; ?> students</p>
        </div>
    </div>
</div>

<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mt-4 mb-3">
    <h4 class="mb-0">Analytics</h4>
    <form method="get" class="d-flex align-items-center gap-2 mb-0">
        <label class="small text-muted mb-0" for="analyticsCourseFilter">Program:</label>
        <select class="form-select form-select-sm sp-filter-select" name="course_id" id="analyticsCourseFilter" onchange="this.form.submit()" style="width:auto;">
            <option value="0">All Programs</option>
            <?php foreach ($courseFilterOptions as $courseOpt): ?>
                <option value="<?php echo $courseOpt['id']; ?>" <?php echo $courseFilterId === (int) $courseOpt['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($courseOpt['code']); ?></option>
            <?php endforeach; ?>
        </select>
    </form>
</div>
<div class="row g-3">
    <div class="col-lg-8">
        <div class="card p-3 h-100">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h6 class="mb-0">Attendance Rate Trend <span class="text-muted small fw-normal">(last 8 weeks)</span></h6>
                <?php if ($trendData): ?>
                    <button type="button" class="btn btn-sm sp-export-btn" id="spExportTrendPdf" title="Export as PDF"><i class="fa-solid fa-file-pdf"></i></button>
                <?php endif; ?>
            </div>
            <?php if ($trendData): ?>
                <div style="height:260px;"><canvas id="trendChart"></canvas></div>
            <?php else: ?>
                <p class="text-muted small mb-0">Not enough data yet.</p>
            <?php endif; ?>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card p-3 h-100">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h6 class="mb-0">Peak Absence Days</h6>
                <?php if ($dowData): ?>
                    <button type="button" class="btn btn-sm sp-export-btn" id="spExportDowPdf" title="Export as PDF"><i class="fa-solid fa-file-pdf"></i></button>
                <?php endif; ?>
            </div>
            <?php if ($dowData): ?>
                <div style="height:260px;"><canvas id="dowChart"></canvas></div>
            <?php else: ?>
                <p class="text-muted small mb-0">Not enough data yet.</p>
            <?php endif; ?>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card p-3 h-100">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h6 class="mb-0">Attendance Rate by Room</h6>
                <?php if ($roomData): ?>
                    <button type="button" class="btn btn-sm sp-export-btn" id="spExportRoomPdf" title="Export as PDF"><i class="fa-solid fa-file-pdf"></i></button>
                <?php endif; ?>
            </div>
            <?php if ($roomData): ?>
                <div style="height:240px;"><canvas id="roomChart"></canvas></div>
            <?php else: ?>
                <p class="text-muted small mb-0">Not enough data yet.</p>
            <?php endif; ?>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card p-3 h-100">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h6 class="mb-0">Attendance Rate by Course</h6>
                <?php if ($courseData): ?>
                    <button type="button" class="btn btn-sm sp-export-btn" id="spExportCoursePdf" title="Export as PDF"><i class="fa-solid fa-file-pdf"></i></button>
                <?php endif; ?>
            </div>
            <?php if ($courseData): ?>
                <div style="height:240px;"><canvas id="courseChart"></canvas></div>
            <?php else: ?>
                <p class="text-muted small mb-0">Not enough data yet.</p>
            <?php endif; ?>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card p-3 h-100">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h6 class="mb-0">Attendance Rate by Teacher</h6>
                <?php if ($teacherData): ?>
                    <button type="button" class="btn btn-sm sp-export-btn" id="spExportTeacherPdf" title="Export as PDF"><i class="fa-solid fa-file-pdf"></i></button>
                <?php endif; ?>
            </div>
            <?php if ($teacherData): ?>
                <div style="height:240px;"><canvas id="teacherChart"></canvas></div>
            <?php else: ?>
                <p class="text-muted small mb-0">Not enough data yet.</p>
            <?php endif; ?>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card p-3 h-100">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h6 class="mb-0">Peak Scan Times</h6>
                <?php if ($hourData): ?>
                    <button type="button" class="btn btn-sm sp-export-btn" id="spExportHourPdf" title="Export as PDF"><i class="fa-solid fa-file-pdf"></i></button>
                <?php endif; ?>
            </div>
            <?php if ($hourData): ?>
                <div style="height:240px;"><canvas id="hourChart"></canvas></div>
            <?php else: ?>
                <p class="text-muted small mb-0">Not enough data yet.</p>
            <?php endif; ?>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card p-3 h-100">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h6 class="mb-0">Enrollment Growth <span class="text-muted small fw-normal">(last 6 months)</span></h6>
                <?php if ($enrollData): ?>
                    <button type="button" class="btn btn-sm sp-export-btn" id="spExportEnrollPdf" title="Export as PDF"><i class="fa-solid fa-file-pdf"></i></button>
                <?php endif; ?>
            </div>
            <?php if ($enrollData): ?>
                <div style="height:240px;"><canvas id="enrollChart"></canvas></div>
            <?php else: ?>
                <p class="text-muted small mb-0">Not enough data yet.</p>
            <?php endif; ?>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card p-3 h-100">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h6 class="mb-0">At-Risk Students <span class="text-muted small fw-normal">(most late/absent)</span></h6>
                <?php if ($atRiskResult->num_rows > 0): ?>
                    <form method="post" class="d-inline-block">
                        <input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>">
                        <input type="hidden" name="action" value="export_at_risk_pdf">
                        <input type="hidden" name="course_id" value="<?php echo $courseFilterId; ?>">
                        <button type="submit" class="btn btn-sm sp-export-btn" title="Export as PDF"><i class="fa-solid fa-file-pdf"></i></button>
                    </form>
                <?php endif; ?>
            </div>
            <?php if ($atRiskResult->num_rows === 0): ?>
                <p class="text-muted small mb-0">No at-risk students right now.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead>
                            <tr><th>Student</th><th>Course/Room</th><th class="text-center">Absent</th><th class="text-center">Late</th></tr>
                        </thead>
                        <tbody>
                            <?php while ($row = $atRiskResult->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($row['first_name'] . ' ' . $row['last_name']); ?></td>
                                    <td class="text-muted small"><?php echo htmlspecialchars(trim(($row['course_code'] ?: '') . ' ' . ($row['room_name'] ?: ''))); ?></td>
                                    <td class="text-center"><span class="badge bg-danger"><?php echo (int) $row['absents']; ?></span></td>
                                    <td class="text-center"><span class="badge bg-warning"><?php echo (int) $row['lates']; ?></span></td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
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
document.addEventListener('DOMContentLoaded', function () {
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
        doc.text('<?php echo htmlspecialchars(addslashes($schoolName)); ?> — Generated ' + new Date().toLocaleString('en-US'), 40, 58);

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
            scales: { y: { min: 0, max: 100, ticks: { callback: function (v) { return v + '%'; } } } }
        }
    });
    spWireExportBtn('spExportTrendPdf', trendChart, 'Attendance Rate Trend');
    <?php endif; ?>

    <?php if ($dowData): ?>
    var dowChart = new Chart(document.getElementById('dowChart'), {
        type: 'bar',
        data: {
            labels: <?php echo json_encode($dowLabels); ?>,
            datasets: [{ label: 'Absences', data: <?php echo json_encode($dowData); ?>, backgroundColor: greenShadesForCount(<?php echo json_encode($dowData); ?>), borderRadius: 6, maxBarThickness: 32 }]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: { y: { beginAtZero: true, ticks: { precision: 0 } } }
        }
    });
    spWireExportBtn('spExportDowPdf', dowChart, 'Peak Absence Days');
    <?php endif; ?>

    <?php if ($roomData): ?>
    var roomChart = new Chart(document.getElementById('roomChart'), {
        type: 'bar',
        data: {
            labels: <?php echo json_encode($roomLabels); ?>,
            datasets: [{ label: 'Attendance Rate', data: <?php echo json_encode($roomData); ?>, backgroundColor: greenShadesForPercent(<?php echo json_encode($roomData); ?>), borderRadius: 6, maxBarThickness: 42 }]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: { y: { min: 0, max: 100, ticks: { callback: function (v) { return v + '%'; } } } }
        }
    });
    spWireExportBtn('spExportRoomPdf', roomChart, 'Attendance Rate by Room');
    <?php endif; ?>

    <?php if ($courseData): ?>
    var courseChart = new Chart(document.getElementById('courseChart'), {
        type: 'bar',
        data: {
            labels: <?php echo json_encode($courseLabels); ?>,
            datasets: [{ label: 'Attendance Rate', data: <?php echo json_encode($courseData); ?>, backgroundColor: greenShadesForPercent(<?php echo json_encode($courseData); ?>), borderRadius: 6, maxBarThickness: 42 }]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: { y: { min: 0, max: 100, ticks: { callback: function (v) { return v + '%'; } } } }
        }
    });
    spWireExportBtn('spExportCoursePdf', courseChart, 'Attendance Rate by Course');
    <?php endif; ?>

    <?php if ($teacherData): ?>
    var teacherChart = new Chart(document.getElementById('teacherChart'), {
        type: 'bar',
        data: {
            labels: <?php echo json_encode($teacherLabels); ?>,
            datasets: [{ label: 'Attendance Rate', data: <?php echo json_encode($teacherData); ?>, backgroundColor: greenShadesForPercent(<?php echo json_encode($teacherData); ?>), borderRadius: 6, maxBarThickness: 42 }]
        },
        options: {
            indexAxis: 'y',
            responsive: true, maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: { x: { min: 0, max: 100, ticks: { callback: function (v) { return v + '%'; } } } }
        }
    });
    spWireExportBtn('spExportTeacherPdf', teacherChart, 'Attendance Rate by Teacher');
    <?php endif; ?>

    <?php if ($hourData): ?>
    var hourChart = new Chart(document.getElementById('hourChart'), {
        type: 'bar',
        data: {
            labels: <?php echo json_encode($hourLabels); ?>,
            datasets: [{ label: 'Scans', data: <?php echo json_encode($hourData); ?>, backgroundColor: greenShadesForCount(<?php echo json_encode($hourData); ?>), borderRadius: 6, maxBarThickness: 32 }]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: { y: { beginAtZero: true, ticks: { precision: 0 } } }
        }
    });
    spWireExportBtn('spExportHourPdf', hourChart, 'Peak Scan Times');
    <?php endif; ?>

    <?php if ($enrollData): ?>
    var enrollChart = new Chart(document.getElementById('enrollChart'), {
        type: 'bar',
        data: {
            labels: <?php echo json_encode($enrollLabels); ?>,
            datasets: [{ label: 'New Students', data: <?php echo json_encode($enrollData); ?>, backgroundColor: greenShadesForCount(<?php echo json_encode($enrollData); ?>), borderRadius: 6, maxBarThickness: 42 }]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: { y: { beginAtZero: true, ticks: { precision: 0 } } }
        }
    });
    spWireExportBtn('spExportEnrollPdf', enrollChart, 'Enrollment Growth');
    <?php endif; ?>
});
</script>
<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>
