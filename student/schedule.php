<?php
require_once __DIR__ . '/../includes/functions.php';
requireRole(['student']);
$pageTitle = 'My Schedule';

$studentDbId = currentStudentId();
if ($studentDbId === false) {
    flash('Your student profile is not set up. Contact an administrator.', 'danger');
    redirect('../dashboard.php');
}

$stmt = $mysqli->prepare('SELECT room_id FROM students WHERE id = ?');
$stmt->bind_param('i', $studentDbId);
$stmt->execute();
$stmt->bind_result($roomId);
$stmt->fetch();
$stmt->close();

$schedule = [];
if ($roomId) {
    $dayOrder = "FIELD(sub.day_of_week,'Mon','Tue','Wed','Thu','Fri','Sat','Sun')";
    $stmt = $mysqli->prepare("SELECT sub.*, CONCAT(t.first_name, ' ', t.last_name) AS teacher_name FROM subjects sub LEFT JOIN teachers t ON sub.teacher_id = t.id WHERE sub.room_id = ? AND sub.status = 'active' ORDER BY $dayOrder, sub.start_time");
    $stmt->bind_param('i', $roomId);
    $stmt->execute();
    $schedule = $stmt->get_result();
}

require_once __DIR__ . '/../includes/student_header.php';
?>
<div class="card p-4">
    <h5 class="mb-3">Weekly Schedule</h5>
    <?php if (!$roomId): ?>
        <div class="alert alert-info">You are not assigned to a room yet. Contact an administrator.</div>
    <?php elseif ($schedule->num_rows === 0): ?>
        <div class="alert alert-info">No subjects scheduled for your room yet.</div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-striped mb-0">
                <thead class="table-light">
                    <tr><th>Day</th><th>Start Time</th><th>Subject</th><th>Teacher</th></tr>
                </thead>
                <tbody>
                    <?php while ($row = $schedule->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($row['day_of_week']); ?></td>
                            <td><?php echo formatTime($row['start_time']); ?></td>
                            <td><?php echo htmlspecialchars($row['name']); ?> (<?php echo htmlspecialchars($row['code']); ?>)</td>
                            <td><?php echo htmlspecialchars($row['teacher_name'] ?: 'Unassigned'); ?></td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
<?php require_once __DIR__ . '/../includes/student_footer.php'; ?>
