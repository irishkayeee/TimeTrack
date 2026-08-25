<?php
require_once __DIR__ . '/../includes/functions.php';
requireRole(['admin', 'superadmin']);
$pageTitle = 'Manage Students';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        flash('Invalid form submission.', 'danger');
        redirect('students.php');
    }
    if ($_POST['action'] === 'save_student') {
        $result = saveStudentRecord($mysqli, $_POST, $_FILES, null);
        if ($result['credentials']) {
            flashCredentials($result['credentials']['username'], $result['credentials']['password']);
        } else {
            flash($result['message'], $result['type']);
        }
        redirect('students.php');
    }
    if ($_POST['action'] === 'delete_student' && !empty($_POST['student_id'])) {
        $sid = intval($_POST['student_id']);
        deleteStudentRecord($mysqli, $sid, null);
        flash('Student record deleted.', 'success');
        redirect('students.php');
    }
    if ($_POST['action'] === 'create_student_credentials' && !empty($_POST['student_id'])) {
        $sid = intval($_POST['student_id']);
        $stmt = $mysqli->prepare('SELECT student_id, user_id, email FROM students WHERE id = ?');
        $stmt->bind_param('i', $sid);
        $stmt->execute();
        $stmt->bind_result($studentCode, $linkedUserId, $studentEmail);
        if ($stmt->fetch()) {
            $stmt->close();
            if ($linkedUserId) {
                flash('A login already exists for this student. Password reset is disabled once a login is created.', 'danger');
            } else {
                $plainPassword = '';
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
        }
        redirect('students.php');
    }
    if ($_POST['action'] === 'import_students' && !empty($_FILES['csv_file']['tmp_name'])) {
        $file = fopen($_FILES['csv_file']['tmp_name'], 'r');
        $row = 0;
        while (($data = fgetcsv($file, 1000, ',')) !== false) {
            $row++;
            if ($row === 1) {
                continue;
            }
            $studentId = sanitize($data[0] ?? '');
            $firstName = sanitize($data[1] ?? '');
            $lastName = sanitize($data[2] ?? '');
            $gender = sanitize($data[3] ?? '');
            $birthday = sanitize($data[4] ?? '');
            $courseId = intval($data[5] ?? 0);
            $yearLevel = sanitize($data[6] ?? '');
            $sectionId = intval($data[7] ?? 0);
            $guardian = sanitize($data[8] ?? '');
            $phone = sanitize($data[9] ?? '');
            $email = sanitize($data[10] ?? '');
            $status = sanitize($data[11] ?? 'active');
            if (!$studentId) {
                $studentId = 'S' . time() . rand(10,99);
            }
            $qrToken = $studentId;
            $stmt = $mysqli->prepare('INSERT INTO students (student_id, first_name, last_name, gender, birthday, course_id, year_level, section_id, guardian_name, phone, email, status, qr_code, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())');
            $stmt->bind_param('sssssiissssss', $studentId, $firstName, $lastName, $gender, $birthday, $courseId, $yearLevel, $sectionId, $guardian, $phone, $email, $status, $qrToken);
            $stmt->execute();
            $stmt->close();
        }
        fclose($file);
        flash('Student list imported successfully.', 'success');
        redirect('students.php');
    }
    if ($_POST['action'] === 'export_students') {
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="students_export_' . date('Ymd') . '.csv"');
        $output = fopen('php://output', 'w');
        fputcsv($output, ['Student ID','First Name','Last Name','Gender','Birthday','Course ID','Year Level','Section ID','Guardian','Phone','Email','Status']);
        $result = $mysqli->query('SELECT student_id, first_name, last_name, gender, birthday, course_id, year_level, section_id, guardian_name, phone, email, status FROM students');
        while ($row = $result->fetch_assoc()) {
            fputcsv($output, $row);
        }
        fclose($output);
        exit;
    }
    if ($_POST['action'] === 'export_students_pdf') {
        $result = $mysqli->query('SELECT s.student_id, s.first_name, s.last_name, c.code AS course_code, sec.section_name, s.year_level, s.status FROM students s LEFT JOIN courses c ON s.course_id = c.id LEFT JOIN sections sec ON s.section_id = sec.id ORDER BY s.last_name');
        $rows = [];
        while ($row = $result->fetch_assoc()) {
            $rows[] = [$row['student_id'], $row['first_name'] . ' ' . $row['last_name'], $row['course_code'] ?: '-', $row['year_level'] ?: '-', $row['section_name'] ?: '-', ucfirst($row['status'])];
        }
        $pdf = generateSimpleTablePdf('Student List', ['ID', 'Name', 'Course', 'Year', 'Section', 'Status'], $rows, [80, 180, 90, 60, 90, 80]);
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="students_export_' . date('Ymd') . '.pdf"');
        header('Content-Length: ' . strlen($pdf));
        echo $pdf;
        exit;
    }
}

