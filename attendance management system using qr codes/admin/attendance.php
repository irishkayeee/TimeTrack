<?php
require_once __DIR__ . '/../includes/functions.php';
requireRole(['admin', 'superadmin', 'teacher']);
$pageTitle = 'Attendance Records';

$date = sanitize($_GET['date'] ?? date('Y-m-d'));
$courseId = intval($_GET['course_id'] ?? 0);
$roomId = intval($_GET['room_id'] ?? 0);
$studentQuery = sanitize($_GET['student'] ?? '');

$where = ['1=1'];
$params = [];
$types = '';
if ($date) {
    $where[] = 'a.date = ?';
    $types .= 's';
    $params[] = $date;
}
if ($courseId) {
    $where[] = 'a.course_id = ?';
    $types .= 'i';
    $params[] = $courseId;
}
if ($roomId) {
    $where[] = 'a.room_id = ?';
    $types .= 'i';
    $params[] = $roomId;
}
if ($studentQuery) {
    $where[] = '(s.first_name LIKE ? OR s.last_name LIKE ? OR s.student_id LIKE ?)';
    $types .= 'sss';
    $params[] = '%' . $studentQuery . '%';
    $params[] = '%' . $studentQuery . '%';
    $params[] = '%' . $studentQuery . '%';
}
$whereSql = implode(' AND ', $where);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['action'] === 'export') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="attendance_export_' . date('Ymd_His') . '.csv"');
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Student ID','Student Name','Course','Section','Status','Date','Time']);
    $query = 'SELECT s.student_id, CONCAT(s.first_name, " ", s.last_name) AS full_name, c.code AS course_code, sec.room_name, a.status, a.date, a.time FROM attendance a LEFT JOIN students s ON a.student_id = s.id LEFT JOIN courses c ON a.course_id = c.id LEFT JOIN rooms sec ON a.room_id = sec.id WHERE ' . $whereSql . ' ORDER BY a.created_at DESC';
    $stmt = $mysqli->prepare($query);
    if ($params) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        fputcsv($output, [$row['student_id'], $row['full_name'], $row['course_code'], $row['room_name'], $row['status'], $row['date'], $row['time']]);
    }
    fclose($output);
    exit;
}

$courses = $mysqli->query('SELECT id, code FROM courses ORDER BY code');
$rooms = $mysqli->query('SELECT id, room_name FROM rooms ORDER BY room_name');
$query = 'SELECT a.*, s.student_id, CONCAT(s.first_name, " ", s.last_name) AS student_name, c.code AS course_code, sec.room_name FROM attendance a LEFT JOIN students s ON a.student_id = s.id LEFT JOIN courses c ON a.course_id = c.id LEFT JOIN rooms sec ON a.room_id = sec.id WHERE ' . $whereSql . ' ORDER BY a.created_at DESC';
$stmt = $mysqli->prepare($query);
if ($params) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$attendanceRecords = $stmt->get_result();
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/admin_nav.php';
?>
<div class="card rounded-4 shadow-sm p-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4>Attendance Records</h4>
        <form method="post" class="m-0">
            <input type="hidden" name="action" value="export">
            <input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>">
            <button type="submit" class="btn btn-outline-success">Export CSV</button>
        </form>
    </div>
    <form method="get" class="row g-3 mb-4">
        <div class="col-md-3">
            <label class="form-label">Date</label>
            <input type="date" class="form-control" name="date" value="<?php echo htmlspecialchars($date); ?>">
        </div>
        <div class="col-md-3">
            <label class="form-label">Course</label>
            <select class="form-select" name="course_id">
                <option value="">All courses</option>
                <?php while ($course = $courses->fetch_assoc()): ?>
                    <option value="<?php echo $course['id']; ?>" <?php echo $courseId == $course['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($course['code']); ?></option>
                <?php endwhile; ?>
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label">Room</label>
            <select class="form-select" name="room_id">
                <option value="">All rooms</option>
                <?php while ($room = $rooms->fetch_assoc()): ?>
                    <option value="<?php echo $room['id']; ?>" <?php echo $roomId == $room['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($room['room_name']); ?></option>
                <?php endwhile; ?>
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label">Student Search</label>
            <input type="text" class="form-control" name="student" placeholder="Name or ID" value="<?php echo htmlspecialchars($studentQuery); ?>">
        </div>
        <div class="col-md-12 text-end">
            <button type="submit" class="btn btn-primary">Filter</button>
        </div>
    </form>
    <div class="table-responsive">
        <table class="table table-striped" id="attendanceTable">
            <thead class="table-light">
                <tr>
                    <th>Date</th>
                    <th>Time</th>
                    <th>Student</th>
                    <th>ID</th>
                    <th>Course</th>
                    <th>Section</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($row = $attendanceRecords->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($row['date']); ?></td>
                        <td><?php echo htmlspecialchars($row['time']); ?></td>
                        <td><?php echo htmlspecialchars($row['student_name']); ?></td>
                        <td><?php echo htmlspecialchars($row['student_id']); ?></td>
                        <td><?php echo htmlspecialchars($row['course_code']); ?></td>
                        <td><?php echo htmlspecialchars($row['room_name']); ?></td>
                        <td><?php echo badgeStatus($row['status']); ?></td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>
</div>
</div>
<script>
$(document).ready(function () {
    $('#attendanceTable').DataTable({ responsive: true });
});
</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>