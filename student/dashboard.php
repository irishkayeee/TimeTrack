<?php
require_once __DIR__ . '/../includes/functions.php';
requireRole(['student']);
$pageTitle = 'Dashboard';
$pageSubtitle = 'Your attendance overview.';

$studentDbId = currentStudentId();
if ($studentDbId === false) {
    flash('Your student profile is not set up. Contact an administrator.', 'danger');
    redirect('../dashboard.php');
}

$stmt = $mysqli->prepare('SELECT s.first_name, s.section_id, c.code AS course_code, sec.section_name FROM students s LEFT JOIN courses c ON s.course_id = c.id LEFT JOIN sections sec ON s.section_id = sec.id WHERE s.id = ?');
$stmt->bind_param('i', $studentDbId);
$stmt->execute();
$me = $stmt->get_result()->fetch_assoc();
$sectionId = $me['section_id'];
$stmt->close();

$hour = (int) date('G');
$greeting = $hour < 12 ? 'Good morning' : ($hour < 18 ? 'Good afternoon' : 'Good evening');
$semester = getSetting('semester', '1st Semester');
$schoolYear = getSetting('school_year', date('Y') . '-' . (date('Y') + 1));

// Months that actually have attendance history, for the month filter dropdown.
$monthsStmt = $mysqli->prepare("SELECT DISTINCT DATE_FORMAT(date, '%Y-%m') AS ym FROM attendance WHERE student_id = ? ORDER BY ym DESC");
$monthsStmt->bind_param('i', $studentDbId);
$monthsStmt->execute();
$monthsResult = $monthsStmt->get_result();
$availableMonths = [];
while ($row = $monthsResult->fetch_assoc()) {
    $availableMonths[] = $row['ym'];
}
$monthsStmt->close();

// Attendance by Subject (bar chart) — every active subject in the student's
// section, defaulting to 0% when there is no attendance history yet.
$subjectRates = [];
if ($sectionId) {
    $stmt = $mysqli->prepare("SELECT id, code, name FROM subjects WHERE section_id = ? AND status = 'active' ORDER BY name");
    $stmt->bind_param('i', $sectionId);
    $stmt->execute();
    $subjectsResult = $stmt->get_result();
    while ($subjectRow = $subjectsResult->fetch_assoc()) {
        $countStmt = $mysqli->prepare("SELECT COUNT(*) AS total, SUM(status IN ('present','late')) AS attended FROM attendance WHERE student_id = ? AND subject_id = ?");
        $countStmt->bind_param('ii', $studentDbId, $subjectRow['id']);
        $countStmt->execute();
        $counts = $countStmt->get_result()->fetch_assoc();
        $countStmt->close();
        $total = (int) $counts['total'];
        $rate = $total ? round((((int) $counts['attended']) / $total) * 100) : 0;
        $subjectRates[] = ['id' => (int) $subjectRow['id'], 'code' => $subjectRow['code'], 'name' => $subjectRow['name'], 'rate' => $rate];
    }
    $stmt->close();
}

// Today's Classes — this student's section's schedule for today, with live status.
$dayMap = ['Mon' => 1, 'Tue' => 2, 'Wed' => 3, 'Thu' => 4, 'Fri' => 5, 'Sat' => 6, 'Sun' => 7];
$todayCode = array_search((int) date('N'), $dayMap);
$todayClasses = [];
if ($sectionId) {
    $stmt = $mysqli->prepare("SELECT sub.*, CONCAT(t.first_name, ' ', t.last_name) AS teacher_name FROM subjects sub LEFT JOIN teachers t ON sub.teacher_id = t.id WHERE sub.section_id = ? AND sub.day_of_week = ? AND sub.status = 'active' ORDER BY sub.start_time");
    $stmt->bind_param('is', $sectionId, $todayCode);
    $stmt->execute();
    $todayResult = $stmt->get_result();
    while ($row = $todayResult->fetch_assoc()) {
        $attStmt = $mysqli->prepare("SELECT status, time FROM attendance WHERE student_id = ? AND subject_id = ? AND date = CURDATE() LIMIT 1");
        $attStmt->bind_param('ii', $studentDbId, $row['id']);
        $attStmt->execute();
        $att = $attStmt->get_result()->fetch_assoc();
        $attStmt->close();
        if ($att) {
            $row['display_status'] = $att['status'];
        } else {
            $minutesSinceStart = (strtotime(date('H:i:s')) - strtotime($row['start_time'])) / 60;
            if ($minutesSinceStart < 0) {
                $row['display_status'] = 'upcoming';
            } elseif ($minutesSinceStart < effectiveAbsentCutoff($row)) {
                $row['display_status'] = 'ongoing';
            } else {
                $row['display_status'] = 'absent';
            }
        }
        $todayClasses[] = $row;
    }
    $stmt->close();
}

