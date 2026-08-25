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
$displaySections = [];
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
    if ($_POST['action'] === 'create_student_credentials' && !empty($_POST['student_id'])) {
        $sid = intval($_POST['student_id']);
        $stmt = $mysqli->prepare('SELECT student_id, user_id, email, section_id FROM students WHERE id = ?');
        $stmt->bind_param('i', $sid);
        $stmt->execute();
        $stmt->bind_result($studentCode, $linkedUserId, $studentEmail, $studentSectionId);
        if ($stmt->fetch() && in_array((int) $studentSectionId, $allowedSections)) {
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
            flash('You can only manage students in your own sections.', 'danger');
        }
        redirect('students.php');
    }
}

$newCredentials = flashCredentialsMessage();
$courses = $mysqli->query('SELECT id, code, name FROM courses ORDER BY name');
$studentRows = [];
if ($displaySections) {
    $placeholders = implode(',', array_fill(0, count($displaySections), '?'));
    $types = str_repeat('i', count($displaySections));
    $stmt = $mysqli->prepare("SELECT s.*, c.code AS course_code, sec.section_name FROM students s LEFT JOIN courses c ON s.course_id = c.id LEFT JOIN sections sec ON s.section_id = sec.id WHERE s.section_id IN ($placeholders) ORDER BY s.created_at DESC");
    $stmt->bind_param($types, ...$displaySections);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $studentRows[] = $row;
    }
}

$qrDirectory = __DIR__ . '/../qrcodes/';
foreach ($studentRows as &$sRow) {
    $qrToken = $sRow['qr_code'] ?: $sRow['student_id'];
    $qrFilename = 'qr_' . preg_replace('/[^A-Za-z0-9_-]/', '_', $qrToken) . '.png';
    $qrFilePath = $qrDirectory . $qrFilename;
    $sRow['qr_filename'] = null;
    if (ensureQrDirectory($qrDirectory)) {
        if (!file_exists($qrFilePath) || !isValidPngFile($qrFilePath)) {
            generateStudentQrFile($qrToken, $qrFilePath);
        }
        if (file_exists($qrFilePath) && isValidPngFile($qrFilePath)) {
            $sRow['qr_filename'] = $qrFilename;
        }
    }
}
unset($sRow);
require_once __DIR__ . '/../includes/teacher_header.php';
?>
<a href="subjects.php" class="sp-back-link d-inline-flex mb-3"><i class="fa-solid fa-arrow-left"></i> Back to My Classes</a>
<?php if ($newCredentials): ?>
    <div class="alert alert-success rounded-4" id="newCredentialsAlert">
        <strong>Login credentials generated.</strong> Share these with the student now — the password will not be shown again.
        <div class="mt-2">
            <span class="me-3">Username: <code><?php echo htmlspecialchars($newCredentials['username']); ?></code></span>
            <span>Password: <code><?php echo htmlspecialchars($newCredentials['password']); ?></code></span>
        </div>
        <div class="mt-3">
            <button type="button" class="btn btn-sm btn-success rounded-pill px-4" onclick="document.getElementById('newCredentialsAlert').remove();">Done</button>
        </div>
    </div>
<?php endif; ?>
<div class="card p-4">
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <div>
            <?php if ($filterSubjectName): ?>
                <h5 class="mb-0"><?php echo htmlspecialchars($filterSubjectName); ?></h5>
            <?php endif; ?>
        </div>
        <?php if ($allowedSections && $filterSubjectId): ?>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#studentModal"><i class="fa-solid fa-plus me-1"></i> Add Student</button>
        <?php endif; ?>
    </div>
    <?php if (!$allowedSections): ?>
        <div class="alert alert-info">You have no assigned subjects yet, so there are no students to manage. Ask an admin to assign you a subject.</div>
    <?php elseif (!$filterSubjectId): ?>
        <div class="alert alert-info">Open Students from a specific class in My Classes to see its roster.</div>
    <?php else: ?>
    <div class="table-responsive">
        <table class="table table-hover" id="myStudentsTable">
            <thead class="table-light">
                <tr>
                    <th>Photo</th>
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
                <?php foreach ($studentRows as $row): ?>
                    <tr>
                        <td>
                            <?php if (!empty($row['photo'])): ?>
                                <img src="../<?php echo htmlspecialchars($row['photo']); ?>" alt="" class="rounded-circle" style="width:36px;height:36px;object-fit:cover;">
                            <?php else: ?>
                                <i class="fa-solid fa-circle-user fa-2x text-secondary"></i>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($row['qr_filename']): ?>
                                <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#qrModal<?php echo $row['id']; ?>">QR</button>
                            <?php else: ?>
                                <button type="button" class="btn btn-sm btn-outline-secondary" disabled title="QR unavailable">QR</button>
                            <?php endif; ?>
                        </td>
                        <td><?php echo htmlspecialchars($row['student_id']); ?></td>
                        <td><?php echo htmlspecialchars($row['first_name'] . ' ' . $row['last_name']); ?></td>
                        <td><?php echo htmlspecialchars($row['course_code']); ?></td>
                        <td><?php echo htmlspecialchars($row['section_name']); ?></td>
                        <td><?php echo badgeStatus($row['status']); ?></td>
                        <td>
                            <button class="btn btn-sm btn-outline-secondary btn-view" data-data='<?php echo json_encode($row); ?>' title="View"><i class="fa-solid fa-eye"></i></button>
                            <button class="btn btn-sm btn-outline-primary btn-edit" data-data='<?php echo json_encode($row); ?>' title="Edit"><i class="fa-solid fa-pen"></i></button>
                            <form method="post" class="d-inline-block" onsubmit="return confirm('Delete this student?');">
                                <input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>">
                                <input type="hidden" name="action" value="delete_student">
                                <input type="hidden" name="student_id" value="<?php echo $row['id']; ?>">
                                <button class="btn btn-sm btn-outline-danger" title="Delete"><i class="fa-solid fa-trash"></i></button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<?php foreach ($studentRows as $row): if (!$row['qr_filename']) continue; ?>
    <div class="modal fade" id="qrModal<?php echo $row['id']; ?>" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered sp-qr-modal-dialog">
            <div class="modal-content rounded-4 sp-qr-modal">
                <button type="button" class="btn-close sp-qr-close" data-bs-dismiss="modal" aria-label="Close"></button>
                <div class="modal-body text-center py-5">
                    <div class="sp-qr-card">
                        <span class="sp-shd-pill"><i class="fa-solid fa-id-card"></i> STUDENT ID</span>
                        <h2 class="sp-qr-code mt-3 mb-0"><?php echo htmlspecialchars($row['student_id']); ?></h2>
                        <p class="text-muted"><?php echo htmlspecialchars($row['first_name'] . ' ' . $row['last_name']); ?></p>
                        <hr class="sp-qr-divider">
                        <div class="sp-qr-frame">
                            <img src="../qrcodes/<?php echo urlencode($row['qr_filename']); ?>" alt="QR code for <?php echo htmlspecialchars($row['student_id']); ?>" class="img-fluid">
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
<?php endforeach; ?>

