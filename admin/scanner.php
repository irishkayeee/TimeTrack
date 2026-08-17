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
    $stmt = $mysqli->prepare("SELECT sub.*, sec.section_name FROM subjects sub JOIN sections sec ON sub.section_id = sec.id WHERE sub.id = ? AND sub.status = 'active'" . ($user['role'] === 'teacher' ? ' AND sub.teacher_id = ?' : '') . ' LIMIT 1');
    if ($user['role'] === 'teacher') {
        $stmt->bind_param('ii', $subjectId, $teacherId);
    } else {
        $stmt->bind_param('i', $subjectId);
    }
    $stmt->execute();
    $activeSubject = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/admin_nav.php';
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
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <h4>QR Scanner</h4>
                <p class="text-muted mb-0">Scanning for: <strong><?php echo htmlspecialchars($activeSubject['name']); ?></strong> &mdash; <?php echo htmlspecialchars($activeSubject['section_name']); ?> &mdash; <?php echo htmlspecialchars($activeSubject['day_of_week']); ?> <?php echo formatTime($activeSubject['start_time']); ?></p>
            </div>
            <div class="d-flex gap-2">
                <a href="scanner.php" class="btn btn-outline-secondary">Change Subject</a>
                <button id="startScan" class="btn btn-primary">Start Scanner</button>
            </div>
        </div>
        <div id="reader"></div>
        <div class="mt-4">
            <div id="scanResult" class="card rounded-4 p-3 d-none"></div>
        </div>
    <?php endif; ?>
</div>
</div>
</div>
<?php if ($activeSubject): ?>
<script>
const currentSubjectId = <?php echo (int) $activeSubject['id']; ?>;
let scanner;
let isProcessingScan = false;
let lastScannedValue = null;
let lastScannedAt = 0;
const scanResult = document.getElementById('scanResult');
const startScanBtn = document.getElementById('startScan');

function setScanButtonState(isScanning) {
    startScanBtn.textContent = isScanning ? 'Scanning...' : 'Start Scanner';
    startScanBtn.disabled = isScanning;
}

function showScanResult(data, statusLabel) {
    const badgeMap = {
        present: '<span class="badge bg-success">Approved</span>',
        late: '<span class="badge bg-warning">Approved (Late)</span>',
        absent: '<span class="badge bg-danger">Recorded (Absent)</span>',
        duplicate: '<span class="badge bg-warning">Duplicate</span>'
    };
    const statusBadge = badgeMap[statusLabel] || '<span class="badge bg-danger">Error</span>';
    const photoHtml = data && data.photo
        ? `<img src="../${data.photo}" class="rounded-circle mb-2" style="width:96px;height:96px;object-fit:cover;">`
        : '<i class="fa-solid fa-circle-user fa-4x text-secondary mb-2"></i>';

    scanResult.innerHTML = `
        <div class="text-center">${photoHtml}</div>
        <div class="mb-3 text-center"><strong>Result:</strong> ${statusBadge}</div>
        ${data ? `
            <p><strong>Name:</strong> ${data.first_name} ${data.last_name}</p>
            <p><strong>ID:</strong> ${data.student_id}</p>
            <p><strong>Course:</strong> ${data.course_code || 'N/A'}</p>
            <p><strong>Section:</strong> ${data.section_name || 'N/A'}</p>
        ` : ''}
    `;
    scanResult.classList.remove('d-none');
}

function showError(message) {
    scanResult.innerHTML = `<div class="alert alert-danger">${message}</div>`;
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
    setScanButtonState(false);
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
            if (!scanner) {
                startScanner();
            }
        }, 2000);
    }
}

async function startScanner() {
    setScanButtonState(true);
    scanResult.classList.add('d-none');
    scanResult.innerHTML = '';
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
    } catch (e) {
        setScanButtonState(false);
        showError('Unable to start camera: ' + e.message);
    }
}

document.addEventListener('DOMContentLoaded', () => {
    startScanner();
});

startScanBtn.addEventListener('click', async () => {
    await startScanner();
});
</script>
<?php endif; ?>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