// Recent Attendance — last 5 records across all subjects, with remarks.
$recentStmt = $mysqli->prepare('SELECT a.*, sub.name AS subject_name, sub.start_time AS subject_start_time FROM attendance a LEFT JOIN subjects sub ON a.subject_id = sub.id WHERE a.student_id = ? ORDER BY a.date DESC, a.time DESC LIMIT 5');
$recentStmt->bind_param('i', $studentDbId);
$recentStmt->execute();
$recentResult = $recentStmt->get_result();
$recentRows = [];
while ($row = $recentResult->fetch_assoc()) {
    $recentRows[] = $row;
}
$recentStmt->close();

// Attendance Trend — weekly attendance rate over the last 6 weeks with any records.
$trendStmt = $mysqli->prepare("SELECT YEARWEEK(date, 1) AS yw, MIN(date) AS week_start, COUNT(*) AS total, SUM(status IN ('present','late')) AS attended FROM attendance WHERE student_id = ? GROUP BY YEARWEEK(date, 1) ORDER BY yw DESC LIMIT 6");
$trendStmt->bind_param('i', $studentDbId);
$trendStmt->execute();
$trendResult = $trendStmt->get_result();
$trendWeeks = [];
while ($row = $trendResult->fetch_assoc()) {
    $trendWeeks[] = $row;
}
$trendStmt->close();
$trendWeeks = array_reverse($trendWeeks);

$statusMeta = [
    'present' => ['icon' => 'fa-circle-check', 'class' => 'sp-status-present', 'label' => 'Present'],
    'late' => ['icon' => 'fa-clock', 'class' => 'sp-status-late', 'label' => 'Late'],
    'absent' => ['icon' => 'fa-circle-xmark', 'class' => 'sp-status-absent', 'label' => 'Absent'],
    'excused' => ['icon' => 'fa-circle-info', 'class' => 'sp-status-excused', 'label' => 'Excused'],
];

require_once __DIR__ . '/../includes/student_header.php';
?>
<div class="card p-4 mb-3">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
        <div>
            <h4 class="mb-1"><span id="spGreetingWord"><?php echo htmlspecialchars($greeting); ?></span>, <?php echo htmlspecialchars($me['first_name']); ?>! 👋</h4>
            <p class="text-muted mb-2">Here's your attendance overview and class schedule.</p>
            <div class="d-flex flex-wrap gap-2">
                <span class="sp-shd-pill"><i class="fa-solid fa-user-group"></i> <?php echo htmlspecialchars(trim(($me['course_code'] ?: 'N/A') . ' ' . ($me['section_name'] ?: ''))); ?></span>
                <span class="sp-shd-pill"><i class="fa-solid fa-calendar"></i> <?php echo htmlspecialchars($semester . ', AY ' . $schoolYear); ?></span>
            </div>
        </div>
        <div class="sp-clock-widget text-center">
            <div class="sp-clock-time" id="spClockTime">--:--:-- --</div>
            <div class="sp-clock-date" id="spClockDate">Loading...</div>
        </div>
    </div>