<div class="modal fade" id="viewStudentModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-4">
            <div class="modal-header">
                <h5 class="modal-title">Student Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="sp-profile-info-row">
                    <i class="fa-solid fa-id-card"></i>
                    <div>
                        <div class="sp-profile-info-label">Student Code</div>
                        <div class="sp-profile-info-value" id="viewStudentCode"></div>
                    </div>
                </div>
                <div class="sp-profile-info-row">
                    <i class="fa-solid fa-user"></i>
                    <div>
                        <div class="sp-profile-info-label">Name</div>
                        <div class="sp-profile-info-value" id="viewStudentName"></div>
                    </div>
                </div>
                <div class="sp-profile-info-row">
                    <i class="fa-solid fa-graduation-cap"></i>
                    <div>
                        <div class="sp-profile-info-label">Course / Section</div>
                        <div class="sp-profile-info-value" id="viewStudentCourseSection"></div>
                    </div>
                </div>
                <div class="sp-profile-info-row">
                    <i class="fa-solid fa-phone"></i>
                    <div>
                        <div class="sp-profile-info-label">Phone</div>
                        <div class="sp-profile-info-value" id="viewStudentPhone"></div>
                    </div>
                </div>
                <div class="sp-profile-info-row">
                    <i class="fa-solid fa-envelope"></i>
                    <div>
                        <div class="sp-profile-info-label">Email</div>
                        <div class="sp-profile-info-value" id="viewStudentEmail"></div>
                    </div>
                </div>
                <div class="sp-profile-info-row mb-0">
                    <i class="fa-solid fa-shield-halved"></i>
                    <div>
                        <div class="sp-profile-info-label">Status</div>
                        <div id="viewStudentStatus"></div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
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
            <form method="post">
                <input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>">
                <input type="hidden" name="action" value="save_student">
                <input type="hidden" name="id" id="studentIdField">
                <div class="modal-body row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Student Code</label>
                        <input type="text" class="form-control" name="student_id" id="studentCodeField" placeholder="Leave blank to auto-generate">
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
                        <label class="form-label">Phone</label>
                        <input type="text" class="form-control" name="phone" id="phoneField">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Email</label>
                        <input type="email" class="form-control" name="email" id="emailField" readonly>
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
            document.getElementById('phoneField').value = data.phone;
            document.getElementById('emailField').value = data.email;
            document.getElementById('courseField').value = data.course_id;
            document.getElementById('yearField').value = data.year_level;
            document.getElementById('sectionField').value = data.section_id;
            document.getElementById('statusField').value = data.status;
            studentModal.show();
        });
    });

    const viewButtons = document.querySelectorAll('.btn-view');
    const viewStudentModal = new bootstrap.Modal(document.getElementById('viewStudentModal'));
    viewButtons.forEach(btn => {
        btn.addEventListener('click', () => {
            const data = JSON.parse(btn.getAttribute('data-data'));
            document.getElementById('viewStudentCode').textContent = data.student_id;
            document.getElementById('viewStudentName').textContent = (data.first_name + ' ' + data.last_name).trim();
            document.getElementById('viewStudentCourseSection').textContent = (data.course_code || 'N/A') + ' — ' + (data.section_name || 'N/A');
            document.getElementById('viewStudentPhone').textContent = data.phone || '—';
            document.getElementById('viewStudentEmail').textContent = data.email || '—';
            const statusBadge = data.status === 'active'
                ? '<span class="badge bg-success">Active</span>'
                : '<span class="badge bg-secondary">Inactive</span>';
            document.getElementById('viewStudentStatus').innerHTML = statusBadge;
            viewStudentModal.show();
        });
    });

    $('#myStudentsTable').DataTable({ responsive: true });
});
</script>
<?php require_once __DIR__ . '/../includes/teacher_footer.php'; ?>
