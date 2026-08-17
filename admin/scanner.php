<?php
require_once __DIR__ . '/../includes/functions.php';
requireRole(['admin', 'superadmin', 'teacher']);
$pageTitle = 'QR Scanner';

$user = currentUser();
if ($user['role'] === 'teacher') {
    $teacherId = currentTeacherId();
    if ($teacherId === false) {
        flash('Your teacher profile is not set up. Contact an administrator.', 'danger');
        redirect('../dashboard.php');
    }
    $subjectsStmt = $mysqli->prepare("SELECT sub.*, sec.section_name FROM subjects sub JOIN sections sec ON sub.section_id = sec.id WHERE sub.status = 'active' AND sub.teacher_id = ? ORDER BY sub.name");
    $subjectsStmt->bind_param('i', $teacherId);
    $subjectsStmt->execute();
    $subjects = $subjectsStmt->get_result();
} else {
    $subjects = $mysqli->query("SELECT sub.*, sec.section_name FROM subjects sub JOIN sections sec ON sub.section_id = sec.id WHERE sub.status = 'active' ORDER BY sub.name");
}

$subjectId = intval($_GET['subject_id'] ?? 0);
$activeSubject = null;
if ($subjectId) {
    $stmt = $mysqli->prepare("SELECT sub.*, sec.section_name, sec.year_level, c.code AS course_code, c.name AS course_name
        FROM subjects sub
        JOIN sections sec ON sub.section_id = sec.id
        JOIN courses c ON sec.course_id = c.id
        WHERE sub.id = ? AND sub.status = 'active'" . ($user['role'] === 'teacher' ? ' AND sub.teacher_id = ?' : '') . ' LIMIT 1');
    if ($user['role'] === 'teacher') {
        $stmt->bind_param('ii', $subjectId, $teacherId);
    } else {
        $stmt->bind_param('i', $subjectId);
    }
    $stmt->execute();
    $activeSubject = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

$scannedRows = [];
$totalStudents = 0;
if ($activeSubject) {
    $scannedStmt = $mysqli->prepare("SELECT a.status, a.time, s.first_name, s.last_name, s.student_id, s.photo
        FROM attendance a
        JOIN students s ON a.student_id = s.id
        WHERE a.subject_id = ? AND a.date = CURDATE()
        ORDER BY a.time DESC");
    $scannedStmt->bind_param('i', $activeSubject['id']);
    $scannedStmt->execute();
    $scannedRows = $scannedStmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $scannedStmt->close();

    foreach ($scannedRows as &$r) {
        $r['lateMinutes'] = null;
        if ($r['status'] === 'late') {
            $r['lateMinutes'] = max(0, round((strtotime($r['time']) - strtotime($activeSubject['start_time'])) / 60));
        }
    }
    unset($r);

    $roster = getLiveRosterForSubject($mysqli, $activeSubject['id']);
    $totalStudents = count($roster['rows']);
}
$scannedTotal = count($scannedRows);
$absentCutoff = intval(getSetting('absent_cutoff_minutes', 20));

$isTeacherView = $user['role'] === 'teacher';
if ($isTeacherView) {
    $pageSubtitle = $activeSubject ? 'Scanning for ' . $activeSubject['name'] : 'Select a class to scan attendance for.';
    require_once __DIR__ . '/../includes/teacher_header.php';
} else {
    require_once __DIR__ . '/../includes/header.php';
    require_once __DIR__ . '/../includes/admin_nav.php';
}
?>
<div class="card rounded-4 shadow-sm p-4">
    <?php if (!$activeSubject): ?>
        <h4>QR Scanner</h4>
        <p class="text-muted">Select the subject/session you're scanning attendance for.</p>
        <div class="row g-3">
            <?php if ($subjects->num_rows === 0): ?>
                <div class="col-12"><div class="alert alert-info">No active subjects available. Ask an admin to create one.</div></div>
            <?php endif; ?>
            <?php while ($row = $subjects->fetch_assoc()): ?>
                <div class="col-md-4">
                    <a href="scanner.php?subject_id=<?php echo $row['id']; ?>" class="text-decoration-none">
                        <div class="card rounded-4 p-3 h-100 border">
                            <h6 class="mb-1"><?php echo htmlspecialchars($row['name']); ?> (<?php echo htmlspecialchars($row['code']); ?>)</h6>
                            <p class="text-muted mb-0"><?php echo htmlspecialchars($row['section_name']); ?> &middot; <?php echo htmlspecialchars($row['day_of_week']); ?> <?php echo formatTime($row['start_time']); ?></p>
                        </div>
                    </a>
                </div>
            <?php endwhile; ?>
        </div>
    <?php else: ?>
        <div class="d-flex justify-content-between align-items-start mb-4 pb-3 flex-wrap gap-3" style="border-bottom: 1px solid #eef1ef;">
            <div class="d-flex gap-3">
                <div class="sp-mc-icon-box" style="--mc-color: var(--lp-mid-green);"><i class="fa-solid fa-qrcode"></i></div>
                <div>
                    <p class="text-muted mb-2"><strong><?php echo htmlspecialchars($activeSubject['name']); ?></strong> &mdash; <?php echo htmlspecialchars($activeSubject['day_of_week']); ?> <?php echo formatTime($activeSubject['start_time']); ?><?php echo $activeSubject['end_time'] ? ' - ' . formatTime($activeSubject['end_time']) : ''; ?></p>
                    <div class="d-flex flex-wrap gap-2">
                        <span class="sp-shd-pill"><i class="fa-solid fa-graduation-cap"></i> <?php echo htmlspecialchars($activeSubject['course_code'] . ' - ' . $activeSubject['course_name']); ?></span>
                        <span class="sp-shd-pill"><i class="fa-solid fa-user-group"></i> <?php echo htmlspecialchars($activeSubject['year_level'] . ' - ' . $activeSubject['section_name']); ?></span>
                    </div>
                </div>
            </div>
            <div class="d-flex align-items-start gap-3">
                <div class="sp-clock-widget text-center">
                    <div class="sp-clock-time" id="scanClockTime">--:--:-- --</div>
                    <div class="sp-clock-date" id="scanClockDate">Loading...</div>
                </div>
                <a href="<?php echo $isTeacherView ? '../teacher/subjects.php' : 'scanner.php'; ?>" class="btn btn-sm btn-outline-secondary rounded-circle" title="Close" style="width:36px;height:36px;"><i class="fa-solid fa-xmark"></i></a>
            </div>
        </div>
        <div class="row g-5">
            <div class="col-lg-5">
                <h6 class="text-uppercase small fw-bold mb-3">Scan Student QR Code</h6>
                <div class="sp-scan-frame">
                    <div id="reader"></div>
                    <div class="sp-scan-corner sp-scan-corner-tl"></div>
                    <div class="sp-scan-corner sp-scan-corner-tr"></div>
                    <div class="sp-scan-corner sp-scan-corner-bl"></div>
                    <div class="sp-scan-corner sp-scan-corner-br"></div>
                    <label class="sp-camera-toggle-pill" title="Turn camera on/off">
                        <span class="sp-switch">
                            <input type="checkbox" id="cameraToggleSwitch" checked>
                            <span class="sp-switch-slider"></span>
                        </span>
                        <span id="cameraStatusText">Camera ON</span>
                    </label>
                </div>
                <div class="mt-3">
                    <div id="scanResult" class="d-none"></div>
                </div>
                <div class="d-flex flex-nowrap gap-2 mt-3" style="overflow-x: auto;">
                    <div class="sp-policy-item mb-0">
                        <span class="sp-policy-dot bg-success"></span>
                        <span>On time: <strong>Present</strong></span>
                    </div>
                    <div class="sp-policy-item mb-0">
                        <span class="sp-policy-dot bg-warning"></span>
                        <span>1&ndash;<?php echo $absentCutoff - 1; ?> min: <strong>Late</strong></span>
                    </div>
                    <div class="sp-policy-item mb-0">
                        <span class="sp-policy-dot bg-danger"></span>
                        <span><?php echo $absentCutoff; ?>+ min: <strong>Absent</strong></span>
                    </div>
                </div>
            </div>
            <div class="col-lg-7">
                <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                    <h6 class="text-uppercase small fw-bold mb-0">Already Scanned (<span id="scannedCount"><?php echo $scannedTotal; ?></span> / <?php echo $totalStudents; ?>)</h6>
                    <div class="d-flex gap-2">
                        <input type="text" class="form-control form-control-sm" id="scannedSearch" placeholder="Search student..." style="width:170px;">
                        <select class="form-select form-select-sm" id="scannedStatusFilter" style="width:auto;">
                            <option value="all">All Status</option>
                            <option value="present">Present</option>
                            <option value="late">Late</option>
                            <option value="absent">Absent</option>
                        </select>
                    </div>
                </div>
                <div class="table-responsive" style="max-height: 420px; overflow-y: auto;">
                    <table class="table table-sm align-middle">
                        <thead class="table-light">
                            <tr><th></th><th>Name</th><th>ID</th><th>Status</th><th>Time</th></tr>
                        </thead>
                        <tbody id="scannedTableBody">
                            <?php foreach ($scannedRows as $r): ?>
                                <tr data-name="<?php echo htmlspecialchars(strtolower($r['first_name'] . ' ' . $r['last_name'] . ' ' . $r['student_id'])); ?>" data-status="<?php echo htmlspecialchars($r['status']); ?>">
                                    <td>
                                        <?php if ($r['photo']): ?>
                                            <img src="../<?php echo htmlspecialchars($r['photo']); ?>" class="rounded-circle" style="width:28px;height:28px;object-fit:cover;">
                                        <?php else: ?>
                                            <i class="fa-solid fa-circle-user text-secondary"></i>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($r['first_name'] . ' ' . $r['last_name']); ?></td>
                                    <td><?php echo htmlspecialchars($r['student_id']); ?></td>
                                    <td><?php echo badgeStatus($r['status']); ?><?php if ($r['lateMinutes'] !== null): ?> <span class="text-muted small">(<?php echo $r['lateMinutes']; ?> min)</span><?php endif; ?></td>
                                    <td><?php echo formatTime($r['time']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <p class="text-muted small text-center mb-0<?php echo $scannedRows ? ' d-none' : ''; ?>" id="scannedEmptyMsg">No students scanned yet.</p>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>
<?php if (!$isTeacherView): ?>
</div>
</div>
<?php endif; ?>
<?php if ($activeSubject): ?>
<script src="https://cdn.jsdelivr.net/npm/jsqr@1.4.0/dist/jsQR.js"></script>
<script>
function scanUpdateClock() {
    var now = new Date();
    var timeEl = document.getElementById('scanClockTime');
    var dateEl = document.getElementById('scanClockDate');
    if (timeEl) {
        timeEl.textContent = now.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true });
    }
    if (dateEl) {
        dateEl.textContent = now.toLocaleDateString('en-US', { weekday: 'long', month: 'long', day: 'numeric', year: 'numeric' });
    }
}
scanUpdateClock();
setInterval(scanUpdateClock, 1000);

const currentSubjectId = <?php echo (int) $activeSubject['id']; ?>;
let scanner;
let isProcessingScan = false;
let lastScannedValue = null;
let lastScannedAt = 0;
const scanResult = document.getElementById('scanResult');
const scannedTableBody = document.getElementById('scannedTableBody');
const scannedEmptyMsg = document.getElementById('scannedEmptyMsg');
const scannedCountEl = document.getElementById('scannedCount');
const scannedSearch = document.getElementById('scannedSearch');
const scannedStatusFilter = document.getElementById('scannedStatusFilter');
let scannedCount = <?php echo $scannedTotal; ?>;

function applyScannedFilters() {
    const term = scannedSearch ? scannedSearch.value.trim().toLowerCase() : '';
    const status = scannedStatusFilter ? scannedStatusFilter.value : 'all';
    let visibleCount = 0;
    scannedTableBody.querySelectorAll('tr').forEach(function (row) {
        const matchesTerm = !term || row.getAttribute('data-name').includes(term);
        const matchesStatus = status === 'all' || row.getAttribute('data-status') === status;
        const visible = matchesTerm && matchesStatus;
        row.style.display = visible ? '' : 'none';
        if (visible) visibleCount++;
    });
    if (scannedEmptyMsg) {
        scannedEmptyMsg.classList.toggle('d-none', visibleCount !== 0);
        scannedEmptyMsg.textContent = scannedCount === 0 ? 'No students scanned yet.' : 'No matching students.';
    }
}
if (scannedSearch) scannedSearch.addEventListener('input', applyScannedFilters);
if (scannedStatusFilter) scannedStatusFilter.addEventListener('change', applyScannedFilters);

function addScannedRow(data, statusLabel) {
    if (!scannedTableBody || !data) {
        return;
    }
    const badgeMap = {
        present: '<span class="badge bg-success">Present</span>',
        late: '<span class="badge bg-warning">Late</span>',
        absent: '<span class="badge bg-danger">Absent</span>'
    };
    const photoHtml = data.photo
        ? `<img src="../${data.photo}" class="rounded-circle" style="width:28px;height:28px;object-fit:cover;">`
        : '<i class="fa-solid fa-circle-user text-secondary"></i>';
    const now = new Date();
    const timeLabel = now.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', hour12: true });
    const row = document.createElement('tr');
    row.setAttribute('data-name', (data.first_name + ' ' + data.last_name + ' ' + data.student_id).toLowerCase());
    row.setAttribute('data-status', statusLabel);
    row.innerHTML = `
        <td>${photoHtml}</td>
        <td>${data.first_name} ${data.last_name}</td>
        <td>${data.student_id}</td>
        <td>${badgeMap[statusLabel] || '<span class="badge bg-secondary">' + statusLabel + '</span>'}</td>
        <td>${timeLabel}</td>
    `;
    scannedTableBody.prepend(row);
    scannedCount++;
    if (scannedCountEl) {
        scannedCountEl.textContent = scannedCount;
    }
    applyScannedFilters();
}

function showScanResult(data, statusLabel) {
    const badgeMap = {
        present: '<span class="badge bg-success">Present</span>',
        late: '<span class="badge bg-warning">Late</span>',
        absent: '<span class="badge bg-danger">Absent</span>',
        duplicate: '<span class="badge bg-warning">Duplicate</span>'
    };
    const statusBadge = badgeMap[statusLabel] || '<span class="badge bg-danger">Error</span>';
    const photoHtml = data && data.photo
        ? `<img src="../${data.photo}" class="rounded-circle" style="width:64px;height:64px;object-fit:cover;">`
        : '<i class="fa-solid fa-circle-user fa-3x text-secondary"></i>';
    const now = new Date();
    const timeLabel = now.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', hour12: true });
    const successLine = statusLabel === 'duplicate'
        ? '<div class="text-warning small mt-2"><i class="fa-solid fa-triangle-exclamation me-1"></i>Already recorded for this class today.</div>'
        : '<div class="text-success small mt-2"><i class="fa-solid fa-circle-check me-1"></i>Successfully recorded!</div>';

    scanResult.innerHTML = data ? `
        <div class="d-flex align-items-center gap-3 p-3 rounded-4" style="background: var(--lp-pale-green);">
            ${photoHtml}
            <div class="flex-grow-1">
                <div class="d-flex align-items-center gap-2">
                    <strong>${data.first_name} ${data.last_name}</strong> ${statusBadge}
                </div>
                <div class="text-muted small">ID: ${data.student_id}</div>
                <div class="text-muted small">${data.course_code || 'N/A'} &middot; ${data.section_name || 'N/A'}</div>
            </div>
            <div class="text-end small text-muted">
                <i class="fa-solid fa-clock me-1"></i>${timeLabel}
            </div>
        </div>
        ${successLine}
    ` : '';
    scanResult.classList.remove('d-none');
}

function showError(message) {
    scanResult.innerHTML = `<div class="alert alert-danger mb-0">${message}</div>`;
    scanResult.classList.remove('d-none');
}

function isDuplicateScan(qrValue) {
    const now = Date.now();
    if (qrValue === lastScannedValue && now - lastScannedAt < 5000) {
        return true;
    }
    lastScannedValue = qrValue;
    lastScannedAt = now;
    return false;
}

async function stopScanner() {
    if (scanner) {
        try {
            await scanner.stop();
            scanner.clear();
        } catch (error) {
            console.warn('Unable to fully stop scanner', error);
        }
        scanner = null;
    }
}

async function submitScan(qrValue) {
    try {
        const response = await fetch('../scan-attendance.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ qrValue, subjectId: currentSubjectId })
        });
        const result = await response.json();
        if (result.status === 'success') {
            showScanResult(result.student, result.statusLabel);
            addScannedRow(result.student, result.statusLabel);
            playSuccess();
            return true;
        }
        if (result.status === 'duplicate') {
            showScanResult(result.student, 'duplicate');
            playError();
            return true;
        }
        showError(result.message || 'Unable to save attendance.');
        playError();
    } catch (error) {
        showError('Scanner request failed: ' + error.message);
        playError();
    }
    return false;
}

async function handleScan(qrValue) {
    if (isProcessingScan) {
        return;
    }
    if (isDuplicateScan(qrValue)) {
        return;
    }
    isProcessingScan = true;
    const scanned = await submitScan(qrValue);
    isProcessingScan = false;
    if (scanned) {
        setTimeout(() => {
            if (!scanner && cameraOn) {
                startScanner();
            }
        }, 2000);
    }
}

const cameraStatusText = document.getElementById('cameraStatusText');
const cameraToggleSwitch = document.getElementById('cameraToggleSwitch');
let cameraOn = false;

function setCameraStatus(state, message) {
    if (!cameraStatusText) return;
    cameraStatusText.textContent = message;
}

async function startScanner() {
    scanResult.classList.add('d-none');
    scanResult.innerHTML = '';
    setCameraStatus('starting', 'Starting...');
    if (cameraToggleSwitch) cameraToggleSwitch.disabled = true;
    if (scanner) {
        await stopScanner();
    }
    scanner = new Html5Qrcode('reader');
    try {
        const cameras = await Html5Qrcode.getCameras();
        const cameraId = cameras && cameras.length ? cameras[0].id : null;
        const constraints = cameraId ? { deviceId: { exact: cameraId } } : { facingMode: 'environment' };
        await scanner.start(constraints, { fps: 10, qrbox: { width: 250, height: 250 } }, handleScan, (error) => {
            console.debug('QR scan error', error);
        });
        setCameraStatus('active', 'Camera ON');
        cameraOn = true;
        if (cameraToggleSwitch) cameraToggleSwitch.checked = true;
    } catch (e) {
        setCameraStatus('error', 'Unavailable');
        showError('Unable to start camera: ' + e.message);
        cameraOn = false;
        if (cameraToggleSwitch) cameraToggleSwitch.checked = false;
    }
    if (cameraToggleSwitch) cameraToggleSwitch.disabled = false;
}

if (cameraToggleSwitch) {
    cameraToggleSwitch.addEventListener('change', async () => {
        if (cameraToggleSwitch.checked) {
            await startScanner();
        } else {
            cameraToggleSwitch.disabled = true;
            await stopScanner();
            cameraOn = false;
            setCameraStatus('off', 'Camera OFF');
            cameraToggleSwitch.disabled = false;
        }
    });
}

document.addEventListener('DOMContentLoaded', () => {
    startScanner();
});
</script>
<?php endif; ?>
<?php require_once __DIR__ . '/../includes/' . ($isTeacherView ? 'teacher_footer.php' : 'footer.php'); ?>
