<?php
require_once __DIR__ . '/../includes/functions.php';
requireRole(['admin', 'superadmin']);
$pageTitle = 'Dashboard';
$pageSubtitle = 'Overview of attendance across the school.';

$totalStudents = $mysqli->query('SELECT COUNT(*) FROM students')->fetch_row()[0];
$presentToday = $mysqli->query("SELECT COUNT(*) FROM attendance WHERE date = CURDATE() AND status = 'present'")->fetch_row()[0];
$absentToday = $mysqli->query("SELECT COUNT(*) FROM attendance WHERE date = CURDATE() AND status = 'absent'")->fetch_row()[0];
$lateToday = $mysqli->query("SELECT COUNT(*) FROM attendance WHERE date = CURDATE() AND status = 'late'")->fetch_row()[0];
$teachersCount = $mysqli->query('SELECT COUNT(*) FROM teachers')->fetch_row()[0];
$coursesCount = $mysqli->query('SELECT COUNT(*) FROM courses')->fetch_row()[0];
$sectionsCount = $mysqli->query('SELECT COUNT(*) FROM sections')->fetch_row()[0];
$attendanceToday = $mysqli->query("SELECT COUNT(*) FROM attendance WHERE date = CURDATE()")->fetch_row()[0];
$recentAttendance = $mysqli->query("SELECT a.*, s.first_name, s.last_name, c.code AS course_code, sec.section_name FROM attendance a LEFT JOIN students s ON a.student_id = s.id LEFT JOIN courses c ON a.course_id = c.id LEFT JOIN sections sec ON a.section_id = sec.id ORDER BY a.created_at DESC LIMIT 8");

require_once __DIR__ . '/../includes/admin_header.php';
?>
<div class="row g-3">
    <div class="col-md-3">
        <div class="card p-3">
            <h6 class="text-muted small text-uppercase mb-2">Total Students</h6>
            <h2 class="mb-0"><?php echo $totalStudents; ?></h2>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card p-3">
            <h6 class="text-muted small text-uppercase mb-2">Present Today</h6>
            <h2 class="mb-0 text-success"><?php echo $presentToday; ?></h2>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card p-3">
            <h6 class="text-muted small text-uppercase mb-2">Absent Today</h6>
            <h2 class="mb-0 text-danger"><?php echo $absentToday; ?></h2>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card p-3">
            <h6 class="text-muted small text-uppercase mb-2">Late Today</h6>
            <h2 class="mb-0 text-warning"><?php echo $lateToday; ?></h2>
        </div>
    </div>
</div>
<div class="row g-3 mt-1">
    <div class="col-12">
        <div class="card p-4">
            <h6 class="mb-3"><i class="fa-solid fa-chart-simple me-2 text-muted"></i>Dashboard Overview</h6>
            <ul class="list-group list-group-flush">
                <li class="list-group-item d-flex justify-content-between align-items-center">Teachers<span class="badge bg-success rounded-pill"><?php echo $teachersCount; ?></span></li>
                <li class="list-group-item d-flex justify-content-between align-items-center">Courses<span class="badge bg-success rounded-pill"><?php echo $coursesCount; ?></span></li>
                <li class="list-group-item d-flex justify-content-between align-items-center">Sections<span class="badge bg-success rounded-pill"><?php echo $sectionsCount; ?></span></li>
                <li class="list-group-item d-flex justify-content-between align-items-center">Attendance Today<span class="badge bg-success rounded-pill"><?php echo $attendanceToday; ?></span></li>
            </ul>
        </div>
    </div>
</div>
<div class="row g-3 mt-1">
    <div class="col-12">
        <div class="card p-4">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h6 class="mb-0"><i class="fa-solid fa-clock-rotate-left me-2 text-muted"></i>Recent Attendance</h6>
                <a href="attendance.php" class="small text-decoration-none">View all <i class="fa-solid fa-arrow-right ms-1"></i></a>
            </div>
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Student</th>
                            <th>Course</th>
                            <th>Section</th>
                            <th>Status</th>
                            <th>Time</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($recentAttendance->num_rows === 0): ?>
                            <tr><td colspan="5" class="text-muted small">No attendance recorded yet.</td></tr>
                        <?php else: ?>
                            <?php while ($row = $recentAttendance->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($row['first_name'] . ' ' . $row['last_name']); ?></td>
                                    <td><?php echo htmlspecialchars($row['course_code']); ?></td>
                                    <td><?php echo htmlspecialchars($row['section_name']); ?></td>
                                    <td><?php echo badgeStatus($row['status']); ?></td>
                                    <td><?php echo date('M j, h:i A', strtotime($row['created_at'])); ?></td>
                                </tr>
                            <?php endwhile; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>
