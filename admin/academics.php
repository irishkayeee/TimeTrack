<?php
require_once __DIR__ . '/../includes/functions.php';
requireRole(['admin', 'superadmin']);
$pageTitle = 'Academics';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        flash('Invalid form submission.', 'danger');
        redirect('academics.php');
    }

    // ---- Students ----
    if ($_POST['action'] === 'save_student') {
        $result = saveStudentRecord($mysqli, $_POST, $_FILES, null);
        if ($result['credentials']) {
            flashCredentials($result['credentials']['username'], $result['credentials']['password']);
        } else {
            flash($result['message'], $result['type']);
        }
        redirect('academics.php');
    }
    if ($_POST['action'] === 'delete_student' && !empty($_POST['student_id'])) {
        $sid = intval($_POST['student_id']);
        deleteStudentRecord($mysqli, $sid, null);
        flash('Student record deleted.', 'success');
        redirect('academics.php');
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
        redirect('academics.php');
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
                $studentId = 'S' . time() . rand(10, 99);
            }
            $qrToken = $studentId;
            $stmt = $mysqli->prepare('INSERT INTO students (student_id, first_name, last_name, gender, birthday, course_id, year_level, section_id, guardian_name, phone, email, status, qr_code, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())');
            $stmt->bind_param('sssssiissssss', $studentId, $firstName, $lastName, $gender, $birthday, $courseId, $yearLevel, $sectionId, $guardian, $phone, $email, $status, $qrToken);
            $stmt->execute();
            $stmt->close();
        }
        fclose($file);
        flash('Student list imported successfully.', 'success');
        redirect('academics.php');
    }
    if ($_POST['action'] === 'export_students') {
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="students_export_' . date('Ymd') . '.csv"');
        $output = fopen('php://output', 'w');
        fputcsv($output, ['Student ID', 'First Name', 'Last Name', 'Gender', 'Birthday', 'Course ID', 'Year Level', 'Section ID', 'Guardian', 'Phone', 'Email', 'Status']);
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

    // ---- Teachers ----
    if ($_POST['action'] === 'save_teacher') {
        $id = intval($_POST['id'] ?? 0);
        $teacherId = sanitize($_POST['teacher_id'] ?? '');
        $firstName = sanitize($_POST['first_name'] ?? '');
        $lastName = sanitize($_POST['last_name'] ?? '');
        $subject = sanitize($_POST['subject'] ?? '');
        $sectionId = intval($_POST['section_id'] ?? 0) ?: null;
        $phone = sanitize($_POST['phone'] ?? '');
        $email = sanitize($_POST['email'] ?? '');
        $status = sanitize($_POST['status'] ?? 'active');
        if ($id) {
            $stmt = $mysqli->prepare('UPDATE teachers SET teacher_id = ?, first_name = ?, last_name = ?, subject = ?, section_id = ?, phone = ?, email = ?, status = ? WHERE id = ?');
            $stmt->bind_param('ssssisssi', $teacherId, $firstName, $lastName, $subject, $sectionId, $phone, $email, $status, $id);
            $stmt->execute();
            $stmt->close();
            flash('Teacher updated successfully.', 'success');
        } else {
            $stmt = $mysqli->prepare('INSERT INTO teachers (teacher_id, first_name, last_name, subject, section_id, phone, email, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())');
            $stmt->bind_param('ssssisss', $teacherId, $firstName, $lastName, $subject, $sectionId, $phone, $email, $status);
            $stmt->execute();
            $stmt->close();
            $newTeacherId = $mysqli->insert_id;
            $plainPassword = '';
            $userId = createUserAccountFor($mysqli, 'teacher', $teacherId, $email, $plainPassword);
            if ($userId) {
                $stmt = $mysqli->prepare('UPDATE teachers SET user_id = ? WHERE id = ?');
                $stmt->bind_param('ii', $userId, $newTeacherId);
                $stmt->execute();
                $stmt->close();
                flashCredentials($teacherId, $plainPassword);
            } else {
                flash('Teacher added, but a login account could not be created (email may already be in use). Use "Create Login" to try again.', 'warning');
            }
        }
        redirect('academics.php');
    }
    if ($_POST['action'] === 'delete_teacher' && !empty($_POST['teacher_id'])) {
        $tid = intval($_POST['teacher_id']);
        $stmt = $mysqli->prepare('SELECT user_id FROM teachers WHERE id = ?');
        $stmt->bind_param('i', $tid);
        $stmt->execute();
        $stmt->bind_result($linkedUserId);
        $stmt->fetch();
        $stmt->close();
        if ($linkedUserId) {
            $stmt = $mysqli->prepare("UPDATE users SET status = 'inactive' WHERE id = ?");
            $stmt->bind_param('i', $linkedUserId);
            $stmt->execute();
            $stmt->close();
        }
        $stmt = $mysqli->prepare('DELETE FROM teachers WHERE id = ?');
        $stmt->bind_param('i', $tid);
        $stmt->execute();
        $stmt->close();
        flash('Teacher removed.', 'success');
        redirect('academics.php');
    }
    if ($_POST['action'] === 'create_teacher_credentials' && !empty($_POST['teacher_id'])) {
        $tid = intval($_POST['teacher_id']);
        $stmt = $mysqli->prepare('SELECT teacher_id, user_id, email FROM teachers WHERE id = ?');
        $stmt->bind_param('i', $tid);
        $stmt->execute();
        $stmt->bind_result($teacherCode, $linkedUserId, $teacherEmail);
        if ($stmt->fetch()) {
            $stmt->close();
            if ($linkedUserId) {
                flash('A login already exists for this teacher. Password reset is disabled once a login is created.', 'danger');
            } else {
                $plainPassword = '';
                $userId = createUserAccountFor($mysqli, 'teacher', $teacherCode, $teacherEmail, $plainPassword);
                if ($userId) {
                    $stmt2 = $mysqli->prepare('UPDATE teachers SET user_id = ? WHERE id = ?');
                    $stmt2->bind_param('ii', $userId, $tid);
                    $stmt2->execute();
                    $stmt2->close();
                    flashCredentials($teacherCode, $plainPassword);
                } else {
                    flash('Unable to create a login account (email may already be in use).', 'danger');
                }
            }
        } else {
            $stmt->close();
        }
        redirect('academics.php');
    }

    // ---- Courses ----
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
        redirect('academics.php');
    }
    if ($_POST['action'] === 'delete_course' && !empty($_POST['course_id'])) {
        $id = intval($_POST['course_id']);
        $stmt = $mysqli->prepare('DELETE FROM courses WHERE id = ?');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $stmt->close();
        flash('Course deleted.', 'success');
        redirect('academics.php');
    }

    // ---- Sections ----
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
        redirect('academics.php');
    }
    if ($_POST['action'] === 'delete_section' && !empty($_POST['section_id'])) {
        $id = intval($_POST['section_id']);
        $stmt = $mysqli->prepare('DELETE FROM sections WHERE id = ?');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $stmt->close();
        flash('Section removed.', 'success');
        redirect('academics.php');
    }

    // ---- Subjects ----
    if ($_POST['action'] === 'save_subject') {
        $id = intval($_POST['id'] ?? 0);
        $code = sanitize($_POST['code'] ?? '');
        $name = sanitize($_POST['name'] ?? '');
        $teacherId = intval($_POST['teacher_id'] ?? 0);
        $sectionId = intval($_POST['section_id'] ?? 0);
        $allowedDays = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
        $postedDays = is_array($_POST['day_of_week'] ?? null) ? $_POST['day_of_week'] : [$_POST['day_of_week'] ?? 'Mon'];
        $days = array_values(array_intersect($allowedDays, array_map('sanitize', $postedDays)));
        if (!$days) {
            $days = ['Mon'];
        }
        $startTime = sanitize($_POST['start_time'] ?? '07:30');
        $endTime = sanitize($_POST['end_time'] ?? '');
        $endTimeParam = $endTime ?: null;
        $room = sanitize($_POST['room'] ?? '');
        $creditUnits = intval($_POST['credit_units'] ?? 0) ?: null;
        $importantNote = sanitize($_POST['important_note'] ?? '');
        $importantNoteParam = $importantNote ?: null;
        $status = sanitize($_POST['status'] ?? 'active');
        $teacherIdParam = $teacherId ?: null;

        $previousTeacherId = null;
        if ($id) {
            $prevStmt = $mysqli->prepare('SELECT teacher_id FROM subjects WHERE id = ?');
            $prevStmt->bind_param('i', $id);
            $prevStmt->execute();
            $prevStmt->bind_result($previousTeacherId);
            $prevStmt->fetch();
            $prevStmt->close();
        }
        $isNewTeacherAssignment = $teacherIdParam && (int) $previousTeacherId !== (int) $teacherIdParam;

        if ($id) {
            $firstDay = $days[0];
            $stmt = $mysqli->prepare('UPDATE subjects SET code = ?, name = ?, teacher_id = ?, section_id = ?, day_of_week = ?, start_time = ?, end_time = ?, room = ?, credit_units = ?, important_note = ?, status = ? WHERE id = ?');
            $stmt->bind_param('ssiissssissi', $code, $name, $teacherIdParam, $sectionId, $firstDay, $startTime, $endTimeParam, $room, $creditUnits, $importantNoteParam, $status, $id);
            $stmt->execute();
            $stmt->close();
            $extraDays = array_slice($days, 1);
        } else {
            $extraDays = $days;
        }

        foreach ($extraDays as $day) {
            $stmt = $mysqli->prepare('INSERT INTO subjects (code, name, teacher_id, section_id, day_of_week, start_time, end_time, room, credit_units, important_note, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())');
            $stmt->bind_param('ssiissssiss', $code, $name, $teacherIdParam, $sectionId, $day, $startTime, $endTimeParam, $room, $creditUnits, $importantNoteParam, $status);
            $stmt->execute();
            $stmt->close();
        }

        if ($isNewTeacherAssignment) {
            notifyTeacherOfSubjectAssignment($mysqli, $teacherIdParam, $code, $name, $days, $startTime);
        }

        flash($id ? 'Subject updated.' : (count($days) > 1 ? 'Subject created for ' . count($days) . ' days.' : 'Subject created.'), 'success');
        redirect('academics.php');
    }
    if ($_POST['action'] === 'delete_subject' && !empty($_POST['subject_id'])) {
        $id = intval($_POST['subject_id']);
        $stmt = $mysqli->prepare('DELETE FROM subjects WHERE id = ?');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $stmt->close();
        flash('Subject removed.', 'success');
        redirect('academics.php');
    }
}

