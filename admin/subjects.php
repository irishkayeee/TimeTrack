<?php
require_once __DIR__ . '/../includes/functions.php';
requireRole(['admin', 'superadmin']);
$pageTitle = 'Subjects';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        flash('Invalid request.', 'danger');
        redirect('subjects.php');
    }
    if ($_POST['action'] === 'save_subject') {
        $id = intval($_POST['id'] ?? 0);
        $code = sanitize($_POST['code'] ?? '');
        $name = sanitize($_POST['name'] ?? '');
        $teacherId = intval($_POST['teacher_id'] ?? 0);
        $sectionId = intval($_POST['section_id'] ?? 0);
        $dayOfWeek = sanitize($_POST['day_of_week'] ?? 'Mon');
        $startTime = sanitize($_POST['start_time'] ?? '07:30');
        $endTime = sanitize($_POST['end_time'] ?? '');
        $endTimeParam = $endTime ?: null;
        $room = sanitize($_POST['room'] ?? '');
        $creditUnits = intval($_POST['credit_units'] ?? 0) ?: null;
        $importantNote = sanitize($_POST['important_note'] ?? '');
        $importantNoteParam = $importantNote ?: null;
        $status = sanitize($_POST['status'] ?? 'active');
        $teacherIdParam = $teacherId ?: null;
        if ($id) {
            $stmt = $mysqli->prepare('UPDATE subjects SET code = ?, name = ?, teacher_id = ?, section_id = ?, day_of_week = ?, start_time = ?, end_time = ?, room = ?, credit_units = ?, important_note = ?, status = ? WHERE id = ?');
            $stmt->bind_param('ssiissssissi', $code, $name, $teacherIdParam, $sectionId, $dayOfWeek, $startTime, $endTimeParam, $room, $creditUnits, $importantNoteParam, $status, $id);
            $stmt->execute();
            $stmt->close();
            flash('Subject updated.', 'success');
        } else {
            $stmt = $mysqli->prepare('INSERT INTO subjects (code, name, teacher_id, section_id, day_of_week, start_time, end_time, room, credit_units, important_note, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())');
            $stmt->bind_param('ssiissssiss', $code, $name, $teacherIdParam, $sectionId, $dayOfWeek, $startTime, $endTimeParam, $room, $creditUnits, $importantNoteParam, $status);
            $stmt->execute();
            $stmt->close();
            flash('Subject created.', 'success');
        }
        redirect('subjects.php');
    }
    if ($_POST['action'] === 'delete_subject' && !empty($_POST['subject_id'])) {
        $id = intval($_POST['subject_id']);
        $stmt = $mysqli->prepare('DELETE FROM subjects WHERE id = ?');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $stmt->close();
        flash('Subject removed.', 'success');
        redirect('subjects.php');
    }
}
$teachers = $mysqli->query("SELECT id, first_name, last_name FROM teachers WHERE status = 'active' ORDER BY first_name");
$sections = $mysqli->query('SELECT id, section_name, year_level FROM sections ORDER BY section_name');
$subjects = $mysqli->query('SELECT sub.*, CONCAT(t.first_name, " ", t.last_name) AS teacher_name, sec.section_name FROM subjects sub LEFT JOIN teachers t ON sub.teacher_id = t.id LEFT JOIN sections sec ON sub.section_id = sec.id ORDER BY sub.created_at DESC');
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/admin_nav.php';
?>
<div class="card rounded-4 shadow-sm p-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h4>Subject Management</h4>
            <p class="text-muted mb-0">A subject is a class session: one weekly day/time slot, taught by a teacher to a section. A class held on multiple days needs one row per day.</p>
        </div>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#subjectModal">Add Subject</button>
    </div>
    <div class="table-responsive">
        <table class="table table-hover" id="subjectsTable">
            <thead class="table-light">
                <tr><th>Code</th><th>Name</th><th>Teacher</th><th>Section</th><th>Day</th><th>Time</th><th>Room</th><th>Status</th><th>Actions</th></tr>
            </thead>
            <tbody>
                <?php while ($row = $subjects->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($row['code']); ?></td>
                        <td><?php echo htmlspecialchars($row['name']); ?></td>
                        <td><?php echo htmlspecialchars($row['teacher_name'] ?: 'Unassigned'); ?></td>
                        <td><?php echo htmlspecialchars($row['section_name']); ?></td>
                        <td><?php echo htmlspecialchars($row['day_of_week']); ?></td>
                        <td><?php echo formatTime($row['start_time']); ?><?php echo $row['end_time'] ? ' - ' . formatTime($row['end_time']) : ''; ?></td>
                        <td><?php echo htmlspecialchars($row['room'] ?: '—'); ?></td>
                        <td><?php echo badgeStatus($row['status']); ?></td>
                        <td>
                            <button class="btn btn-sm btn-outline-primary btn-edit-subject" data-data='<?php echo json_encode($row); ?>'>Edit</button>
                            <form method="post" class="d-inline-block" onsubmit="return confirm('Delete this subject?');">
                                <input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>">
                                <input type="hidden" name="action" value="delete_subject">
                                <input type="hidden" name="subject_id" value="<?php echo $row['id']; ?>">
                                <button class="btn btn-sm btn-outline-danger">Delete</button>
                            </form>
                        </td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>
</div>
</div>
<div class="modal fade" id="subjectModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-4">
            <div class="modal-header">
                <h5 class="modal-title">Subject Form</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="post">
                <input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>">
                <input type="hidden" name="action" value="save_subject">
                <input type="hidden" name="id" id="subjectIdField">
                <div class="modal-body row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Subject Code</label>
                        <input type="text" class="form-control" name="code" id="subjectCodeField" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Subject Name</label>
                        <input type="text" class="form-control" name="name" id="subjectNameField" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Teacher</label>
                        <select class="form-select" name="teacher_id" id="subjectTeacherField">
                            <option value="0">Unassigned</option>
                            <?php while ($teacher = $teachers->fetch_assoc()): ?>
                                <option value="<?php echo $teacher['id']; ?>"><?php echo htmlspecialchars($teacher['first_name'] . ' ' . $teacher['last_name']); ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Section</label>
                        <select class="form-select" name="section_id" id="subjectSectionField" required>
                            <?php while ($section = $sections->fetch_assoc()): ?>
                                <option value="<?php echo $section['id']; ?>"><?php echo htmlspecialchars($section['year_level'] . ' - ' . $section['section_name']); ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Day of Week</label>
                        <select class="form-select" name="day_of_week" id="subjectDayField">
                            <?php foreach (['Mon','Tue','Wed','Thu','Fri','Sat','Sun'] as $day): ?>
                                <option value="<?php echo $day; ?>"><?php echo $day; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Start Time</label>
                        <input type="time" class="form-control" name="start_time" id="subjectStartTimeField" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">End Time</label>
                        <input type="time" class="form-control" name="end_time" id="subjectEndTimeField">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Room</label>
                        <input type="text" class="form-control" name="room" id="subjectRoomField" placeholder="e.g. IT Lab 1">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Credit Units</label>
                        <input type="number" min="0" class="form-control" name="credit_units" id="subjectCreditUnitsField">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Status</label>
                        <select class="form-select" name="status" id="subjectStatusField">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Important Note</label>
                        <textarea class="form-control" name="important_note" id="subjectNoteField" rows="2" placeholder="Shown to students on the subject page"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Subject</button>
                </div>
            </form>
        </div>
    </div>
</div>
<script>
const subjectModal = new bootstrap.Modal(document.getElementById('subjectModal'));
document.querySelectorAll('.btn-edit-subject').forEach(btn => {
    btn.addEventListener('click', () => {
        const data = JSON.parse(btn.getAttribute('data-data'));
        document.getElementById('subjectIdField').value = data.id;
        document.getElementById('subjectCodeField').value = data.code;
        document.getElementById('subjectNameField').value = data.name;
        document.getElementById('subjectTeacherField').value = data.teacher_id || '0';
        document.getElementById('subjectSectionField').value = data.section_id;
        document.getElementById('subjectDayField').value = data.day_of_week;
        document.getElementById('subjectStartTimeField').value = data.start_time;
        document.getElementById('subjectEndTimeField').value = data.end_time || '';
        document.getElementById('subjectRoomField').value = data.room || '';
        document.getElementById('subjectCreditUnitsField').value = data.credit_units || '';
        document.getElementById('subjectNoteField').value = data.important_note || '';
        document.getElementById('subjectStatusField').value = data.status;
        subjectModal.show();
    });
});
$(document).ready(function () {
    $('#subjectsTable').DataTable({ responsive: true });
});
</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
