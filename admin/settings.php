<?php
require_once __DIR__ . '/../includes/functions.php';
requireRole(['admin', 'superadmin']);
$pageTitle = 'Settings';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        flash('Invalid request.', 'danger');
        redirect('settings.php');
    }
    $fields = [
        'school_name' => sanitize($_POST['school_name'] ?? ''),
        'timezone' => sanitize($_POST['timezone'] ?? ''),
        'attendance_time' => sanitize($_POST['attendance_time'] ?? ''),
        'late_time' => sanitize($_POST['late_time'] ?? ''),
        'school_year' => sanitize($_POST['school_year'] ?? ''),
        'semester' => sanitize($_POST['semester'] ?? '')
    ];
    foreach ($fields as $name => $value) {
        $stmt = $mysqli->prepare('INSERT INTO settings (`name`,`value`) VALUES (?, ?) ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)');
        $stmt->bind_param('ss', $name, $value);
        $stmt->execute();
        $stmt->close();
    }
    if (!empty($_FILES['logo']['tmp_name'])) {
        $ext = pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION);
        $allowed = ['png','jpg','jpeg'];
        if (in_array(strtolower($ext), $allowed)) {
            $logoPath = 'uploads/school_logo.' . $ext;
            move_uploaded_file($_FILES['logo']['tmp_name'], __DIR__ . '/../' . $logoPath);
            $stmt = $mysqli->prepare('INSERT INTO settings (`name`,`value`) VALUES (?, ?) ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)');
            $key = 'logo';
            $stmt->bind_param('ss', $key, $logoPath);
            $stmt->execute();
            $stmt->close();
        }
    }
    flash('Settings updated.', 'success');
    redirect('settings.php');
}

$schoolName = getSetting('school_name', 'Attendance Management System');
$timezone = getSetting('timezone', date_default_timezone_get());
$attendanceTime = getSetting('attendance_time', '07:30');
$lateTime = getSetting('late_time', '08:00');
$schoolYear = getSetting('school_year', '2025-2026');
$semester = getSetting('semester', '1st Semester');
$logo = getSetting('logo', '');
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/admin_nav.php';
?>
<div class="card rounded-4 shadow-sm p-4">
    <h4>System Settings</h4>
    <form method="post" enctype="multipart/form-data" class="row g-3 mt-3">
        <input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>">
        <div class="col-md-6">
            <label class="form-label">School Name</label>
            <input type="text" class="form-control" name="school_name" value="<?php echo htmlspecialchars($schoolName); ?>" required>
        </div>
        <div class="col-md-6">
            <label class="form-label">Timezone</label>
            <input type="text" class="form-control" name="timezone" value="<?php echo htmlspecialchars($timezone); ?>" required>
        </div>
        <div class="col-md-4">
            <label class="form-label">Attendance Time</label>
            <input type="time" class="form-control" name="attendance_time" value="<?php echo htmlspecialchars($attendanceTime); ?>" required>
        </div>
        <div class="col-md-4">
            <label class="form-label">Late Time</label>
            <input type="time" class="form-control" name="late_time" value="<?php echo htmlspecialchars($lateTime); ?>" required>
        </div>
        <div class="col-md-4">
            <label class="form-label">School Year</label>
            <input type="text" class="form-control" name="school_year" value="<?php echo htmlspecialchars($schoolYear); ?>" required>
        </div>
        <div class="col-md-6">
            <label class="form-label">Semester</label>
            <input type="text" class="form-control" name="semester" value="<?php echo htmlspecialchars($semester); ?>" required>
        </div>
        <div class="col-md-6">
            <label class="form-label">School Logo</label>
            <input type="file" class="form-control" name="logo" accept="image/*">
            <?php if ($logo): ?>
                <div class="mt-2">
                    <img src="../<?php echo htmlspecialchars($logo); ?>" alt="Logo" class="img-fluid rounded-4" style="max-height:100px;">
                </div>
            <?php endif; ?>
        </div>
        <div class="col-12 text-end">
            <button class="btn btn-primary">Save Settings</button>
        </div>
    </form>
</div>
</div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>