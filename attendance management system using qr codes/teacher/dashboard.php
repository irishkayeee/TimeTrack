<?php
require_once __DIR__ . '/../includes/functions.php';
requireRole(['teacher']);
$pageTitle = 'Teacher Dashboard';
require_once __DIR__ . '/../includes/header.php';
$totalStudents = $mysqli->query('SELECT COUNT(*) FROM students')->fetch_row()[0];
$todayAttendance = $mysqli->query("SELECT COUNT(*) FROM attendance WHERE date = CURDATE()")->fetch_row()[0];
$myAssigned = $mysqli->query('SELECT COUNT(*) FROM rooms')->fetch_row()[0];
?>
<div class="container-fluid py-4">
    <div class="row g-3">
        <div class="col-md-4">
            <div class="card shadow-sm rounded-4 p-3">
                <h6>Assigned Rooms</h6>
                <h2><?php echo $myAssigned; ?></h2>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card shadow-sm rounded-4 p-3">
                <h6>Today Attendance</h6>
                <h2><?php echo $todayAttendance; ?></h2>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card shadow-sm rounded-4 p-3">
                <h6>Total Students</h6>
                <h2><?php echo $totalStudents; ?></h2>
            </div>
        </div>
    </div>
    <div class="row g-3 mt-3">
        <div class="col-12">
            <div class="card rounded-4 shadow-sm p-3">
                <h5>Quick Actions</h5>
                <div class="d-flex flex-wrap gap-2">
                    <a href="attendance.php" class="btn btn-primary">Manage Attendance</a>
                    <a href="../admin/qr-generator.php" class="btn btn-outline-primary">QR Generator</a>
                </div>
            </div>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>