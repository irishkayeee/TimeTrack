<?php
require_once __DIR__ . '/../includes/functions.php';
requireRole(['teacher']);
$pageTitle = 'Students';
$pageSubtitle = 'Manage students in your assigned sections.';

$teacherId = currentTeacherId();
if ($teacherId === false) {
    flash('Your teacher profile is not set up. Contact an administrator.', 'danger');
    redirect('../dashboard.php');
}

$sectionStmt = $mysqli->prepare('SELECT DISTINCT sec.id, sec.section_name, sec.year_level FROM subjects sub JOIN sections sec ON sub.section_id = sec.id WHERE sub.teacher_id = ? ORDER BY sec.section_name');
$sectionStmt->bind_param('i', $teacherId);
$sectionStmt->execute();
$sectionResult = $sectionStmt->get_result();
$allowedSections = [];
$sectionRows = [];
while ($row = $sectionResult->fetch_assoc()) {
    $allowedSections[] = (int) $row['id'];
    $sectionRows[] = $row;
}
$sectionStmt->close();

$filterSubjectId = intval($_GET['subject_id'] ?? 0);
$filterSubjectName = null;
$displaySections = $allowedSections;
if ($filterSubjectId) {
    $subjStmt = $mysqli->prepare('SELECT code, name, section_id FROM subjects WHERE id = ? AND teacher_id = ?');
    $subjStmt->bind_param('ii', $filterSubjectId, $teacherId);
    $subjStmt->execute();
    $subjRow = $subjStmt->get_result()->fetch_assoc();
    $subjStmt->close();
    if ($subjRow) {
        $filterSubjectName = $subjRow['code'] . ' - ' . $subjRow['name'];
        $displaySections = [(int) $subjRow['section_id']];
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        flash('Invalid form submission.', 'danger');
        redirect('students.php');
    }
    if ($_POST['action'] === 'save_student') {
        $result = saveStudentRecord($mysqli, $_POST, $_FILES, $allowedSections);
        if ($result['credentials']) {
            flashCredentials($result['credentials']['username'], $result['credentials']['password']);
        } else {
            flash($result['message'], $result['type']);
        }
        redirect('students.php');
    }
    if ($_POST['action'] === 'delete_student' && !empty($_POST['student_id'])) {
        $sid = intval($_POST['student_id']);
        if (deleteStudentRecord($mysqli, $sid, $allowedSections)) {
            flash('Student record deleted.', 'success');
        } else {
            flash('You can only manage students in your own sections.', 'danger');
        }
        redirect('students.php');
    }
    if (in_array($_POST['action'], ['create_student_credentials', 'regenerate_student_credentials']) && !empty($_POST['student_id'])) {
        $sid = intval($_POST['student_id']);
        $stmt = $mysqli->prepare('SELECT student_id, user_id, email, section_id FROM students WHERE id = ?');
        $stmt->bind_param('i', $sid);
        $stmt->execute();
        $stmt->bind_result($studentCode, $linkedUserId, $studentEmail, $studentSectionId);
        if ($stmt->fetch() && in_array((int) $studentSectionId, $allowedSections)) {
            $stmt->close();
            $plainPassword = '';
            if ($linkedUserId) {
                regenerateCredentials($mysqli, $linkedUserId, $plainPassword);
                flashCredentials($studentCode, $plainPassword);
            } else {
                $userId = createUserAccountFor($mysqli, 'student', $studentCode, $studentEmail, $plainPassword);
                if ($userId) {
                    $stmt2 = $mysqli->prepare('UPDATE students SET user_id = ? WHERE id = ?');
                    $stmt2->bind_param('ii', $userId, $sid);
                    $stmt2->execute();
                    $stmt2->close();
                    flashCredentials($studentCode, $plainPassword);
                } else {
                    flash('Unable to create a login account (email may already be in use).', 'danger');
                }
            }
        } else {
            $stmt->close();
            flash('You can only manage students in your own sections.', 'danger');
        }
        redirect('students.php');
    }
}

$newCredentials = flashCredentialsMessage();
$courses = $mysqli->query('SELECT id, code, name FROM courses ORDER BY name');
$students = [];
if ($displaySections) {
    $placeholders = implode(',', array_fill(0, count($displaySections), '?'));
    $types = str_repeat('i', count($displaySections));
    $stmt = $mysqli->prepare("SELECT s.*, c.code AS course_code, sec.section_name FROM students s LEFT JOIN courses c ON s.course_id = c.id LEFT JOIN sections sec ON s.section_id = sec.id WHERE s.section_id IN ($placeholders) ORDER BY s.created_at DESC");
    $stmt->bind_param($types, ...$displaySections);
    $stmt->execute();
    $students = $stmt->get_result();
}
require_once __DIR__ . '/../includes/teacher_header.php';
?>
<?php if ($newCredentials): ?>
    <div class="alert alert-success rounded-4">
        <strong>Login credentials generated.</strong> Share these with the student now — the password will not be shown again.
        <div class="mt-2">
            <span class="me-3">Username: <code><?php echo htmlspecialchars($newCredentials['username']); ?></code></span>
            <span>Password: <code><?php echo htmlspecialchars($newCredentials['password']); ?></code></span>
        </div>
    </div>
