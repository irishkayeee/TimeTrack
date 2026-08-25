<?php
require_once __DIR__ . '/../includes/functions.php';
requireRole(['admin', 'superadmin']);
$pageTitle = 'Courses';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        flash('Invalid request.', 'danger');
        redirect('courses.php');
    }
    if ($_POST['action'] === 'save_course') {
        $id = intval($_POST['id'] ?? 0);
        $code = sanitize($_POST['code'] ?? '');
        $name = sanitize($_POST['name'] ?? '');
        $description = sanitize($_POST['description'] ?? '');
        if ($id) {
            $stmt = $mysqli->prepare('UPDATE courses SET code = ?, name = ?, description = ? WHERE id = ?');
            $stmt->bind_param('sssi', $code, $name, $description, $id);
            $stmt->execute();
            $stmt->close();
            flash('Course updated.', 'success');
        } else {
            $stmt = $mysqli->prepare('INSERT INTO courses (code, name, description, created_at) VALUES (?, ?, ?, NOW())');
            $stmt->bind_param('sss', $code, $name, $description);
            $stmt->execute();
            $stmt->close();
            flash('Course created.', 'success');
        }
        redirect('courses.php');
    }
    if ($_POST['action'] === 'delete_course' && !empty($_POST['course_id'])) {
        $id = intval($_POST['course_id']);
        $stmt = $mysqli->prepare('DELETE FROM courses WHERE id = ?');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $stmt->close();
        flash('Course deleted.', 'success');
        redirect('courses.php');
    }
}
$courses = $mysqli->query('SELECT * FROM courses ORDER BY code');
require_once __DIR__ . '/../includes/admin_header.php';
?>
<div class="card rounded-4 shadow-sm p-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4>Course Management</h4>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#courseModal">Add Course</button>
    </div>
    <div class="table-responsive">
        <table class="table table-hover" id="coursesTable">
            <thead class="table-light">
                <tr><th>Code</th><th>Name</th><th>Description</th><th>Actions</th></tr>
            </thead>
            <tbody>
                <?php while ($row = $courses->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($row['code']); ?></td>
                        <td><?php echo htmlspecialchars($row['name']); ?></td>
                        <td><?php echo htmlspecialchars($row['description']); ?></td>
                        <td>
                            <button class="btn btn-sm btn-outline-primary btn-edit-course" data-data='<?php echo json_encode($row); ?>'>Edit</button>
                            <form method="post" class="d-inline-block" onsubmit="return confirm('Delete this course?');">
                                <input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>">
                                <input type="hidden" name="action" value="delete_course">
                                <input type="hidden" name="course_id" value="<?php echo $row['id']; ?>">
                                <button class="btn btn-sm btn-outline-danger">Delete</button>
                            </form>
                        </td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>
<div class="modal fade" id="courseModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-4">
            <div class="modal-header">
                <h5 class="modal-title">Course Form</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="post">
                <input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>">
                <input type="hidden" name="action" value="save_course">
                <input type="hidden" name="id" id="courseIdField">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Course Code</label>
                        <input type="text" class="form-control" name="code" id="courseCodeField" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Course Name</label>
                        <input type="text" class="form-control" name="name" id="courseNameField" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea class="form-control" name="description" id="courseDescriptionField" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Course</button>
                </div>
            </form>
        </div>
    </div>
</div>
<script>
const courseModal = new bootstrap.Modal(document.getElementById('courseModal'));
document.querySelectorAll('.btn-edit-course').forEach(btn => {
    btn.addEventListener('click', () => {
        const data = JSON.parse(btn.getAttribute('data-data'));
        document.getElementById('courseIdField').value = data.id;
        document.getElementById('courseCodeField').value = data.code;
        document.getElementById('courseNameField').value = data.name;
        document.getElementById('courseDescriptionField').value = data.description;
        courseModal.show();
    });
});
$(document).ready(function () {
    $('#coursesTable').DataTable({ responsive: true });
});
</script>
<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>