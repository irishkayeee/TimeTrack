<?php
require_once __DIR__ . '/../includes/functions.php';
requireRole(['teacher']);
$pageTitle = 'Attendance Records';
$pageSubtitle = 'Attendance across your classes, filterable by date.';

$teacherId = currentTeacherId();
if ($teacherId === false) {
    flash('Your teacher profile is not set up. Contact an administrator.', 'danger');
    redirect('../dashboard.php');
}

$date = sanitize($_GET['date'] ?? date('Y-m-d'));
$subjectId = intval($_GET['subject_id'] ?? 0);

$subjectsStmt = $mysqli->prepare('SELECT id, code, name FROM subjects WHERE teacher_id = ? ORDER BY name');
$subjectsStmt->bind_param('i', $teacherId);
$subjectsStmt->execute();
$mySubjects = $subjectsStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$subjectsStmt->close();

$query = 'SELECT a.*, s.student_id, CONCAT(s.first_name, " ", s.last_name) AS student_name, c.code AS course_code, sec.section_name, sub.name AS subject_name FROM attendance a LEFT JOIN students s ON a.student_id = s.id LEFT JOIN courses c ON a.course_id = c.id LEFT JOIN sections sec ON a.section_id = sec.id JOIN subjects sub ON a.subject_id = sub.id WHERE a.date = ? AND sub.teacher_id = ?';
$types = 'si';
$params = [$date, $teacherId];
if ($subjectId) {
    $query .= ' AND sub.id = ?';
    $types .= 'i';
    $params[] = $subjectId;
}
$query .= ' ORDER BY a.created_at DESC';
$records = $mysqli->prepare($query);
$records->bind_param($types, ...$params);
$records->execute();
$result = $records->get_result();
require_once __DIR__ . '/../includes/teacher_header.php';
?>
<div class="card p-4">
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <a href="subjects.php" class="text-decoration-none small"><i class="fa-solid fa-arrow-left me-1"></i> Back to My Classes</a>
        <form method="get" class="d-flex gap-2">
            <select class="form-select" name="subject_id">
                <option value="0">All Classes</option>
                <?php foreach ($mySubjects as $subj): ?>
                    <option value="<?php echo $subj['id']; ?>" <?php echo $subjectId === (int) $subj['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($subj['code']); ?></option>
                <?php endforeach; ?>
            </select>
            <input type="date" class="form-control" name="date" value="<?php echo htmlspecialchars($date); ?>">
            <button class="btn btn-primary">Filter</button>
        </form>
    </div>
    <div class="table-responsive">
        <table class="table table-striped" id="teacherAttendanceTable">
            <thead class="table-light">
                <tr><th>Date</th><th>Time</th><th>Student</th><th>ID</th><th>Subject</th><th>Section</th><th>Status</th></tr>
            </thead>
            <tbody>
                <?php while ($row = $result->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($row['date']); ?></td>
                        <td><?php echo htmlspecialchars($row['time']); ?></td>
                        <td><?php echo htmlspecialchars($row['student_name']); ?></td>
                        <td><?php echo htmlspecialchars($row['student_id']); ?></td>
                        <td><?php echo htmlspecialchars($row['subject_name']); ?></td>
                        <td><?php echo htmlspecialchars($row['section_name']); ?></td>
                        <td><?php echo badgeStatus($row['status']); ?></td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>
<script>
$(document).ready(function () {
    $('#teacherAttendanceTable').DataTable({ responsive: true });
});
</script>
<?php require_once __DIR__ . '/../includes/teacher_footer.php'; ?>