</div>
<div class="row g-3">
    <div class="col-lg-7 d-flex flex-column">
        <div class="card p-4 mb-3 flex-grow-1 d-flex flex-column">
            <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                <h6 class="mb-0 text-uppercase small fw-bold">Attendance by Subject</h6>
                <div class="d-flex align-items-center gap-2">
                    <select class="form-select form-select-sm sp-filter-select" id="spMonthFilter" style="width:auto;">
                        <option value="all">All Time</option>
                        <?php foreach ($availableMonths as $ym): ?>
                            <option value="<?php echo htmlspecialchars($ym); ?>"><?php echo htmlspecialchars(date('F Y', strtotime($ym . '-01'))); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button type="button" class="btn btn-sm sp-export-btn" id="spExportSubjectPdf" title="Export as PDF"><i class="fa-solid fa-file-pdf"></i></button>
                </div>
            </div>
            <p class="text-muted small mb-2" style="font-size:0.75rem;"><i class="fa-solid fa-circle-info me-1"></i>Click a bar to filter the trend chart below by that subject.</p>
            <?php if (empty($subjectRates)): ?>
                <p class="text-muted small mb-0">No subjects scheduled for your section yet.</p>
            <?php else: ?>
                <div class="flex-grow-1 position-relative">
                    <canvas id="subjectRateChart"></canvas>
                </div>
            <?php endif; ?>
        </div>

        <div class="card p-4 flex-grow-1 d-flex flex-column">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h6 class="mb-0 text-uppercase small fw-bold">Attendance Trend <span id="spTrendFilterLabel" class="text-muted fw-normal"></span></h6>
                <div class="d-flex align-items-center gap-2">
                    <a href="#" id="spClearTrendFilter" class="small text-decoration-none d-none">Clear filter <i class="fa-solid fa-xmark ms-1"></i></a>
                    <button type="button" class="btn btn-sm sp-export-btn" id="spExportTrendPdf" title="Export as PDF"><i class="fa-solid fa-file-pdf"></i></button>
                </div>
            </div>
            <?php if (count($trendWeeks) < 2): ?>
                <p class="text-muted small mb-0">Not enough attendance history yet to show a trend.</p>
            <?php else: ?>
                <div class="flex-grow-1 position-relative">
                    <canvas id="trendChart"></canvas>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="card p-4 mb-3">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h6 class="mb-0 text-uppercase small fw-bold"><i class="fa-solid fa-calendar-day me-1"></i> Today's Classes</h6>
                <span class="text-muted small"><?php echo date('M j, Y (l)'); ?></span>
            </div>
            <?php if (empty($todayClasses)): ?>
                <p class="text-muted small mb-0">No classes scheduled today.</p>
            <?php else: ?>
                <?php foreach ($todayClasses as $row): ?>
                    <div class="sp-today-row">
                        <div class="sp-today-time">
                            <?php echo formatTime($row['start_time']); ?><br>
                            <span class="text-muted"><?php echo $row['end_time'] ? formatTime($row['end_time']) : ''; ?></span>
                        </div>
                        <div class="flex-grow-1">
                            <div class="fw-semibold"><?php echo htmlspecialchars($row['name']); ?></div>
                            <div class="text-muted small">Prof. <?php echo htmlspecialchars($row['teacher_name'] ?: 'Unassigned'); ?><?php echo $row['room'] ? ' · ' . htmlspecialchars($row['room']) : ''; ?></div>
                        </div>
                        <?php if ($row['display_status'] === 'upcoming'): ?>
                            <span class="badge sp-badge-upcoming">Upcoming</span>
                        <?php elseif ($row['display_status'] === 'ongoing'): ?>
                            <span class="badge sp-badge-upcoming">Ongoing</span>
                        <?php else: ?>
                            <?php $meta = $statusMeta[$row['display_status']] ?? $statusMeta['absent']; ?>
                            <span class="badge sp-badge-<?php echo $row['display_status']; ?>"><i class="fa-solid <?php echo $meta['icon']; ?> me-1"></i><?php echo $meta['label']; ?></span>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
            <div class="text-center mt-2">
                <a href="subjects.php" class="small text-decoration-none">View Full Schedule <i class="fa-solid fa-arrow-right ms-1"></i></a>
            </div>
        </div>

        <div class="card p-4">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h6 class="mb-0 text-uppercase small fw-bold">Recent Attendance</h6>
                <a href="subjects.php" class="small text-decoration-none">View All <i class="fa-solid fa-arrow-right ms-1"></i></a>
            </div>
            <?php if (empty($recentRows)): ?>
                <p class="text-muted small mb-0">No attendance recorded yet.</p>
            <?php else: ?>
                <?php foreach ($recentRows as $row): ?>
                    <?php
                    $meta = $statusMeta[$row['status']] ?? $statusMeta['excused'];
                    $remarks = 'On time';
                    if ($row['status'] === 'late' && $row['subject_start_time']) {
                        $lateMinutes = max(0, round((strtotime($row['time']) - strtotime($row['subject_start_time'])) / 60));
                        $remarks = 'Arrived ' . formatTime($row['time']) . ' (' . $lateMinutes . ' min late)';
                    } elseif ($row['status'] === 'absent') {
                        $remarks = '—';
                    }
                    ?>
                    <div class="sp-recent-row">
                        <span class="sp-recent-icon <?php echo $meta['class']; ?>"><i class="fa-solid <?php echo $meta['icon']; ?>"></i></span>
                        <div class="flex-grow-1">
                            <div class="fw-semibold small"><?php echo htmlspecialchars($row['subject_name'] ?: 'N/A'); ?></div>
                            <div class="text-muted" style="font-size:0.75rem;"><?php echo formatDate($row['date']); ?> &middot; <?php echo $remarks; ?></div>
                        </div>
                        <span class="badge sp-badge-<?php echo $row['status']; ?>"><?php echo $meta['label']; ?></span>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
            <div class="text-center mt-2">
                <a href="subjects.php" class="small text-decoration-none">View Attendance History <i class="fa-solid fa-arrow-right ms-1"></i></a>
            </div>
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
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.0/chart.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script>
function spGreenShade(pct) {
    // Light green (low attendance) fading up to a deep green (high attendance).
    var light = [199, 230, 209];
    var dark = [20, 83, 45];
    var t = Math.max(0, Math.min(100, pct)) / 100;
    var r = Math.round(light[0] + (dark[0] - light[0]) * t);
    var g = Math.round(light[1] + (dark[1] - light[1]) * t);
    var b = Math.round(light[2] + (dark[2] - light[2]) * t);
    return 'rgb(' + r + ',' + g + ',' + b + ')';
}