$courses = $mysqli->query('SELECT id, code, name FROM courses ORDER BY name');
$sections = $mysqli->query('SELECT id, section_name FROM sections ORDER BY section_name');
$students = $mysqli->query('SELECT s.*, c.code AS course_code, sec.section_name FROM students s LEFT JOIN courses c ON s.course_id = c.id LEFT JOIN sections sec ON s.section_id = sec.id ORDER BY s.created_at DESC');
$newCredentials = flashCredentialsMessage();
require_once __DIR__ . '/../includes/admin_header.php';
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
<div class="card rounded-4 shadow-sm p-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4>Student Management</h4>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#studentModal">Add Student</button>
    </div>
    <div class="table-responsive">
        <table class="table table-hover" id="studentsTable">
            <thead class="table-light">
                <tr>
                    <th>Photo</th>
                    <th>ID</th>
                    <th>Name</th>
                    <th>Course</th>
                    <th>Section</th>
                    <th>Status</th>
                    <th>Created</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($row = $students->fetch_assoc()): ?>
                    <tr>
                        <td>
                            <?php if (!empty($row['photo'])): ?>
                                <img src="../<?php echo htmlspecialchars($row['photo']); ?>" class="table-avatar" alt="">
                            <?php else: ?>
                                <span class="table-avatar table-avatar-fallback"><i class="fa-solid fa-user"></i></span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo htmlspecialchars($row['student_id']); ?></td>
                        <td><?php echo htmlspecialchars($row['first_name'] . ' ' . $row['last_name']); ?></td>
                        <td><?php echo htmlspecialchars($row['course_code']); ?></td>
                        <td><?php echo htmlspecialchars($row['section_name']); ?></td>
                        <td><?php echo badgeStatus($row['status']); ?></td>
                        <td><?php echo formatDateTime($row['created_at']); ?></td>
                        <td>
                            <div class="d-flex align-items-center gap-1">
                                <button class="btn btn-sm btn-outline-primary btn-icon btn-edit" data-data='<?php echo json_encode($row); ?>' title="Edit"><i class="fa-solid fa-pen"></i></button>
                                <form method="post" class="d-inline-block" onsubmit="return confirm('Delete this student?');">
                                    <input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>">
                                    <input type="hidden" name="action" value="delete_student">
                                    <input type="hidden" name="student_id" value="<?php echo $row['id']; ?>">
                                    <button class="btn btn-sm btn-outline-danger btn-icon" title="Delete"><i class="fa-solid fa-trash"></i></button>
                                </form>
                                <?php if (!$row['user_id']): ?>
                                    <form method="post" class="d-inline-block" onsubmit="return confirm('Create a login for this student?');">
                                        <input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>">
                                        <input type="hidden" name="action" value="create_student_credentials">
                                        <input type="hidden" name="student_id" value="<?php echo $row['id']; ?>">
                                        <button class="btn btn-sm btn-outline-secondary">Create Login</button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
    <div class="row mt-4 align-items-stretch">
        <div class="col-md-6">
            <form method="post" enctype="multipart/form-data" class="card rounded-4 p-3 shadow-sm h-100 d-flex flex-column">
                <input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>">
                <input type="hidden" name="action" value="import_students">
                <h6>Import Students CSV</h6>
                <div class="mb-3">
                    <input type="file" class="form-control" name="csv_file" accept=".csv" required>
                </div>
                <button class="btn btn-success mt-auto">Upload CSV</button>
            </form>
        </div>
        <div class="col-md-6">
            <div class="card rounded-4 p-3 shadow-sm h-100 d-flex flex-column">
                <h6>Export Students</h6>
                <div class="mt-3 d-flex flex-column gap-2">
                    <form method="post">
                        <input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>">
                        <input type="hidden" name="action" value="export_students_pdf">
                        <button class="btn btn-outline-primary w-100">Download PDF</button>
                    </form>
                    <form method="post">
                        <input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>">
                        <input type="hidden" name="action" value="export_students">
                        <button class="btn btn-outline-primary w-100">Download CSV</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
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
                            <option value="0">Unassigned</option>
                            <?php while ($section = $sections->fetch_assoc()): ?>
                                <option value="<?php echo $section['id']; ?>"><?php echo htmlspecialchars($section['section_name']); ?></option>
                            <?php endwhile; ?>
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
document.addEventListener('DOMContentLoaded', function () {
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

$('#studentsTable').DataTable({ responsive: true });
});
</script>
<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>