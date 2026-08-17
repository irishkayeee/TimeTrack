<?php
require_once __DIR__ . '/../includes/functions.php';
requireRole(['student']);
$pageTitle = 'QR Code';
$pageSubtitle = 'Show this QR code to your teacher to record your attendance.';

$studentDbId = currentStudentId();
if ($studentDbId === false) {
    flash('Your student profile is not set up. Contact an administrator.', 'danger');
    redirect('../dashboard.php');
}

$stmt = $mysqli->prepare('SELECT student_id, first_name, last_name, qr_code, course_id, section_id FROM students WHERE id = ?');
$stmt->bind_param('i', $studentDbId);
$stmt->execute();
$student = $stmt->get_result()->fetch_assoc();
$stmt->close();

$qrText = $student['qr_code'] ?: $student['student_id'];
$qrFilename = 'qr_' . preg_replace('/[^A-Za-z0-9_-]/', '_', $qrText) . '.png';
$qrDirectory = __DIR__ . '/../qrcodes/';
$qrFilePath = $qrDirectory . $qrFilename;
$qrImageExists = false;
if (ensureQrDirectory($qrDirectory)) {
    if (!file_exists($qrFilePath) || !isValidPngFile($qrFilePath)) {
        generateStudentQrFile($qrText, $qrFilePath);
    }
    $qrImageExists = file_exists($qrFilePath) && isValidPngFile($qrFilePath);
}
$qrFileVersion = $qrImageExists ? filemtime($qrFilePath) : time();

require_once __DIR__ . '/../includes/student_header.php';
?>
<div class="row justify-content-center">
    <div class="col-md-5">
        <div class="card p-4 text-center">
            <h5 class="mb-1"><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></h5>
            <p class="text-muted mb-3">ID: <?php echo htmlspecialchars($student['student_id']); ?></p>
            <?php if ($qrImageExists): ?>
                <img src="../qrcodes/<?php echo urlencode($qrFilename); ?>?v=<?php echo $qrFileVersion; ?>" alt="My QR Code" class="img-fluid rounded-4 border shadow-sm mb-3">
                <a href="../qrcodes/<?php echo urlencode($qrFilename); ?>" class="btn btn-primary" download="<?php echo htmlspecialchars($qrFilename); ?>"><i class="fa-solid fa-download me-1"></i> Download</a>
            <?php else: ?>
                <div class="alert alert-warning mb-0">QR code unavailable. Contact an administrator.</div>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/../includes/student_footer.php'; ?>