$courseOptions = $mysqli->query('SELECT id, code, name FROM courses ORDER BY name')->fetch_all(MYSQLI_ASSOC);
$sectionOptions = $mysqli->query('SELECT id, section_name FROM sections ORDER BY section_name')->fetch_all(MYSQLI_ASSOC);
$sectionOptionsWithYear = $mysqli->query('SELECT id, section_name, year_level FROM sections ORDER BY section_name')->fetch_all(MYSQLI_ASSOC);
$activeTeachers = $mysqli->query("SELECT id, first_name, last_name FROM teachers WHERE status = 'active' ORDER BY first_name")->fetch_all(MYSQLI_ASSOC);
$students = $mysqli->query('SELECT s.*, c.code AS course_code, sec.section_name FROM students s LEFT JOIN courses c ON s.course_id = c.id LEFT JOIN sections sec ON s.section_id = sec.id ORDER BY s.created_at DESC');
$teachers = $mysqli->query('SELECT t.*, sec.section_name FROM teachers t LEFT JOIN sections sec ON t.section_id = sec.id ORDER BY t.created_at DESC');
$courseList = $mysqli->query('SELECT * FROM courses ORDER BY code');
$sectionList = $mysqli->query('SELECT sec.*, c.code AS course_code, CONCAT(t.first_name, " ", t.last_name) AS adviser_name FROM sections sec LEFT JOIN courses c ON sec.course_id = c.id LEFT JOIN teachers t ON sec.adviser_id = t.id ORDER BY sec.created_at DESC');
$subjectList = $mysqli->query('SELECT sub.*, CONCAT(t.first_name, " ", t.last_name) AS teacher_name, sec.section_name FROM subjects sub LEFT JOIN teachers t ON sub.teacher_id = t.id LEFT JOIN sections sec ON sub.section_id = sec.id ORDER BY sub.created_at DESC');
$newCredentials = flashCredentialsMessage();
require_once __DIR__ . '/../includes/admin_header.php';
?>
<?php if ($newCredentials): ?>
    <div class="modal fade" id="credentialsModal" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content rounded-4">
                <div class="modal-body p-4">
                    <div class="alert alert-success rounded-4 mb-0">
                        <strong>Login credentials generated.</strong> Share these now — the password will not be shown again.
                        <div class="mt-2">
                            <span class="me-3">Username: <code><?php echo htmlspecialchars($newCredentials['username']); ?></code></span>
                            <span>Password: <code><?php echo htmlspecialchars($newCredentials['password']); ?></code></span>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-primary rounded-pill px-4" data-bs-dismiss="modal">Done</button>
                </div>
            </div>
        </div>
    </div>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            new bootstrap.Modal(document.getElementById('credentialsModal')).show();
        });
    </script>
