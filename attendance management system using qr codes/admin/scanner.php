<?php
require_once __DIR__ . '/../includes/functions.php';
requireRole(['admin', 'superadmin', 'teacher']);
$pageTitle = 'QR Scanner';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/admin_nav.php';
?>
<div class="card rounded-4 shadow-sm p-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h4>QR Scanner</h4>
            <p class="text-muted">Use your webcam to scan student QR codes and save attendance instantly.</p>
        </div>
        <button id="startScan" class="btn btn-primary">Start Scanner</button>
    </div>
    <div id="reader"></div>
    <div class="mt-4">
        <div id="scanResult" class="card rounded-4 p-3 d-none"></div>
    </div>
</div>
</div>
</div>
<script>
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

function showScanResult(data, status) {
    const statusBadge = status === 'Success'
        ? '<span class="badge bg-success">Present</span>'
        : status === 'Duplicate'
            ? '<span class="badge bg-warning">Duplicate</span>'
            : '<span class="badge bg-danger">Error</span>';

    scanResult.innerHTML = `
        <div class="mb-3"><strong>Result:</strong> ${statusBadge}</div>
        ${data ? `
            <p><strong>Name:</strong> ${data.first_name} ${data.last_name}</p>
            <p><strong>ID:</strong> ${data.student_id}</p>
            <p><strong>Course:</strong> ${data.course_code || 'N/A'}</p>
            <p><strong>Room:</strong> ${data.room_name || 'N/A'}</p>
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
            body: JSON.stringify({ qrValue })
        });
        const result = await response.json();
        if (result.status === 'success') {
            showScanResult(result.student, 'Success');
            playSuccess();
            return true;
        }
        if (result.status === 'duplicate') {
            showScanResult(result.student, 'Duplicate');
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
<?php require_once __DIR__ . '/../includes/footer.php'; ?>