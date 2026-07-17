<?php
require_once __DIR__ . '/../includes/functions.php';
requireRole(['admin', 'superadmin']);
$pageTitle = 'Reports';
$today = date('Y-m-d');
$daily = $mysqli->query("SELECT COUNT(*) FROM attendance WHERE date = '$today'")->fetch_row()[0];
$weekly = $mysqli->query("SELECT COUNT(*) FROM attendance WHERE date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)")->fetch_row()[0];
$monthly = $mysqli->query("SELECT COUNT(*) FROM attendance WHERE MONTH(date) = MONTH(CURDATE()) AND YEAR(date) = YEAR(CURDATE())")->fetch_row()[0];
$topAbsent = $mysqli->query("SELECT s.first_name, s.last_name, COUNT(*) AS total FROM attendance a JOIN students s ON a.student_id = s.id WHERE a.status = 'absent' GROUP BY a.student_id ORDER BY total DESC LIMIT 5");
$topLate = $mysqli->query("SELECT s.first_name, s.last_name, COUNT(*) AS total FROM attendance a JOIN students s ON a.student_id = s.id WHERE a.status = 'late' GROUP BY a.student_id ORDER BY total DESC LIMIT 5");
$perfect = $mysqli->query("SELECT s.first_name, s.last_name, COUNT(*) AS total FROM attendance a JOIN students s ON a.student_id = s.id WHERE a.status = 'present' GROUP BY a.student_id HAVING COUNT(*) >= 5 ORDER BY total DESC LIMIT 5");
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/admin_nav.php';
?>
<div class="card rounded-4 shadow-sm p-4">
    <h4>Attendance Reports</h4>
    <div class="row g-3 mt-3">
        <div class="col-md-4"><div class="card rounded-4 p-3"><h5>Daily</h5><p class="display-6"><?php echo $daily; ?></p></div></div>
        <div class="col-md-4"><div class="card rounded-4 p-3"><h5>Weekly</h5><p class="display-6"><?php echo $weekly; ?></p></div></div>
        <div class="col-md-4"><div class="card rounded-4 p-3"><h5>Monthly</h5><p class="display-6"><?php echo $monthly; ?></p></div></div>
    </div>
    <div class="row g-3 mt-4">
        <div class="col-lg-6">
            <div class="card rounded-4 p-3">
                <h5>Most Absent</h5>
                <ul class="list-group list-group-flush">
                    <?php while ($row = $topAbsent->fetch_assoc()): ?>
                        <li class="list-group-item d-flex justify-content-between align-items-center"><?php echo htmlspecialchars($row['first_name'] . ' ' . $row['last_name']); ?><span class="badge bg-danger rounded-pill"><?php echo $row['total']; ?></span></li>
                    <?php endwhile; ?>
                </ul>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card rounded-4 p-3">
                <h5>Most Late</h5>
                <ul class="list-group list-group-flush">
                    <?php while ($row = $topLate->fetch_assoc()): ?>
                        <li class="list-group-item d-flex justify-content-between align-items-center"><?php echo htmlspecialchars($row['first_name'] . ' ' . $row['last_name']); ?><span class="badge bg-warning rounded-pill"><?php echo $row['total']; ?></span></li>
                    <?php endwhile; ?>
                </ul>
            </div>
        </div>
    </div>
    <div class="row g-3 mt-4">
        <div class="col-12">
            <div class="card rounded-4 p-3">
                <h5>Perfect Attendance</h5>
                <ul class="list-group list-group-flush">
                    <?php while ($row = $perfect->fetch_assoc()): ?>
                        <li class="list-group-item"><?php echo htmlspecialchars($row['first_name'] . ' ' . $row['last_name']); ?> <span class="text-muted">(<?php echo $row['total']; ?> days)</span></li>
                    <?php endwhile; ?>
                </ul>
            </div>
        </div>
    </div>
</div>
</div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>