<?php endif; ?>

<ul class="nav nav-tabs mb-3" id="academicsTabs" role="tablist">
    <li class="nav-item" role="presentation">
        <button class="nav-link active" id="teachers-tab" data-bs-toggle="tab" data-bs-target="#tab-teachers" type="button" role="tab">Teachers</button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="students-tab" data-bs-toggle="tab" data-bs-target="#tab-students" type="button" role="tab">Students</button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="courses-tab" data-bs-toggle="tab" data-bs-target="#tab-courses" type="button" role="tab">Courses</button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="sections-tab" data-bs-toggle="tab" data-bs-target="#tab-sections" type="button" role="tab">Sections</button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="subjects-tab" data-bs-toggle="tab" data-bs-target="#tab-subjects" type="button" role="tab">Subjects</button>
    </li>
</ul>

<div class="tab-content">
    <div class="tab-pane fade" id="tab-students" role="tabpanel">
        <div class="card rounded-4 shadow-sm p-4">
            <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                <h6 class="mb-0">Student Management</h6>
                <div class="d-flex align-items-center gap-2 flex-wrap">
                    <select class="form-select form-select-sm sp-filter-select" id="studentCourseFilter" style="width:auto;">
                        <option value="">All Courses</option>
                        <?php foreach ($courseOptions as $course): ?>
                            <option value="<?php echo htmlspecialchars($course['code']); ?>"><?php echo htmlspecialchars($course['code']); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <select class="form-select form-select-sm sp-filter-select" id="studentSectionFilter" style="width:auto;">
                        <option value="">All Sections</option>
                        <?php foreach ($sectionOptions as $section): ?>
                            <option value="<?php echo htmlspecialchars($section['section_name']); ?>"><?php echo htmlspecialchars($section['section_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#studentModal">Add Student</button>
                </div>
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
                                <td><?php echo date('M j, Y', strtotime($row['created_at'])); ?></td>
                                <td>
                                    <div class="d-flex align-items-center gap-1">
                                        <button class="btn btn-sm btn-outline-primary btn-icon btn-edit" data-data='<?php echo json_encode($row); ?>' title="Edit"><i class="fa-solid fa-pen"></i></button>
                                        <form method="post" class="d-inline-block">
                                            <input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>">
                                            <input type="hidden" name="action" value="delete_student">
                                            <input type="hidden" name="student_id" value="<?php echo $row['id']; ?>">
                                            <button type="button" class="btn btn-sm btn-outline-danger btn-icon js-confirm-submit" data-message="Delete this student?" title="Delete"><i class="fa-solid fa-trash"></i></button>
                                        </form>
                                        <?php if (!$row['user_id']): ?>
                                            <form method="post" class="d-inline-block">
                                                <input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>">
                                                <input type="hidden" name="action" value="create_student_credentials">
                                                <input type="hidden" name="student_id" value="<?php echo $row['id']; ?>">
                                                <button type="button" class="btn btn-sm btn-outline-secondary btn-icon js-confirm-submit" data-message="Create a login for this student?" title="Create Login"><i class="fa-solid fa-user-plus"></i></button>
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
    </div>

    <div class="tab-pane fade show active" id="tab-teachers" role="tabpanel">
        <div class="card rounded-4 shadow-sm p-4">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h6>Teacher Management</h6>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#teacherModal">Add Teacher</button>
            </div>
            <div class="table-responsive">
                <table class="table table-hover" id="teachersTable">
                    <thead class="table-light">
                        <tr>
                            <th>Photo</th>
                            <th>Teacher ID</th>
                            <th>Name</th>
                            <th>Subject</th>
                            <th>Section</th>
                            <th>Status</th>
                            <th>Created</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($row = $teachers->fetch_assoc()): ?>
                            <tr>
                                <td>
                                    <?php if (!empty($row['photo'])): ?>
                                        <img src="../<?php echo htmlspecialchars($row['photo']); ?>" class="table-avatar" alt="">
                                    <?php else: ?>
                                        <span class="table-avatar table-avatar-fallback"><i class="fa-solid fa-user"></i></span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($row['teacher_id']); ?></td>
                                <td><?php echo htmlspecialchars($row['first_name'] . ' ' . $row['last_name']); ?></td>
                                <td><?php echo htmlspecialchars($row['subject']); ?></td>
                                <td><?php echo htmlspecialchars($row['section_name'] ?? ''); ?></td>
                                <td><?php echo badgeStatus($row['status']); ?></td>
                                <td><?php echo date('M j, Y', strtotime($row['created_at'])); ?></td>
                                <td>
                                    <div class="d-flex align-items-center gap-1">
                                        <button class="btn btn-sm btn-outline-secondary btn-icon btn-view-teacher" data-data='<?php echo json_encode($row); ?>' title="View"><i class="fa-solid fa-eye"></i></button>
                                        <button class="btn btn-sm btn-outline-primary btn-icon btn-edit-teacher" data-data='<?php echo json_encode($row); ?>' title="Edit"><i class="fa-solid fa-pen"></i></button>
                                        <form method="post" class="d-inline-block">
                                            <input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>">
                                            <input type="hidden" name="action" value="delete_teacher">
                                            <input type="hidden" name="teacher_id" value="<?php echo $row['id']; ?>">
                                            <button type="button" class="btn btn-sm btn-outline-danger btn-icon js-confirm-submit" data-message="Delete this teacher?" title="Delete"><i class="fa-solid fa-trash"></i></button>
                                        </form>
                                        <?php if (!$row['user_id']): ?>
                                            <form method="post" class="d-inline-block">
                                                <input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>">
                                                <input type="hidden" name="action" value="create_teacher_credentials">
                                                <input type="hidden" name="teacher_id" value="<?php echo $row['id']; ?>">
                                                <button type="button" class="btn btn-sm btn-outline-secondary btn-icon js-confirm-submit" data-message="Create a login for this teacher?" title="Create Login"><i class="fa-solid fa-user-plus"></i></button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="tab-pane fade" id="tab-courses" role="tabpanel">
        <div class="card rounded-4 shadow-sm p-4">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h6>Course Management</h6>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#courseModal">Add Course</button>
            </div>
            <div class="table-responsive">
                <table class="table table-hover" id="coursesTable">
                    <thead class="table-light">
                        <tr><th>Code</th><th>Name</th><th>Description</th><th>Actions</th></tr>
                    </thead>
                    <tbody>
                        <?php while ($row = $courseList->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($row['code']); ?></td>
                                <td><?php echo htmlspecialchars($row['name']); ?></td>
                                <td><?php echo htmlspecialchars($row['description']); ?></td>
                                <td>
                                    <div class="d-flex align-items-center gap-1">
                                        <button class="btn btn-sm btn-outline-primary btn-icon btn-edit-course" data-data='<?php echo json_encode($row); ?>' title="Edit"><i class="fa-solid fa-pen"></i></button>
                                        <form method="post" class="d-inline-block">
                                            <input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>">
                                            <input type="hidden" name="action" value="delete_course">
                                            <input type="hidden" name="course_id" value="<?php echo $row['id']; ?>">
                                            <button type="button" class="btn btn-sm btn-outline-danger btn-icon js-confirm-submit" data-message="Delete this course?" title="Delete"><i class="fa-solid fa-trash"></i></button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="tab-pane fade" id="tab-sections" role="tabpanel">
        <div class="card rounded-4 shadow-sm p-4">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h6>Section Management</h6>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#sectionModal">Add Section</button>
            </div>
            <div class="table-responsive">
                <table class="table table-hover" id="sectionsTable">
                    <thead class="table-light">
                        <tr><th>Year</th><th>Section</th><th>Course</th><th>Adviser</th><th>Actions</th></tr>
                    </thead>
                    <tbody>
                        <?php while ($row = $sectionList->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($row['year_level']); ?></td>
                                <td><?php echo htmlspecialchars($row['section_name']); ?></td>
                                <td><?php echo htmlspecialchars($row['course_code']); ?></td>
                                <td><?php echo htmlspecialchars($row['adviser_name'] ?: 'Unassigned'); ?></td>
                                <td>
                                    <div class="d-flex align-items-center gap-1">
                                        <button class="btn btn-sm btn-outline-primary btn-icon btn-edit-section" data-data='<?php echo json_encode($row); ?>' title="Edit"><i class="fa-solid fa-pen"></i></button>
                                        <form method="post" class="d-inline-block">
                                            <input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>">
                                            <input type="hidden" name="action" value="delete_section">
                                            <input type="hidden" name="section_id" value="<?php echo $row['id']; ?>">
                                            <button type="button" class="btn btn-sm btn-outline-danger btn-icon js-confirm-submit" data-message="Delete this section?" title="Delete"><i class="fa-solid fa-trash"></i></button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="tab-pane fade" id="tab-subjects" role="tabpanel">
        <div class="card rounded-4 shadow-sm p-4">
            <div class="mb-3">
                <h6>Subject Management</h6>
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <div class="sp-subject-search">
                        <i class="fa-solid fa-magnifying-glass"></i>
                        <input type="text" class="form-control" id="subjectSearchInput" placeholder="Search subjects...">
                    </div>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#subjectModal">Add Subject</button>
                </div>
            </div>
            <div class="table-responsive">
                <table class="table table-hover" id="subjectsTable">
                    <thead class="table-light">
                        <tr><th>Code</th><th>Name</th><th>Teacher</th><th>Section</th><th>Day</th><th>Time</th><th>Room</th><th>Status</th><th>Actions</th></tr>
                    </thead>
                    <tbody>
                        <?php while ($row = $subjectList->fetch_assoc()): ?>
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
                                    <div class="d-flex align-items-center gap-1">
                                        <button class="btn btn-sm btn-outline-primary btn-icon btn-edit-subject" data-data='<?php echo json_encode($row); ?>' title="Edit"><i class="fa-solid fa-pen"></i></button>
                                        <form method="post" class="d-inline-block">
                                            <input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>">
                                            <input type="hidden" name="action" value="delete_subject">
                                            <input type="hidden" name="subject_id" value="<?php echo $row['id']; ?>">
                                            <button type="button" class="btn btn-sm btn-outline-danger btn-icon js-confirm-submit" data-message="Delete this subject?" title="Delete"><i class="fa-solid fa-trash"></i></button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
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
                        <label class="form-label">Guardian</label>
                        <input type="text" class="form-control" name="guardian" id="guardianField">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Guardian Email</label>
                        <input type="email" class="form-control" name="guardian_email" id="guardianEmailField" placeholder="For attendance alerts">
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
                            <?php foreach ($courseOptions as $course): ?>
                                <option value="<?php echo $course['id']; ?>"><?php echo htmlspecialchars($course['code'] . ' - ' . $course['name']); ?></option>
                            <?php endforeach; ?>
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
                            <?php foreach ($sectionOptions as $section): ?>
                                <option value="<?php echo $section['id']; ?>"><?php echo htmlspecialchars($section['section_name']); ?></option>
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

<div class="modal fade" id="teacherModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content rounded-4">
            <div class="modal-header">
                <h5 class="modal-title">Teacher Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="post" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>">
                <input type="hidden" name="action" value="save_teacher">
                <input type="hidden" name="id" id="teacherIdField">
                <div class="modal-body row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Teacher Code</label>
                        <input type="text" class="form-control" name="teacher_id" id="teacherCodeField" required>
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
                        <label class="form-label">Status</label>
                        <select class="form-select" name="status" id="teacherStatusField">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Subject</label>
                        <input type="text" class="form-control" name="subject" id="subjectField" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Section</label>
                        <select class="form-select" name="section_id" id="teacherSectionField">
                            <option value="0">Unassigned</option>
                            <?php foreach ($sectionOptions as $section): ?>
                                <option value="<?php echo $section['id']; ?>"><?php echo htmlspecialchars($section['section_name']); ?></option>
                            <?php endforeach; ?>
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

<div class="modal fade" id="teacherViewModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-4">
            <div class="modal-header">
                <h5 class="modal-title">Teacher Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="text-center mb-3">
                    <img id="viewTeacherPhoto" src="" alt="Photo" class="rounded-circle d-none" style="width:96px;height:96px;object-fit:cover;">
                </div>
                <dl class="row mb-0">
                    <dt class="col-5">Teacher Code</dt><dd class="col-7" id="viewTeacherCode"></dd>
                    <dt class="col-5">Name</dt><dd class="col-7" id="viewTeacherName"></dd>
                    <dt class="col-5">Subject</dt><dd class="col-7" id="viewTeacherSubject"></dd>
                    <dt class="col-5">Section</dt><dd class="col-7" id="viewTeacherSection"></dd>
                    <dt class="col-5">Status</dt><dd class="col-7" id="viewTeacherStatus"></dd>
                    <dt class="col-5">Email</dt><dd class="col-7" id="viewTeacherEmail"></dd>
                    <dt class="col-5">Phone</dt><dd class="col-7" id="viewTeacherPhone"></dd>
                    <dt class="col-5">Created</dt><dd class="col-7" id="viewTeacherCreated"></dd>
                </dl>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
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
                            <?php foreach ($courseOptions as $course): ?>
                                <option value="<?php echo $course['id']; ?>"><?php echo htmlspecialchars($course['code']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Adviser</label>
                        <select class="form-select" name="adviser_id" id="sectionAdviserField">
                            <option value="0">Unassigned</option>
                            <?php foreach ($activeTeachers as $teacher): ?>
                                <option value="<?php echo $teacher['id']; ?>"><?php echo htmlspecialchars($teacher['first_name'] . ' ' . $teacher['last_name']); ?></option>
                            <?php endforeach; ?>
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
                            <?php foreach ($activeTeachers as $teacher): ?>
                                <option value="<?php echo $teacher['id']; ?>"><?php echo htmlspecialchars($teacher['first_name'] . ' ' . $teacher['last_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Section</label>
                        <select class="form-select" name="section_id" id="subjectSectionField" required>
                            <?php foreach ($sectionOptionsWithYear as $section): ?>
                                <option value="<?php echo $section['id']; ?>"><?php echo htmlspecialchars($section['year_level'] . ' - ' . $section['section_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-12">
                        <label class="form-label">Day(s) of Week</label>
                        <div class="d-flex flex-wrap gap-3" id="subjectDayField">
                            <?php foreach (['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'] as $day): ?>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="day_of_week[]" value="<?php echo $day; ?>" id="subjectDay<?php echo $day; ?>">
                                    <label class="form-check-label" for="subjectDay<?php echo $day; ?>"><?php echo $day; ?></label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <small class="text-muted">Selecting multiple days creates one class session per day, all sharing this same time and details.</small>
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
document.addEventListener('DOMContentLoaded', function () {
const studentModal = new bootstrap.Modal(document.getElementById('studentModal'));
document.querySelectorAll('.btn-edit').forEach(btn => {
    btn.addEventListener('click', () => {
        const data = JSON.parse(btn.getAttribute('data-data'));
        document.getElementById('studentIdField').value = data.id;
        document.getElementById('studentCodeField').value = data.student_id;
        document.getElementById('firstNameField').value = data.first_name;
        document.getElementById('lastNameField').value = data.last_name;
        document.getElementById('genderField').value = data.gender;
        document.getElementById('guardianField').value = data.guardian_name;
        document.getElementById('guardianEmailField').value = data.guardian_email;
        document.getElementById('phoneField').value = data.phone;
        document.getElementById('emailField').value = data.email;
        document.getElementById('courseField').value = data.course_id;
        document.getElementById('yearField').value = data.year_level;
        document.getElementById('sectionField').value = data.section_id;
        document.getElementById('statusField').value = data.status;
        studentModal.show();
    });
});

const teacherViewModal = new bootstrap.Modal(document.getElementById('teacherViewModal'));
const statusBadgeClass = { active: 'success', inactive: 'secondary' };
const monthNames = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
function formatDateTime(sqlDateTime) {
    if (!sqlDateTime) return '—';
    const [datePart, timePart] = sqlDateTime.split(' ');
    const [year, month, day] = datePart.split('-').map(Number);
    const [hour, minute] = timePart.split(':').map(Number);
    const period = hour >= 12 ? 'PM' : 'AM';
    const hour12 = hour % 12 || 12;
    return `${monthNames[month - 1]} ${day}, ${year} ${hour12}:${String(minute).padStart(2, '0')} ${period}`;
}
document.querySelectorAll('.btn-view-teacher').forEach(btn => {
    btn.addEventListener('click', () => {
        const data = JSON.parse(btn.getAttribute('data-data'));
        document.getElementById('viewTeacherCode').textContent = data.teacher_id || '—';
        document.getElementById('viewTeacherName').textContent = `${data.first_name || ''} ${data.last_name || ''}`.trim() || '—';
        document.getElementById('viewTeacherSubject').textContent = data.subject || '—';
        document.getElementById('viewTeacherSection').textContent = data.section_name || 'Unassigned';
        document.getElementById('viewTeacherStatus').innerHTML = `<span class="badge bg-${statusBadgeClass[data.status] || 'secondary'}">${(data.status || '').charAt(0).toUpperCase() + (data.status || '').slice(1)}</span>`;
        document.getElementById('viewTeacherEmail').textContent = data.email || '—';
        document.getElementById('viewTeacherPhone').textContent = data.phone || '—';
        document.getElementById('viewTeacherCreated').textContent = formatDateTime(data.created_at);
        const photoEl = document.getElementById('viewTeacherPhoto');
        if (data.photo) {
            photoEl.src = '../' + data.photo;
            photoEl.classList.remove('d-none');
        } else {
            photoEl.classList.add('d-none');
        }
        teacherViewModal.show();
    });
});

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

const subjectModal = new bootstrap.Modal(document.getElementById('subjectModal'));
document.querySelectorAll('.btn-edit-subject').forEach(btn => {
    btn.addEventListener('click', () => {
        const data = JSON.parse(btn.getAttribute('data-data'));
        document.getElementById('subjectIdField').value = data.id;
        document.getElementById('subjectCodeField').value = data.code;
        document.getElementById('subjectNameField').value = data.name;
        document.getElementById('subjectTeacherField').value = data.teacher_id || '0';
        document.getElementById('subjectSectionField').value = data.section_id;
        document.querySelectorAll('#subjectDayField input[type="checkbox"]').forEach(function (cb) {
            cb.checked = cb.value === data.day_of_week;
        });
        document.getElementById('subjectStartTimeField').value = data.start_time;
        document.getElementById('subjectEndTimeField').value = data.end_time || '';
        document.getElementById('subjectRoomField').value = data.room || '';
        document.getElementById('subjectCreditUnitsField').value = data.credit_units || '';
        document.getElementById('subjectNoteField').value = data.important_note || '';
        document.getElementById('subjectStatusField').value = data.status;
        subjectModal.show();
    });
});

var studentsTable = $('#studentsTable').DataTable({ responsive: true, paging: false, ordering: false, dom: 'frt' });

function applyStudentFilters() {
    var course = $('#studentCourseFilter').val();
    var section = $('#studentSectionFilter').val();
    studentsTable.column(3).search(course ? '^' + $.fn.dataTable.util.escapeRegex(course) + '$' : '', true, false);
    studentsTable.column(4).search(section ? '^' + $.fn.dataTable.util.escapeRegex(section) + '$' : '', true, false);
    studentsTable.draw();
}
$('#studentCourseFilter, #studentSectionFilter').on('change', applyStudentFilters);

$('#teachersTable').DataTable({ responsive: true, paging: false, ordering: false, dom: 'frt' });
$('#coursesTable').DataTable({ responsive: true, paging: false, ordering: false, dom: 'frt' });
$('#sectionsTable').DataTable({ responsive: true, paging: false, ordering: false, dom: 'frt' });
var subjectsTable = $('#subjectsTable').DataTable({ responsive: true, paging: false, ordering: false, dom: 'frt' });
$('#subjectSearchInput').on('input', function () {
    subjectsTable.search(this.value).draw();
});

document.querySelectorAll('#academicsTabs button[data-bs-toggle="tab"]').forEach(function (tabBtn) {
    tabBtn.addEventListener('shown.bs.tab', function () {
        $.fn.dataTable.tables({ visible: true, api: true }).columns.adjust();
    });
});
});
</script>
<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>
