<?php
require_once __DIR__ . '/../includes/functions.php';
requireRole(['admin', 'superadmin']);
$pageTitle = 'Sections';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        flash('Invalid request.', 'danger');
        redirect('sections.php');
    }
    if ($_POST['action'] === 'save_section') {
        $id = intval($_POST['id'] ?? 0);
        $yearLevel = sanitize($_POST['year_level'] ?? '');
        $sectionName = sanitize($_POST['section_name'] ?? '');
        $courseId = intval($_POST['course_id'] ?? 0);
        $adviserId = intval($_POST['adviser_id'] ?? 0);
        $adviserIdParam = $adviserId ?: null;
        if ($id) {
            $stmt = $mysqli->prepare('UPDATE sections SET year_level = ?, section_name = ?, course_id = ?, adviser_id = ? WHERE id = ?');
            $stmt->bind_param('ssiii', $yearLevel, $sectionName, $courseId, $adviserIdParam, $id);
            $stmt->execute();
            $stmt->close();
            flash('Section updated.', 'success');
        } else {
            $stmt = $mysqli->prepare('INSERT INTO sections (year_level, section_name, course_id, adviser_id, created_at) VALUES (?, ?, ?, ?, NOW())');
            $stmt->bind_param('ssii', $yearLevel, $sectionName, $courseId, $adviserIdParam);
            $stmt->execute();
            $stmt->close();
            flash('Section created.', 'success');
        }
        redirect('sections.php');
    }
    if ($_POST['action'] === 'delete_section' && !empty($_POST['section_id'])) {
        $id = intval($_POST['section_id']);
        $stmt = $mysqli->prepare('DELETE FROM sections WHERE id = ?');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $stmt->close();
        flash('Section removed.', 'success');
        redirect('sections.php');
    }
}
$courses = $mysqli->query('SELECT id, code FROM courses ORDER BY code');
$teachers = $mysqli->query("SELECT id, first_name, last_name FROM teachers WHERE status = 'active' ORDER BY first_name");
$sections = $mysqli->query('SELECT sec.*, c.code AS course_code, CONCAT(t.first_name, " ", t.last_name) AS adviser_name FROM sections sec LEFT JOIN courses c ON sec.course_id = c.id LEFT JOIN teachers t ON sec.adviser_id = t.id ORDER BY sec.created_at DESC');
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/admin_nav.php';
?>
<div class="card rounded-4 shadow-sm p-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4>Section Management</h4>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#sectionModal">Add Section</button>
    </div>
    <div class="table-responsive">
        <table class="table table-hover" id="sectionsTable">
            <thead class="table-light">
                <tr><th>Year</th><th>Section</th><th>Course</th><th>Adviser</th><th>Actions</th></tr>
            </thead>
            <tbody>
                <?php while ($row = $sections->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($row['year_level']); ?></td>
                        <td><?php echo htmlspecialchars($row['section_name']); ?></td>
                        <td><?php echo htmlspecialchars($row['course_code']); ?></td>
                        <td><?php echo htmlspecialchars($row['adviser_name'] ?: 'Unassigned'); ?></td>
                        <td>
                            <button class="btn btn-sm btn-outline-primary btn-edit-section" data-data='<?php echo json_encode($row); ?>'>Edit</button>
                            <form method="post" class="d-inline-block" onsubmit="return confirm('Delete this section?');">
                                <input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>">
                                <input type="hidden" name="action" value="delete_section">
                                <input type="hidden" name="section_id" value="<?php echo $row['id']; ?>">
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
<div class="modal fade" id="sectionModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-4">
            <div class="modal-header">
                <h5 class="modal-title">Section Form</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="post">
                <input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>">
                <input type="hidden" name="action" value="save_section">
                <input type="hidden" name="id" id="sectionIdField">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Year Level</label>
                        <input type="text" class="form-control" name="year_level" id="sectionYearField" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Section Name</label>
                        <input type="text" class="form-control" name="section_name" id="sectionNameField" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Course</label>
                        <select class="form-select" name="course_id" id="sectionCourseField">
                            <option value="0">Select Course</option>
                            <?php while ($course = $courses->fetch_assoc()): ?>
                                <option value="<?php echo $course['id']; ?>"><?php echo htmlspecialchars($course['code']); ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Adviser</label>
                        <select class="form-select" name="adviser_id" id="sectionAdviserField">
                            <option value="0">Unassigned</option>
                            <?php while ($teacher = $teachers->fetch_assoc()): ?>
                                <option value="<?php echo $teacher['id']; ?>"><?php echo htmlspecialchars($teacher['first_name'] . ' ' . $teacher['last_name']); ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Section</button>
                </div>
            </form>
        </div>
    </div>
</div>
<script>
const sectionModal = new bootstrap.Modal(document.getElementById('sectionModal'));
document.querySelectorAll('.btn-edit-section').forEach(btn => {
    btn.addEventListener('click', () => {
        const data = JSON.parse(btn.getAttribute('data-data'));
        document.getElementById('sectionIdField').value = data.id;
        document.getElementById('sectionYearField').value = data.year_level;
        document.getElementById('sectionNameField').value = data.section_name;
        document.getElementById('sectionCourseField').value = data.course_id;
        document.getElementById('sectionAdviserField').value = data.adviser_id || '0';
        sectionModal.show();
    });
});
$(document).ready(function () {
    $('#sectionsTable').DataTable({ responsive: true });
});
</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>