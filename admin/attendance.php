<?php
require_once __DIR__ . '/../includes/functions.php';
requireRole(['admin', 'superadmin', 'teacher']);
$pageTitle = 'Attendance Records';

finalizeTodaysAbsences($mysqli);

$period = $_REQUEST['period'] ?? 'all';
if (!in_array($period, ['all', 'today', 'month', 'specific', 'range'])) {
    $period = 'all';
}
$date = sanitize($_REQUEST['date'] ?? '');
$dateFrom = sanitize($_REQUEST['date_from'] ?? '');
$dateTo = sanitize($_REQUEST['date_to'] ?? '');
$courseId = intval($_REQUEST['course_id'] ?? 0);
$roomId = intval($_REQUEST['room_id'] ?? 0);
$studentQuery = sanitize($_REQUEST['student'] ?? '');

$where = ['1=1'];
$params = [];
$types = '';
switch ($period) {
    case 'today':
        $where[] = 'a.date = ?';
        $types .= 's';
        $params[] = date('Y-m-d');
        break;
    case 'month':
        $where[] = 'a.date >= ?';
        $types .= 's';
        $params[] = date('Y-m-01');
        break;
    case 'specific':
        if ($date) {
            $where[] = 'a.date = ?';
            $types .= 's';
            $params[] = $date;
        }
        break;
    case 'range':
        if ($dateFrom) {
            $where[] = 'a.date >= ?';
            $types .= 's';
            $params[] = $dateFrom;
        }
        if ($dateTo) {
            $where[] = 'a.date <= ?';
            $types .= 's';
            $params[] = $dateTo;
        }
        break;
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
    $where[] = '(CONCAT(s.first_name, " ", s.last_name) LIKE ? OR s.student_id LIKE ?)';
    $types .= 'ss';
    $params[] = '%' . $studentQuery . '%';
    $params[] = '%' . $studentQuery . '%';
}
$whereSql = implode(' AND ', $where);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['action'] === 'export') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="attendance_export_' . date('Ymd_His') . '.csv"');
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Student ID','Student Name','Course','Year Level','Room','Subject','Status','Date','Time']);
    $query = 'SELECT s.student_id, s.year_level, CONCAT(s.first_name, " ", s.last_name) AS full_name, c.code AS course_code, sec.room_name, sub.name AS subject_name, a.status, a.date, a.time FROM attendance a LEFT JOIN students s ON a.student_id = s.id LEFT JOIN courses c ON a.course_id = c.id LEFT JOIN rooms sec ON a.room_id = sec.id LEFT JOIN subjects sub ON a.subject_id = sub.id WHERE ' . $whereSql . ' ORDER BY a.created_at DESC';
    $stmt = $mysqli->prepare($query);
    if ($params) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        fputcsv($output, [$row['student_id'], $row['full_name'], $row['course_code'], $row['year_level'], $row['room_name'], $row['subject_name'], $row['status'], $row['date'], $row['time']]);
    }
    fclose($output);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['action'] === 'export_pdf') {
    $query = 'SELECT s.student_id, s.year_level, CONCAT(s.first_name, " ", s.last_name) AS full_name, c.code AS course_code, sec.room_name, sub.name AS subject_name, a.status, a.date, a.time FROM attendance a LEFT JOIN students s ON a.student_id = s.id LEFT JOIN courses c ON a.course_id = c.id LEFT JOIN rooms sec ON a.room_id = sec.id LEFT JOIN subjects sub ON a.subject_id = sub.id WHERE ' . $whereSql . ' ORDER BY a.created_at DESC';
    $stmt = $mysqli->prepare($query);
    if ($params) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $rows = [];
    while ($row = $result->fetch_assoc()) {
        $rows[] = [$row['date'], $row['time'], $row['full_name'], $row['student_id'], $row['course_code'] ?: '-', $row['room_name'] ?: '-', $row['subject_name'] ?: '-', ucfirst($row['status'])];
    }
    $pdf = generateSimpleTablePdf('Attendance Records', ['Date', 'Time', 'Student', 'ID', 'Course', 'Room', 'Subject', 'Status'], $rows, [65, 55, 130, 65, 60, 80, 130, 70]);
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="attendance_export_' . date('Ymd_His') . '.pdf"');
    header('Content-Length: ' . strlen($pdf));
    echo $pdf;
    exit;
}

