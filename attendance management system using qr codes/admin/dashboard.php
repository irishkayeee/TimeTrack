<?php
require_once __DIR__ . '/../includes/functions.php';
requireRole(['admin', 'superadmin']);
$pageTitle = 'Admin Dashboard';
require_once __DIR__ . '/../includes/header.php';

// Summary cards
$totalStudents = $mysqli->query('SELECT COUNT(*) FROM students')->fetch_row()[0];
$presentToday = $mysqli->query("SELECT COUNT(*) FROM attendance WHERE date = CURDATE() AND status = 'present'")->fetch_row()[0];
$absentToday = $mysqli->query("SELECT COUNT(*) FROM attendance WHERE date = CURDATE() AND status = 'absent'")->fetch_row()[0];
$lateToday = $mysqli->query("SELECT COUNT(*) FROM attendance WHERE date = CURDATE() AND status = 'late'")->fetch_row()[0];
$teachersCount = $mysqli->query('SELECT COUNT(*) FROM teachers')->fetch_row()[0];
$coursesCount = $mysqli->query('SELECT COUNT(*) FROM courses')->fetch_row()[0];
$sectionsCount = $mysqli->query('SELECT COUNT(*) FROM sections')->fetch_row()[0];
$attendanceToday = $mysqli->query("SELECT COUNT(*) FROM attendance WHERE date = CURDATE()") ->fetch_row()[0];
$recentAttendance = $mysqli->query("SELECT a.*, s.first_name, s.last_name, c.code AS course_code, sec.section_name FROM attendance a LEFT JOIN students s ON a.student_id = s.id LEFT JOIN courses c ON a.course_id = c.id LEFT JOIN sections sec ON a.section_id = sec.id ORDER BY a.created_at DESC LIMIT 8");
?>
<div class="container-fluid py-4">
    <div class="row g-3">
        <div class="col-md-3">
            <div class="card bg-gradient shadow-sm border-0 text-white p-3 rounded-4">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <h6>Total Students</h6>
                        <h2><?php echo $totalStudents; ?></h2>
                    </div>
                    <i class="fa-solid fa-user-graduate fa-2x"></i>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-success bg-opacity-75 shadow-sm border-0 text-white p-3 rounded-4">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <h6>Present Today</h6>
                        <h2><?php echo $presentToday; ?></h2>
                    </div>
                    <i class="fa-solid fa-check fa-2x"></i>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-danger bg-opacity-75 shadow-sm border-0 text-white p-3 rounded-4">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <h6>Absent Today</h6>
                        <h2><?php echo $absentToday; ?></h2>
                    </div>
                    <i class="fa-solid fa-user-slash fa-2x"></i>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-warning bg-opacity-75 shadow-sm border-0 text-white p-3 rounded-4">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <h6>Late Today</h6>
                        <h2><?php echo $lateToday; ?></h2>
                    </div>
                    <i class="fa-solid fa-clock fa-2x"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="row g-3 mt-3">
        <div class="col-lg-4">
            <div class="card rounded-4 shadow-sm p-3">
                <h5>Dashboard Overview</h5>
                <p class="text-muted">Quick access to attendance metrics and recent activity.</p>
                <ul class="list-group list-group-flush">
                    <li class="list-group-item d-flex justify-content-between align-items-center">Teachers<span class="badge bg-primary rounded-pill"><?php echo $teachersCount; ?></span></li>
                    <li class="list-group-item d-flex justify-content-between align-items-center">Courses<span class="badge bg-primary rounded-pill"><?php echo $coursesCount; ?></span></li>
                    <li class="list-group-item d-flex justify-content-between align-items-center">Sections<span class="badge bg-primary rounded-pill"><?php echo $sectionsCount; ?></span></li>
                    <li class="list-group-item d-flex justify-content-between align-items-center">Attendance Today<span class="badge bg-primary rounded-pill"><?php echo $attendanceToday; ?></span></li>
                </ul>
            </div>
        </div>
        <div class="col-lg-8">
            <div class="card rounded-4 shadow-sm p-3">
                <h5>Attendance Per Month</h5>
                <canvas id="attendanceMonthChart" height="220"></canvas>
            </div>
        </div>
    </div>
    <div class="row g-3 mt-3">
        <div class="col-lg-6">
            <div class="card rounded-4 shadow-sm p-3">
                <h5>Present vs Absent</h5>
                <canvas id="presentAbsentChart" height="220"></canvas>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card rounded-4 shadow-sm p-3">
                <h5>Weekly Attendance</h5>
                <canvas id="weeklyAttendanceChart" height="220"></canvas>
            </div>
        </div>
    </div>
    <div class="row g-3 mt-3">
        <div class="col-12">
            <div class="card rounded-4 shadow-sm p-3">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5>Recent Attendance</h5>
                    <a href="attendance.php" class="btn btn-sm btn-outline-primary">View all</a>
                </div>
                <div class="table-responsive">
                    <table class="table table-striped align-middle">
                        <thead class="table-light">
                            <tr>
                                <th scope="col">Student</th>
                                <th scope="col">Course</th>
                                <th scope="col">Section</th>
                                <th scope="col">Status</th>
                                <th scope="col">Time</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($row = $recentAttendance->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($row['first_name'] . ' ' . $row['last_name']); ?></td>
                                    <td><?php echo htmlspecialchars($row['course_code']); ?></td>
                                    <td><?php echo htmlspecialchars($row['section_name']); ?></td>
                                    <td><?php echo badgeStatus($row['status']); ?></td>
                                    <td><?php echo date('M j, h:i A', strtotime($row['created_at'])); ?></td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
<script>
const attendanceMonthData = {
    labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
    datasets: [{
        label: 'Attendance Count',
        backgroundColor: 'rgba(34, 120, 255, 0.3)',
        borderColor: '#2278ff',
        data: [<?php for ($m = 1; $m <= 12; $m++) { $count = $mysqli->query("SELECT COUNT(*) FROM attendance WHERE MONTH(date) = $m")->fetch_row()[0]; echo $count . ',';} ?>]
    }]
};
const presentAbsentData = {
    labels: ['Present', 'Absent', 'Late', 'Excused'],
    datasets: [{
        data: [<?php echo $presentToday; ?>, <?php echo $absentToday; ?>, <?php echo $lateToday; ?>, <?php echo $mysqli->query("SELECT COUNT(*) FROM attendance WHERE date = CURDATE() AND status = 'excused'")->fetch_row()[0]; ?>],
        backgroundColor: ['#198754', '#dc3545', '#ffc107', '#0dcaf0']
    }]
};
const weeklyAttendanceData = {
    labels: ['Sun','Mon','Tue','Wed','Thu','Fri','Sat'],
    datasets: [{
        label: 'Records',
        backgroundColor: 'rgba(13, 110, 253, 0.4)',
        borderColor: '#0d6efd',
        data: [<?php for ($d = 0; $d < 7; $d++) { $count = $mysqli->query("SELECT COUNT(*) FROM attendance WHERE WEEKDAY(date) = $d")->fetch_row()[0]; echo $count . ',';} ?>]
    }]
};
window.addEventListener('DOMContentLoaded', () => {
    new Chart(document.getElementById('attendanceMonthChart'), { type: 'bar', data: attendanceMonthData, options: { responsive: true, plugins: { legend: { display: false } }}});
    new Chart(document.getElementById('presentAbsentChart'), { type: 'doughnut', data: presentAbsentData, options: { responsive: true }});
    new Chart(document.getElementById('weeklyAttendanceChart'), { type: 'line', data: weeklyAttendanceData, options: { responsive: true, plugins: { legend: { display: false } }, tension: 0.3 }});
});
</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>