<?php
require_once __DIR__ . '/../includes/functions.php';
requireRole(['teacher']);
$pageTitle = 'My Subjects';

$teacherId = currentTeacherId();
if ($teacherId === false) {
    flash('Your teacher profile is not set up. Contact an administrator.', 'danger');
    redirect('../dashboard.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        flash('Invalid request.', 'danger');
        redirect('subjects.php');
    }
    if ($_POST['action'] === 'save_schedule') {
        $id = intval($_POST['id'] ?? 0);
        $dayOfWeek = sanitize($_POST['day_of_week'] ?? 'Mon');
        $startTime = sanitize($_POST['start_time'] ?? '07:30');
        $stmt = $mysqli->prepare('UPDATE subjects SET day_of_week = ?, start_time = ? WHERE id = ? AND teacher_id = ?');
        $stmt->bind_param('ssii', $dayOfWeek, $startTime, $id, $teacherId);
        $stmt->execute();
        $stmt->close();
        flash('Schedule updated.', 'success');
        redirect('subjects.php');
    }
}

$stmt = $mysqli->prepare('SELECT sub.*, sec.section_name FROM subjects sub JOIN sections sec ON sub.section_id = sec.id WHERE sub.teacher_id = ? ORDER BY sub.name');
$stmt->bind_param('i', $teacherId);
$stmt->execute();
$subjects = $stmt->get_result();
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/teacher_nav.php';
?>
<div class="card rounded-4 shadow-sm p-4">
    <h4>My Subjects</h4>
    <p class="text-muted">You can edit the day and start time for your subjects. Contact an admin to change name, section, or teacher assignment.</p>
    <div class="table-responsive">
        <table class="table table-hover" id="mySubjectsTable">
            <thead class="table-light">
                <tr><th>Code</th><th>Name</th><th>Section</th><th>Day</th><th>Start Time</th><th>Status</th><th>Actions</th></tr>
            </thead>
            <tbody>
                <?php while ($row = $subjects->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($row['code']); ?></td>
                        <td><?php echo htmlspecialchars($row['name']); ?></td>
                        <td><?php echo htmlspecialchars($row['section_name']); ?></td>
                        <td><?php echo htmlspecialchars($row['day_of_week']); ?></td>
                        <td><?php echo formatTime($row['start_time']); ?></td>
                        <td><?php echo badgeStatus($row['status']); ?></td>
                        <td>
                            <button class="btn btn-sm btn-outline-primary btn-edit-schedule" data-data='<?php echo json_encode($row); ?>'>Edit Schedule</button>
                        </td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>
</div>
</div>
<div class="modal fade" id="scheduleModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-4">
            <div class="modal-header">
                <h5 class="modal-title" id="scheduleModalTitle">Edit Schedule</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="post">
                <input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>">
                <input type="hidden" name="action" value="save_schedule">
                <input type="hidden" name="id" id="scheduleIdField">
                <div class="modal-body row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Day of Week</label>
                        <select class="form-select" name="day_of_week" id="scheduleDayField">
                            <?php foreach (['Mon','Tue','Wed','Thu','Fri','Sat','Sun'] as $day): ?>
                                <option value="<?php echo $day; ?>"><?php echo $day; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Start Time</label>
                        <input type="time" class="form-control" name="start_time" id="scheduleStartTimeField" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Schedule</button>
                </div>
            </form>
        </div>
    </div>
</div>
<script>
const scheduleModal = new bootstrap.Modal(document.getElementById('scheduleModal'));
document.querySelectorAll('.btn-edit-schedule').forEach(btn => {
    btn.addEventListener('click', () => {
        const data = JSON.parse(btn.getAttribute('data-data'));
        document.getElementById('scheduleModalTitle').textContent = 'Edit Schedule — ' + data.name;
        document.getElementById('scheduleIdField').value = data.id;
        document.getElementById('scheduleDayField').value = data.day_of_week;
        document.getElementById('scheduleStartTimeField').value = data.start_time;
        scheduleModal.show();
    });
});
$(document).ready(function () {
    $('#mySubjectsTable').DataTable({ responsive: true });
});
</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
