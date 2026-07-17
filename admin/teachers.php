<?php
require_once __DIR__ . '/../includes/functions.php';
requireRole(['admin', 'superadmin']);
$pageTitle = 'Manage Teachers';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        flash('Invalid request.', 'danger');
        redirect('teachers.php');
    }
    if ($_POST['action'] === 'save_teacher') {
        $id = intval($_POST['id'] ?? 0);
        $teacherId = sanitize($_POST['teacher_id'] ?? '');
        $firstName = sanitize($_POST['first_name'] ?? '');
        $lastName = sanitize($_POST['last_name'] ?? '');
        $subject = sanitize($_POST['subject'] ?? '');
        $sectionId = intval($_POST['section_id'] ?? 0);
        $phone = sanitize($_POST['phone'] ?? '');
        $email = sanitize($_POST['email'] ?? '');
        $status = sanitize($_POST['status'] ?? 'active');
        if ($id) {
            $stmt = $mysqli->prepare('UPDATE teachers SET teacher_id = ?, first_name = ?, last_name = ?, subject = ?, section_id = ?, phone = ?, email = ?, status = ? WHERE id = ?');
            $stmt->bind_param('ssssiissi', $teacherId, $firstName, $lastName, $subject, $sectionId, $phone, $email, $status, $id);
            $stmt->execute();
            $stmt->close();
            flash('Teacher updated successfully.', 'success');
        } else {
            $stmt = $mysqli->prepare('INSERT INTO teachers (teacher_id, first_name, last_name, subject, section_id, phone, email, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())');
            $stmt->bind_param('ssssiiss', $teacherId, $firstName, $lastName, $subject, $sectionId, $phone, $email, $status);
            $stmt->execute();
            $stmt->close();
            flash('Teacher added successfully.', 'success');
        }
        redirect('teachers.php');
    }
    if ($_POST['action'] === 'delete_teacher' && !empty($_POST['teacher_id'])) {
        $tid = intval($_POST['teacher_id']);
        $stmt = $mysqli->prepare('DELETE FROM teachers WHERE id = ?');
        $stmt->bind_param('i', $tid);
        $stmt->execute();
        $stmt->close();
        flash('Teacher removed.', 'success');
        redirect('teachers.php');
    }
}
$courses = $mysqli->query('SELECT id, code FROM courses ORDER BY code');
$sections = $mysqli->query('SELECT id, section_name FROM sections ORDER BY section_name');
$teachers = $mysqli->query('SELECT t.*, sec.section_name FROM teachers t LEFT JOIN sections sec ON t.section_id = sec.id ORDER BY t.created_at DESC');
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/admin_nav.php';
?>
<div class="card rounded-4 shadow-sm p-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4>Teacher Management</h4>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#teacherModal">Add Teacher</button>
    </div>
    <div class="table-responsive">
        <table class="table table-hover" id="teachersTable">
            <thead class="table-light">
                <tr>
                    <th>Teacher ID</th>
                    <th>Name</th>
                    <th>Subject</th>
                    <th>Section</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($row = $teachers->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($row['teacher_id']); ?></td>
                        <td><?php echo htmlspecialchars($row['first_name'] . ' ' . $row['last_name']); ?></td>
                        <td><?php echo htmlspecialchars($row['subject']); ?></td>
                        <td><?php echo htmlspecialchars($row['section_name']); ?></td>
                        <td><?php echo badgeStatus($row['status']); ?></td>
                        <td>
                            <button class="btn btn-sm btn-outline-primary btn-edit-teacher" data-data='<?php echo json_encode($row); ?>'>Edit</button>
                            <form method="post" class="d-inline-block" onsubmit="return confirm('Delete this teacher?');">
                                <input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>">
                                <input type="hidden" name="action" value="delete_teacher">
                                <input type="hidden" name="teacher_id" value="<?php echo $row['id']; ?>">
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
<div class="modal fade" id="teacherModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content rounded-4">
            <div class="modal-header">
                <h5 class="modal-title">Teacher Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="post">
                <input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>">
                <input type="hidden" name="action" value="save_teacher">
                <input type="hidden" name="id" id="teacherIdField">
                <div class="modal-body row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Teacher Code</label>
                        <input type="text" class="form-control" name="teacher_id" id="teacherCodeField" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Subject</label>
                        <input type="text" class="form-control" name="subject" id="subjectField" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">First Name</label>
                        <input type="text" class="form-control" name="first_name" id="teacherFirstNameField" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Last Name</label>
                        <input type="text" class="form-control" name="last_name" id="teacherLastNameField" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Email</label>
                        <input type="email" class="form-control" name="email" id="teacherEmailField">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Phone</label>
                        <input type="text" class="form-control" name="phone" id="teacherPhoneField">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Section</label>
                        <select class="form-select" name="section_id" id="teacherSectionField">
                            <option value="0">Unassigned</option>
                            <?php while ($section = $sections->fetch_assoc()): ?>
                                <option value="<?php echo $section['id']; ?>"><?php echo htmlspecialchars($section['section_name']); ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Status</label>
                        <select class="form-select" name="status" id="teacherStatusField">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Teacher</button>
                </div>
            </form>
        </div>
    </div>
</div>
<script>
const teacherModal = new bootstrap.Modal(document.getElementById('teacherModal'));
document.querySelectorAll('.btn-edit-teacher').forEach(btn => {
    btn.addEventListener('click', () => {
        const data = JSON.parse(btn.getAttribute('data-data'));
        document.getElementById('teacherIdField').value = data.id;
        document.getElementById('teacherCodeField').value = data.teacher_id;
        document.getElementById('subjectField').value = data.subject;
        document.getElementById('teacherFirstNameField').value = data.first_name;
        document.getElementById('teacherLastNameField').value = data.last_name;
        document.getElementById('teacherEmailField').value = data.email;
        document.getElementById('teacherPhoneField').value = data.phone;
        document.getElementById('teacherSectionField').value = data.section_id;
        document.getElementById('teacherStatusField').value = data.status;
        teacherModal.show();
    });
});
$(document).ready(function () {
    $('#teachersTable').DataTable({ responsive: true });
});
</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>