$courses = $mysqli->query('SELECT id, code FROM courses ORDER BY code');
$rooms = $mysqli->query('SELECT id, room_name FROM rooms ORDER BY room_name');
$query = 'SELECT a.*, s.student_id, s.photo, s.year_level, CONCAT(s.first_name, " ", s.last_name) AS student_name, c.code AS course_code, sec.room_name, sub.name AS subject_name FROM attendance a LEFT JOIN students s ON a.student_id = s.id LEFT JOIN courses c ON a.course_id = c.id LEFT JOIN rooms sec ON a.room_id = sec.id LEFT JOIN subjects sub ON a.subject_id = sub.id WHERE ' . $whereSql . ' ORDER BY a.created_at DESC';
$stmt = $mysqli->prepare($query);
if ($params) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$attendanceRecords = $stmt->get_result();
require_once __DIR__ . '/../includes/admin_header.php';
?>
<div class="card rounded-4 shadow-sm p-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4>Attendance Records</h4>
        <div class="d-flex gap-2">
            <form method="post" class="m-0">
                <input type="hidden" name="action" value="export">
                <input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>">
                <input type="hidden" name="period" value="<?php echo htmlspecialchars($period); ?>">
                <input type="hidden" name="date" value="<?php echo htmlspecialchars($date); ?>">
                <input type="hidden" name="date_from" value="<?php echo htmlspecialchars($dateFrom); ?>">
                <input type="hidden" name="date_to" value="<?php echo htmlspecialchars($dateTo); ?>">
                <input type="hidden" name="course_id" value="<?php echo htmlspecialchars($courseId); ?>">
                <input type="hidden" name="room_id" value="<?php echo htmlspecialchars($roomId); ?>">
                <input type="hidden" name="student" value="<?php echo htmlspecialchars($studentQuery); ?>">
                <button type="submit" class="btn btn-outline-success">Export CSV</button>
            </form>
            <form method="post" class="m-0">
                <input type="hidden" name="action" value="export_pdf">
                <input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>">
                <input type="hidden" name="period" value="<?php echo htmlspecialchars($period); ?>">
                <input type="hidden" name="date" value="<?php echo htmlspecialchars($date); ?>">
                <input type="hidden" name="date_from" value="<?php echo htmlspecialchars($dateFrom); ?>">
                <input type="hidden" name="date_to" value="<?php echo htmlspecialchars($dateTo); ?>">
                <input type="hidden" name="course_id" value="<?php echo htmlspecialchars($courseId); ?>">
                <input type="hidden" name="room_id" value="<?php echo htmlspecialchars($roomId); ?>">
                <input type="hidden" name="student" value="<?php echo htmlspecialchars($studentQuery); ?>">
                <button type="submit" class="btn btn-outline-primary">Download PDF</button>
            </form>
        </div>
    </div>
    <form method="get" class="d-flex flex-wrap align-items-end gap-3 mb-4">
        <div class="flex-fill" style="min-width: 160px;">
            <label class="form-label">Period</label>
            <select class="form-select" name="period" id="periodField">
                <option value="all" <?php echo $period === 'all' ? 'selected' : ''; ?>>All Time</option>
                <option value="today" <?php echo $period === 'today' ? 'selected' : ''; ?>>Today</option>
                <option value="month" <?php echo $period === 'month' ? 'selected' : ''; ?>>This Month</option>
                <option value="specific" <?php echo $period === 'specific' ? 'selected' : ''; ?>>Specific Date</option>
                <option value="range" <?php echo $period === 'range' ? 'selected' : ''; ?>>Date Range</option>
            </select>
        </div>
        <div class="flex-fill" style="min-width: 160px;" id="specificDateWrap">
            <label class="form-label">Date</label>
            <input type="date" class="form-control" name="date" value="<?php echo htmlspecialchars($date); ?>">
        </div>
        <div class="flex-fill" style="min-width: 160px;" id="dateFromWrap">
            <label class="form-label">From</label>
            <input type="date" class="form-control" name="date_from" value="<?php echo htmlspecialchars($dateFrom); ?>">
        </div>
        <div class="flex-fill" style="min-width: 160px;" id="dateToWrap">
            <label class="form-label">To</label>
            <input type="date" class="form-control" name="date_to" value="<?php echo htmlspecialchars($dateTo); ?>">
        </div>
        <div class="flex-fill" style="min-width: 160px;">
            <label class="form-label">Course</label>
            <select class="form-select" name="course_id">
                <option value="">All courses</option>
                <?php while ($course = $courses->fetch_assoc()): ?>
                    <option value="<?php echo $course['id']; ?>" <?php echo $courseId == $course['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($course['code']); ?></option>
                <?php endwhile; ?>
            </select>
        </div>
        <div class="flex-fill" style="min-width: 160px;">
            <label class="form-label">Room</label>
            <select class="form-select" name="room_id">
                <option value="">All rooms</option>
                <?php while ($room = $rooms->fetch_assoc()): ?>
                    <option value="<?php echo $room['id']; ?>" <?php echo $roomId == $room['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($room['room_name']); ?></option>
                <?php endwhile; ?>
            </select>
        </div>
        <div class="flex-fill" style="min-width: 160px;">
            <label class="form-label">Student Search</label>
            <input type="text" class="form-control" name="student" placeholder="Name or ID" value="<?php echo htmlspecialchars($studentQuery); ?>">
        </div>
        <div class="d-flex gap-2">
            <button type="submit" class="btn btn-primary btn-icon" title="Filter"><i class="fa-solid fa-filter"></i></button>
            <a href="attendance.php" class="btn btn-outline-secondary btn-icon" title="Reset"><i class="fa-solid fa-rotate-left"></i></a>
        </div>
    </form>
    <div class="table-responsive">
        <table class="table table-striped" id="attendanceTable">
            <thead class="table-light">
                <tr>
                    <th>Date</th>
                    <th>Time</th>
                    <th>Photo</th>
                    <th>Student</th>
                    <th>ID</th>
                    <th>Course</th>
                    <th>Year</th>
                    <th>Room</th>
                    <th>Subject</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($row = $attendanceRecords->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo formatDate($row['date']); ?></td>
                        <td><?php echo formatTime($row['time']); ?></td>
                        <td>
                            <?php if (!empty($row['photo'])): ?>
                                <img src="../<?php echo htmlspecialchars($row['photo']); ?>" class="table-avatar" alt="">
                            <?php else: ?>
                                <span class="table-avatar table-avatar-fallback"><i class="fa-solid fa-user"></i></span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo htmlspecialchars($row['student_name']); ?></td>
                        <td><?php echo htmlspecialchars($row['student_id']); ?></td>
                        <td><?php echo htmlspecialchars($row['course_code']); ?></td>
                        <td><?php echo htmlspecialchars($row['year_level']); ?></td>
                        <td><?php echo htmlspecialchars($row['room_name']); ?></td>
                        <td><?php echo htmlspecialchars($row['subject_name']); ?></td>
                        <td><?php echo badgeStatus($row['status']); ?></td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const periodField = document.getElementById('periodField');
    function updatePeriodFields() {
        const period = periodField.value;
        document.getElementById('specificDateWrap').style.display = period === 'specific' ? '' : 'none';
        document.getElementById('dateFromWrap').style.display = period === 'range' ? '' : 'none';
        document.getElementById('dateToWrap').style.display = period === 'range' ? '' : 'none';
    }
    periodField.addEventListener('change', updatePeriodFields);
    updatePeriodFields();

    $('#attendanceTable').DataTable({ responsive: true, paging: false, ordering: false, dom: 'frt' });
});
</script>
<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>