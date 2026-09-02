<?php
require_once __DIR__ . '/../includes/functions.php';
requireRole(['admin', 'superadmin']);
$pageTitle = 'Rooms';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        flash('Invalid request.', 'danger');
        redirect('rooms.php');
    }
    if ($_POST['action'] === 'save_room') {
        $id = intval($_POST['id'] ?? 0);
        $yearLevel = sanitize($_POST['year_level'] ?? '');
        $roomName = sanitize($_POST['room_name'] ?? '');
        $courseId = intval($_POST['course_id'] ?? 0);
        $adviserId = intval($_POST['adviser_id'] ?? 0);
        $adviserIdParam = $adviserId ?: null;
        if ($id) {
            $stmt = $mysqli->prepare('UPDATE rooms SET year_level = ?, room_name = ?, course_id = ?, adviser_id = ? WHERE id = ?');
            $stmt->bind_param('ssiii', $yearLevel, $roomName, $courseId, $adviserIdParam, $id);
            $stmt->execute();
            $stmt->close();
            flash('Room updated.', 'success');
        } else {
            $stmt = $mysqli->prepare('INSERT INTO rooms (year_level, room_name, course_id, adviser_id, created_at) VALUES (?, ?, ?, ?, NOW())');
            $stmt->bind_param('ssii', $yearLevel, $roomName, $courseId, $adviserIdParam);
            $stmt->execute();
            $stmt->close();
            flash('Room created.', 'success');
        }
        redirect('rooms.php');
    }
    if ($_POST['action'] === 'delete_room' && !empty($_POST['room_id'])) {
        $id = intval($_POST['room_id']);
        $stmt = $mysqli->prepare('DELETE FROM rooms WHERE id = ?');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $stmt->close();
        flash('Room removed.', 'success');
        redirect('rooms.php');
    }
}
$courses = $mysqli->query('SELECT id, code FROM courses ORDER BY code');
$teachers = $mysqli->query("SELECT id, first_name, last_name FROM teachers WHERE status = 'active' ORDER BY first_name");
$rooms = $mysqli->query('SELECT sec.*, c.code AS course_code, CONCAT(t.first_name, " ", t.last_name) AS adviser_name FROM rooms sec LEFT JOIN courses c ON sec.course_id = c.id LEFT JOIN teachers t ON sec.adviser_id = t.id ORDER BY sec.created_at DESC');
require_once __DIR__ . '/../includes/admin_header.php';
?>
<div class="card rounded-4 shadow-sm p-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4>Room Management</h4>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#roomModal">Add Room</button>
    </div>
    <div class="table-responsive">
        <table class="table table-hover" id="roomsTable">
            <thead class="table-light">
                <tr><th>Year</th><th>Room</th><th>Course</th><th>Adviser</th><th>Actions</th></tr>
            </thead>
            <tbody>
                <?php while ($row = $rooms->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($row['year_level']); ?></td>
                        <td><?php echo htmlspecialchars($row['room_name']); ?></td>
                        <td><?php echo htmlspecialchars($row['course_code']); ?></td>
                        <td><?php echo htmlspecialchars($row['adviser_name'] ?: 'Unassigned'); ?></td>
                        <td>
                            <button class="btn btn-sm btn-outline-primary btn-edit-room" data-data='<?php echo json_encode($row); ?>'>Edit</button>
                            <form method="post" class="d-inline-block" onsubmit="return confirm('Delete this room?');">
                                <input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>">
                                <input type="hidden" name="action" value="delete_room">
                                <input type="hidden" name="room_id" value="<?php echo $row['id']; ?>">
                                <button class="btn btn-sm btn-outline-danger">Delete</button>
                            </form>
                        </td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>
<div class="modal fade" id="roomModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-4">
            <div class="modal-header">
                <h5 class="modal-title">Room Form</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="post">
                <input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>">
                <input type="hidden" name="action" value="save_room">
                <input type="hidden" name="id" id="roomIdField">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Year Level</label>
                        <input type="text" class="form-control" name="year_level" id="roomYearField" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Room Name</label>
                        <input type="text" class="form-control" name="room_name" id="roomNameField" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Course</label>
                        <select class="form-select" name="course_id" id="roomCourseField">
                            <option value="0">Select Course</option>
                            <?php while ($course = $courses->fetch_assoc()): ?>
                                <option value="<?php echo $course['id']; ?>"><?php echo htmlspecialchars($course['code']); ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Adviser</label>
                        <select class="form-select" name="adviser_id" id="roomAdviserField">
                            <option value="0">Unassigned</option>
                            <?php while ($teacher = $teachers->fetch_assoc()): ?>
                                <option value="<?php echo $teacher['id']; ?>"><?php echo htmlspecialchars($teacher['first_name'] . ' ' . $teacher['last_name']); ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Room</button>
                </div>
            </form>
        </div>
    </div>
</div>
<script>
const roomModal = new bootstrap.Modal(document.getElementById('roomModal'));
document.querySelectorAll('.btn-edit-room').forEach(btn => {
    btn.addEventListener('click', () => {
        const data = JSON.parse(btn.getAttribute('data-data'));
        document.getElementById('roomIdField').value = data.id;
        document.getElementById('roomYearField').value = data.year_level;
        document.getElementById('roomNameField').value = data.room_name;
        document.getElementById('roomCourseField').value = data.course_id;
        document.getElementById('roomAdviserField').value = data.adviser_id || '0';
        roomModal.show();
    });
});
$(document).ready(function () {
    $('#roomsTable').DataTable({ responsive: true });
});
</script>
<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>