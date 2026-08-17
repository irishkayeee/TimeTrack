<?php
require_once __DIR__ . '/../includes/functions.php';
requireRole(['admin', 'superadmin', 'teacher']);
$pageTitle = 'QR Generator';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $studentId = intval($_POST['student_id'] ?? 0);
    if ($studentId) {
        redirect('qr-generator.php?student_id=' . $studentId);
    }
}
$students = $mysqli->query('SELECT id, student_id, first_name, last_name, qr_code FROM students ORDER BY first_name');
$selectedStudent = null;
$qrText = '';
$qrFilename = '';
$qrFileVersion = time();
$qrImageExists = false;
$qrGenerationError = '';
$qrDirectory = __DIR__ . '/../qrcodes/';

if (!empty($_GET['student_id'])) {
    $sid = intval($_GET['student_id']);
    $stmt = $mysqli->prepare('SELECT student_id, qr_code, first_name, last_name FROM students WHERE id = ? LIMIT 1');
    $stmt->bind_param('i', $sid);
    $stmt->execute();
    $stmt->bind_result($studentCode, $qrCode, $firstName, $lastName);
    if ($stmt->fetch()) {
        $selectedStudent = ['id' => $sid, 'student_id' => $studentCode, 'name' => $firstName . ' ' . $lastName, 'qr_code' => $qrCode];
        $qrText = $qrCode ?: $studentCode;
        $qrFilename = 'qr_' . preg_replace('/[^A-Za-z0-9_-]/', '_', $qrText) . '.png';
        $qrFilePath = $qrDirectory . $qrFilename;

        if (!ensureQrDirectory($qrDirectory)) {
            $qrGenerationError = 'Unable to create or write to the qrcodes folder. Please check folder permissions.';
        } else {
            if (!file_exists($qrFilePath) || !isValidPngFile($qrFilePath)) {
                if (!generateStudentQrFile($qrText, $qrFilePath)) {
                    $qrGenerationError = 'Unable to generate a valid QR image. Please verify the QR library and folder permissions.';
                }
            }
        }

        if (empty($qrGenerationError) && file_exists($qrFilePath) && isValidPngFile($qrFilePath)) {
            $qrImageExists = true;
            $qrFileVersion = filemtime($qrFilePath) ?: time();
        }
    }
    $stmt->close();
}

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/admin_nav.php';
?>
<div class="card rounded-4 shadow-sm p-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h4>QR Code Generator</h4>
            <p class="text-muted">Generate and download student QR codes for attendance scanning.</p>
        </div>
    </div>
    <form method="post" class="row g-3 align-items-end mb-4">
        <input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>">
        <div class="col-md-8">
            <label class="form-label">Select Student</label>
            <select class="form-select" name="student_id" required>
                <option value="">Choose Student</option>
                <?php while ($student = $students->fetch_assoc()): ?>
                    <option value="<?php echo $student['id']; ?>" <?php echo $selectedStudent && $selectedStudent['id'] == $student['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($student['student_id'] . ' - ' . $student['first_name'] . ' ' . $student['last_name']); ?></option>
                <?php endwhile; ?>
            </select>
        </div>
        <div class="col-md-4">
            <button class="btn btn-primary w-100">Load Student</button>
        </div>
    </form>
    <?php if ($selectedStudent): ?>
        <div class="row g-4 align-items-center">
            <div class="col-md-6">
                <div class="card rounded-4 p-4 text-center">
                    <h5><?php echo htmlspecialchars($selectedStudent['name']); ?></h5>
                    <p class="text-muted mb-3">ID: <?php echo htmlspecialchars($selectedStudent['student_id']); ?></p>
                    <?php if ($qrImageExists): ?>
                        <img id="qrImage" src="../qrcodes/<?php echo urlencode($qrFilename); ?>?v=<?php echo $qrFileVersion; ?>" alt="Student QR Code" class="img-fluid rounded-4 border shadow-sm">
                    <?php else: ?>
                        <div class="alert alert-warning">QR preview unavailable. <?php echo htmlspecialchars($qrGenerationError); ?></div>
                    <?php endif; ?>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card rounded-4 p-4">
                    <h5>Download & Print</h5>
                    <p class="text-muted">Use this QR code for student attendance. Save or print the image for easy scanning.</p>
                    <?php if ($qrImageExists): ?>
                        <a href="../qrcodes/<?php echo urlencode($qrFilename); ?>" class="btn btn-success mb-2" download="<?php echo htmlspecialchars($qrFilename); ?>">Download QR</a>
                        <button class="btn btn-outline-primary" type="button" onclick="window.open('../qrcodes/<?php echo urlencode($qrFilename); ?>','_blank','noopener').print();">Print QR</button>
                    <?php else: ?>
                        <button class="btn btn-success mb-2" disabled>Download QR</button>
                        <button class="btn btn-outline-primary" type="button" disabled>Print QR</button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    <?php else: ?>
        <div class="alert alert-info">Select a student to generate a QR code.</div>
    <?php endif; ?>
</div>
</div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>