var spSubjectIds = <?php echo json_encode(array_map(function ($s) { return $s['id']; }, $subjectRates)); ?>;
var spSelectedSubjectId = 0;
var spSelectedMonth = 'all';
var subjectChart = null;
var trendChart = null;

<?php if (!empty($subjectRates)): ?>
var subjectRateValues = <?php echo json_encode(array_map(function ($s) { return $s['rate']; }, $subjectRates)); ?>;
subjectChart = new Chart(document.getElementById('subjectRateChart'), {
    type: 'bar',
    data: {
        labels: <?php echo json_encode(array_map(function ($s) { return $s['code']; }, $subjectRates)); ?>,
        datasets: [{
            label: 'Attendance Rate',
            data: subjectRateValues,
            backgroundColor: subjectRateValues.map(spGreenShade),
            borderRadius: 6,
            maxBarThickness: 42
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        layout: { padding: { top: 12 } },
        onClick: function (evt, elements) {
            if (!elements.length) return;
            var idx = elements[0].index;
            var subjectId = spSubjectIds[idx];
            var label = subjectChart.data.labels[idx];
            spSelectedSubjectId = subjectId;
            document.getElementById('spTrendFilterLabel').textContent = '— ' + label;
            document.getElementById('spClearTrendFilter').classList.remove('d-none');
            spRefreshTrend();
        },
        plugins: { legend: { display: false } },
        scales: { y: { min: 0, max: 100, ticks: { callback: function (v) { return v + '%'; } } } }
    }
});
<?php endif; ?>

<?php if (count($trendWeeks) >= 2): ?>
trendChart = new Chart(document.getElementById('trendChart'), {
    type: 'line',
    data: {
        labels: <?php echo json_encode(array_map(function ($i) { return 'Week ' . ($i + 1); }, array_keys($trendWeeks))); ?>,
        datasets: [{
            label: 'Attendance Rate',
            data: <?php echo json_encode(array_map(function ($w) { return $w['total'] ? round(($w['attended'] / $w['total']) * 100) : 0; }, $trendWeeks)); ?>,
            borderColor: '#2f7d4f',
            backgroundColor: 'rgba(47,125,79,0.12)',
            fill: true,
            tension: 0.35,
            pointBackgroundColor: '#2f7d4f',
            pointRadius: 5
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        layout: { padding: { top: 20, right: 10, left: 4, bottom: 4 } },
        plugins: { legend: { display: false } },
        clip: false,
        scales: { y: { min: 0, max: 100, ticks: { callback: function (v) { return v + '%'; } } } }
    }
});
<?php endif; ?>

function spRefreshTrend() {
    if (!trendChart) return;
    var url = 'dashboard-data.php?type=trend&subject_id=' + spSelectedSubjectId + '&month=' + encodeURIComponent(spSelectedMonth);
    fetch(url).then(function (r) { return r.json(); }).then(function (json) {
        trendChart.data.labels = json.labels.length ? json.labels : ['No data'];
        trendChart.data.datasets[0].data = json.labels.length ? json.data : [0];
        trendChart.update();
    });
}

function spRefreshSubjects() {
    if (!subjectChart) return;
    var url = 'dashboard-data.php?type=subjects&month=' + encodeURIComponent(spSelectedMonth);
    fetch(url).then(function (r) { return r.json(); }).then(function (json) {
        spSubjectIds = json.subjectIds;
        subjectChart.data.labels = json.labels;
        subjectChart.data.datasets[0].data = json.data;
        subjectChart.data.datasets[0].backgroundColor = json.data.map(spGreenShade);
        subjectChart.update();
    });
}

var monthFilterEl = document.getElementById('spMonthFilter');
if (monthFilterEl) {
    monthFilterEl.addEventListener('change', function () {
        spSelectedMonth = this.value;
        spRefreshSubjects();
        spRefreshTrend();
    });
}

var clearTrendFilterEl = document.getElementById('spClearTrendFilter');
if (clearTrendFilterEl) {
    clearTrendFilterEl.addEventListener('click', function (e) {
        e.preventDefault();
        spSelectedSubjectId = 0;
        document.getElementById('spTrendFilterLabel').textContent = '';
        this.classList.add('d-none');
        spRefreshTrend();
    });
}

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
    doc.text('<?php echo htmlspecialchars(addslashes(($me['course_code'] ?: '') . ' ' . ($me['section_name'] ?: '') . ' — Generated ')); ?>' + new Date().toLocaleString('en-US'), 40, 58);

    var imgWidth = pageWidth - 80;
    var imgHeight = chartInstance.height * (imgWidth / chartInstance.width);
    if (imgHeight > pageHeight - 100) {
        imgHeight = pageHeight - 100;
        imgWidth = chartInstance.width * (imgHeight / chartInstance.height);
    }
    doc.addImage(imgData, 'PNG', 40, 80, imgWidth, imgHeight);
    doc.save(title.replace(/\s+/g, '_').toLowerCase() + '.pdf');
}

var exportSubjectBtn = document.getElementById('spExportSubjectPdf');
if (exportSubjectBtn) {
    exportSubjectBtn.addEventListener('click', function () {
        spExportChartPdf(subjectChart, 'Attendance by Subject');
    });
}

var exportTrendBtn = document.getElementById('spExportTrendPdf');
if (exportTrendBtn) {
    exportTrendBtn.addEventListener('click', function () {
        spExportChartPdf(trendChart, 'Attendance Trend');
    });
}
</script>
<?php require_once __DIR__ . '/../includes/student_footer.php'; ?>