<?php endif; ?>
<div class="card p-4">
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <div>
            <h5 class="mb-0">My Students</h5>
            <?php if ($filterSubjectName): ?>
                <p class="text-muted small mb-0">Filtered by <?php echo htmlspecialchars($filterSubjectName); ?> — <a href="students.php">clear filter</a></p>
            <?php endif; ?>
        </div>
        <?php if ($allowedSections): ?>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#studentModal">Add Student</button>
        <?php endif; ?>
    </div>
    <?php if (!$allowedSections): ?>
        <div class="alert alert-info">You have no assigned subjects yet, so there are no students to manage. Ask an admin to assign you a subject.</div>
    <?php else: ?>
    <div class="table-responsive">
        <table class="table table-hover" id="myStudentsTable">
            <thead class="table-light">
                <tr>
                    <th>QR</th>
                    <th>ID</th>
                    <th>Name</th>
                    <th>Course</th>
                    <th>Section</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($row = $students->fetch_assoc()): ?>
                    <tr>
                        <td><a href="../admin/qr-generator.php?student_id=<?php echo $row['id']; ?>" class="btn btn-sm btn-outline-secondary">QR</a></td>
                        <td><?php echo htmlspecialchars($row['student_id']); ?></td>
                        <td><?php echo htmlspecialchars($row['first_name'] . ' ' . $row['last_name']); ?></td>
                        <td><?php echo htmlspecialchars($row['course_code']); ?></td>
                        <td><?php echo htmlspecialchars($row['section_name']); ?></td>
                        <td><?php echo badgeStatus($row['status']); ?></td>
                        <td>
                            <button class="btn btn-sm btn-outline-primary btn-edit" data-data='<?php echo json_encode($row); ?>'>Edit</button>
                            <form method="post" class="d-inline-block" onsubmit="return confirm('<?php echo $row['user_id'] ? 'Reset this student\'s password?' : 'Create a login for this student?'; ?>');">
                                <input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>">
                                <input type="hidden" name="action" value="<?php echo $row['user_id'] ? 'regenerate_student_credentials' : 'create_student_credentials'; ?>">
                                <input type="hidden" name="student_id" value="<?php echo $row['id']; ?>">
                                <button class="btn btn-sm btn-outline-secondary"><?php echo $row['user_id'] ? 'Reset Password' : 'Create Login'; ?></button>
                            </form>
                            <form method="post" class="d-inline-block" onsubmit="return confirm('Delete this student?');">
                                <input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>">
                                <input type="hidden" name="action" value="delete_student">
                                <input type="hidden" name="student_id" value="<?php echo $row['id']; ?>">
                                <button class="btn btn-sm btn-outline-danger">Delete</button>
                            </form>
                        </td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<div class="modal fade" id="studentModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content rounded-4">
            <div class="modal-header">
                <h5 class="modal-title">Student Form</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="post" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>">
                <input type="hidden" name="action" value="save_student">
                <input type="hidden" name="id" id="studentIdField">
                <div class="modal-body row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Student Code</label>
                        <input type="text" class="form-control" name="student_id" id="studentCodeField">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">First Name</label>
                        <input type="text" class="form-control" name="first_name" id="firstNameField" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Last Name</label>
                        <input type="text" class="form-control" name="last_name" id="lastNameField" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Gender</label>
                        <select name="gender" id="genderField" class="form-select">
                            <option value="Male">Male</option>
                            <option value="Female">Female</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Birthday</label>
                        <input type="date" class="form-control" name="birthday" id="birthdayField" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Guardian</label>
                        <input type="text" class="form-control" name="guardian" id="guardianField">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Phone</label>
                        <input type="text" class="form-control" name="phone" id="phoneField">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Email</label>
                        <input type="email" class="form-control" name="email" id="emailField">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Course</label>
                        <select class="form-select" name="course_id" id="courseField">
                            <option value="0">Unassigned</option>
                            <?php while ($course = $courses->fetch_assoc()): ?>
                                <option value="<?php echo $course['id']; ?>"><?php echo htmlspecialchars($course['code'] . ' - ' . $course['name']); ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Year Level</label>
                        <input type="text" class="form-control" name="year_level" id="yearField">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Section</label>
                        <select class="form-select" name="section_id" id="sectionField">
                            <?php foreach ($sectionRows as $section): ?>
                                <option value="<?php echo $section['id']; ?>"><?php echo htmlspecialchars($section['year_level'] . ' - ' . $section['section_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Status</label>
                        <select class="form-select" name="status" id="statusField">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                    <div class="col-md-12">
                        <label class="form-label">Photo</label>
                        <input type="file" class="form-control" name="photo">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Student</button>
                </div>
            </form>
        </div>
    </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const editButtons = document.querySelectorAll('.btn-edit');
    const studentModal = new bootstrap.Modal(document.getElementById('studentModal'));
    editButtons.forEach(btn => {
        btn.addEventListener('click', () => {
            const data = JSON.parse(btn.getAttribute('data-data'));
            document.getElementById('studentIdField').value = data.id;
            document.getElementById('studentCodeField').value = data.student_id;
            document.getElementById('firstNameField').value = data.first_name;
            document.getElementById('lastNameField').value = data.last_name;
            document.getElementById('genderField').value = data.gender;
            document.getElementById('birthdayField').value = data.birthday;
            document.getElementById('guardianField').value = data.guardian_name;
            document.getElementById('phoneField').value = data.phone;
            document.getElementById('emailField').value = data.email;
            document.getElementById('courseField').value = data.course_id;
            document.getElementById('yearField').value = data.year_level;
            document.getElementById('sectionField').value = data.section_id;
            document.getElementById('statusField').value = data.status;
            studentModal.show();
        });
    });

    $('#myStudentsTable').DataTable({ responsive: true });
});
</script>
<?php require_once __DIR__ . '/../includes/teacher_footer.php'; ?>
