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

$dayMap = ['Mon' => 1, 'Tue' => 2, 'Wed' => 3, 'Thu' => 4, 'Fri' => 5, 'Sat' => 6, 'Sun' => 7];
$todayCode = array_search((int) date('N'), $dayMap);

$stmt = $mysqli->prepare("SELECT sub.*, sec.room_name FROM subjects sub JOIN rooms sec ON sub.room_id = sec.id WHERE sub.teacher_id = ? AND sub.day_of_week = ? AND sub.status = 'active' ORDER BY sub.start_time");
$stmt->bind_param('is', $teacherId, $todayCode);
$stmt->execute();
$todaySubjects = $stmt->get_result();

$totalCounts = ['present' => 0, 'late' => 0, 'absent' => 0, 'pending' => 0];
$todaySubjectRows = [];
$todaySubjectIds = [];
while ($row = $todaySubjects->fetch_assoc()) {
    $roster = getLiveRosterForSubject($mysqli, $row['id']);
    foreach ($totalCounts as $key => $value) {
        $totalCounts[$key] += $roster['counts'][$key];
    }
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

$atRiskStmt = $mysqli->prepare("SELECT s.id, s.first_name, s.last_name, s.student_id, sec.room_name, SUM(a.status = 'absent') AS absents, SUM(a.status = 'late') AS lates, COUNT(a.id) AS total FROM attendance a JOIN students s ON a.student_id = s.id JOIN subjects sub ON a.subject_id = sub.id LEFT JOIN rooms sec ON s.room_id = sec.id WHERE sub.teacher_id = ? GROUP BY s.id HAVING (SUM(a.status = 'absent') + SUM(a.status = 'late')) > 0 ORDER BY (SUM(a.status = 'absent') * 2 + SUM(a.status = 'late')) DESC LIMIT 10");
$atRiskStmt->bind_param('i', $teacherId);
$atRiskStmt->execute();
$atRiskResult = $atRiskStmt->get_result();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'export_at_risk_pdf') {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        flash('Invalid request.', 'danger');
        redirect('dashboard.php');
    }
    $rows = [];
    while ($row = $atRiskResult->fetch_assoc()) {
        $rows[] = [$row['student_id'], $row['first_name'] . ' ' . $row['last_name'], $row['room_name'] ?: '-', (int) $row['absents'], (int) $row['lates']];
    }
    $pdf = generateSimpleTablePdf('At-Risk Students', ['ID', 'Name', 'Room', 'Absent', 'Late'], $rows, [80, 200, 160, 70, 70]);
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="at_risk_students_' . date('Ymd') . '.pdf"');
    header('Content-Length: ' . strlen($pdf));
    echo $pdf;
    exit;
}

require_once __DIR__ . '/../includes/teacher_header.php';
?>
<div class="card p-4 mb-3 sp-greeting-card">
    <h4 class="mb-1"><?php echo htmlspecialchars($greeting); ?>, Prof. <?php echo htmlspecialchars($me['first_name'] ?? 'Teacher'); ?>! 👋</h4>
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
                                    <?php if ($row['is_makeup']): ?><span class="badge bg-warning text-dark ms-1">Makeup</span><?php endif; ?>
                                </div>
                                <div class="sp-subject-body">
                                    <div class="sp-subject-teacher">
                                        <div class="sp-subject-meta">
                                            <span class="sp-subject-prof"><?php echo htmlspecialchars($row['room_name']); ?></span>
                                            <?php echo $row['is_makeup'] ? 'Makeup Class' : htmlspecialchars($row['day_of_week']); ?> | <?php echo formatTime($row['start_time']); ?>
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

<h4 class="mt-4 mb-3">Analytics</h4>
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
                <h6 class="mb-0">Attendance Rate by Class</h6>
                <?php if ($classData): ?>
                    <button type="button" class="btn btn-sm sp-export-btn" id="spExportClassPdf" title="Export as PDF"><i class="fa-solid fa-file-pdf"></i></button>
                <?php endif; ?>
            </div>
            <?php if ($classData): ?>
                <div style="height:240px;"><canvas id="classChart"></canvas></div>
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
    <div class="col-lg-12">
        <div class="card p-3 h-100">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h6 class="mb-0">At-Risk Students <span class="text-muted small fw-normal">(most late/absent in your classes)</span></h6>
                <?php if ($atRiskResult->num_rows > 0): ?>
                    <form method="post" class="d-inline-block">
                        <input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>">
                        <input type="hidden" name="action" value="export_at_risk_pdf">
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
                            <tr><th>Student</th><th>Room</th><th class="text-center">Absent</th><th class="text-center">Late</th></tr>
                        </thead>
                        <tbody>
                            <?php while ($row = $atRiskResult->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($row['first_name'] . ' ' . $row['last_name']); ?></td>
                                    <td class="text-muted small"><?php echo htmlspecialchars($row['room_name'] ?: '-'); ?></td>
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

<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.0/chart.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
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

    <?php if ($classData): ?>
    var classChart = new Chart(document.getElementById('classChart'), {
        type: 'bar',
        data: {
            labels: <?php echo json_encode($classLabels); ?>,
            datasets: [{ label: 'Attendance Rate', data: <?php echo json_encode($classData); ?>, backgroundColor: greenShadesForPercent(<?php echo json_encode($classData); ?>), borderRadius: 6, maxBarThickness: 42 }]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: { y: { min: 0, max: 100, ticks: { callback: function (v) { return v + '%'; } } } }
        }
    });
    spWireExportBtn('spExportClassPdf', classChart, 'Attendance Rate by Class');
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
});
</script>
<?php require_once __DIR__ . '/../includes/teacher_footer.php'; ?>
