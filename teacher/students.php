<?php
require_once __DIR__ . '/../includes/functions.php';
requireRole(['teacher']);
$pageTitle = 'Students';
$pageSubtitle = 'Manage students in your assigned rooms.';

$teacherId = currentTeacherId();
if ($teacherId === false) {
    flash('Your teacher profile is not set up. Contact an administrator.', 'danger');
    redirect('../dashboard.php');
}

$roomStmt = $mysqli->prepare('SELECT DISTINCT sec.id FROM subjects sub JOIN rooms sec ON sub.room_id = sec.id WHERE sub.teacher_id = ? ORDER BY sec.room_name');
$roomStmt->bind_param('i', $teacherId);
$roomStmt->execute();
$roomResult = $roomStmt->get_result();
$allowedRooms = [];
while ($row = $roomResult->fetch_assoc()) {
    $allowedRooms[] = (int) $row['id'];
}
$roomStmt->close();

$filterSubjectId = intval($_GET['subject_id'] ?? 0);
$filterSubjectName = null;
$displayRooms = [];
if ($filterSubjectId) {
    $subjStmt = $mysqli->prepare('SELECT code, name, room_id FROM subjects WHERE id = ? AND teacher_id = ?');
    $subjStmt->bind_param('ii', $filterSubjectId, $teacherId);
    $subjStmt->execute();
    $subjRow = $subjStmt->get_result()->fetch_assoc();
    $subjStmt->close();
    if ($subjRow) {
        $filterSubjectName = $subjRow['code'] . ' - ' . $subjRow['name'];
        $displayRooms = [(int) $subjRow['room_id']];
    }
}

$redirectTarget = 'students.php' . ($filterSubjectId ? '?subject_id=' . $filterSubjectId : '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        flash('Invalid form submission.', 'danger');
        redirect($redirectTarget);
    }
    if ($_POST['action'] === 'update_student_contact') {
        $sid = intval($_POST['id'] ?? 0);
        $guardian = sanitize($_POST['guardian'] ?? '');
        $guardianEmail = sanitize($_POST['guardian_email'] ?? '');
        $phone = sanitize($_POST['phone'] ?? '');

        $check = $mysqli->prepare('SELECT room_id FROM students WHERE id = ?');
        $check->bind_param('i', $sid);
        $check->execute();
        $check->bind_result($existingRoomId);
        $found = $check->fetch();
        $check->close();

        if (!$found || !in_array((int) $existingRoomId, $allowedRooms)) {
            flash('You can only manage students in your own rooms.', 'danger');
            redirect($redirectTarget);
        }

        $stmt = $mysqli->prepare('UPDATE students SET guardian_name = ?, guardian_email = ?, phone = ? WHERE id = ?');
        $stmt->bind_param('sssi', $guardian, $guardianEmail, $phone, $sid);
        $stmt->execute();
        $stmt->close();
        flash('Student contact info updated.', 'success');
        redirect($redirectTarget);
    }
    if ($_POST['action'] === 'enroll_student' && !empty($_POST['student_db_id'])) {
        if (!$filterSubjectId || !$displayRooms) {
            flash('Open a specific class before enrolling a student.', 'danger');
            redirect($redirectTarget);
        }
        $sid = intval($_POST['student_db_id']);
        $targetRoomId = $displayRooms[0];
        $result = enrollStudentInRoom($mysqli, $sid, $targetRoomId, $filterSubjectId);
        if ($result === 'success') {
            $notifMessage = "You've been added to " . $filterSubjectName . '.';
            $notifStmt = $mysqli->prepare("INSERT INTO notifications (student_id, subject_id, type, title, message, is_read, created_at) VALUES (?, ?, 'enrolled', 'Enrolled in a class', ?, 0, NOW())");
            $notifStmt->bind_param('iis', $sid, $filterSubjectId, $notifMessage);
            $notifStmt->execute();
            $notifStmt->close();
            flash('Student enrolled in this class.', 'success');
        } elseif ($result === 'already_enrolled') {
            flash('That student is already enrolled in this class.', 'info');
        } else {
            flash('Student not found.', 'danger');
        }
        redirect($redirectTarget);
    }
    if ($_POST['action'] === 'unenroll_student' && !empty($_POST['student_id'])) {
        $sid = intval($_POST['student_id']);
        if (unenrollStudentFromRoom($mysqli, $sid, $allowedRooms)) {
            flash('Student unenrolled from this room. Their account was kept.', 'success');
        } elseif ($filterSubjectId && removeClassEnrollment($mysqli, $sid, $filterSubjectId)) {
            flash('Student removed from this class. Their home room was not affected.', 'success');
        } else {
            flash('You can only manage students in your own rooms.', 'danger');
        }
        redirect($redirectTarget);
    }
    if ($_POST['action'] === 'create_student_credentials' && !empty($_POST['student_id'])) {
        $sid = intval($_POST['student_id']);
        $stmt = $mysqli->prepare('SELECT student_id, user_id, email, room_id, first_name, last_name FROM students WHERE id = ?');
        $stmt->bind_param('i', $sid);
        $stmt->execute();
        $stmt->bind_result($studentCode, $linkedUserId, $studentEmail, $studentRoomId, $studentFirstName, $studentLastName);
        if ($stmt->fetch() && in_array((int) $studentRoomId, $allowedRooms)) {
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
                    $emailed = $studentEmail && emailStudentCredentials($studentEmail, trim($studentFirstName . ' ' . $studentLastName), $studentCode, $plainPassword);
                    flashCredentials($studentCode, $plainPassword, $emailed);
                } else {
                    flash('Unable to create a login account (email may already be in use).', 'danger');
                }
            }
        } else {
            $stmt->close();
            flash('You can only manage students in your own rooms.', 'danger');
        }
        redirect($redirectTarget);
    }
}

$newCredentials = flashCredentialsMessage();
$studentRows = [];
if ($displayRooms) {
    $placeholders = implode(',', array_fill(0, count($displayRooms), '?'));
    $types = str_repeat('i', count($displayRooms));
    $params = $displayRooms;
    $sql = "SELECT s.*, c.code AS course_code, sec.room_name FROM students s LEFT JOIN courses c ON s.course_id = c.id LEFT JOIN rooms sec ON s.room_id = sec.id WHERE s.room_id IN ($placeholders)";
    if ($filterSubjectId) {
        $sql .= ' OR s.id IN (SELECT student_id FROM enrollments WHERE subject_id = ?)';
        $types .= 'i';
        $params[] = $filterSubjectId;
    }
    $sql .= ' ORDER BY s.created_at DESC';
    $stmt = $mysqli->prepare($sql);
    $stmt->bind_param($types, ...$params);
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
        <div class="mt-2">
            <?php if (!empty($newCredentials['emailed'])): ?>
                <span class="badge bg-success-subtle text-success border border-success-subtle"><i class="fa-solid fa-envelope-circle-check me-1"></i>Emailed to the student</span>
            <?php else: ?>
                <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle"><i class="fa-solid fa-triangle-exclamation me-1"></i>Not emailed — share these credentials manually</span>
            <?php endif; ?>
        </div>
        <div class="mt-3">
            <button type="button" class="btn btn-sm btn-success rounded-pill px-4" onclick="document.getElementById('newCredentialsAlert').remove();">Done</button>
        </div>
    </div>
<?php endif; ?>
<div class="card p-4">
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <div class="d-flex align-items-center gap-3 flex-wrap">
            <?php if ($filterSubjectName): ?>
                <h5 class="mb-0"><?php echo htmlspecialchars($filterSubjectName); ?></h5>
            <?php endif; ?>
            <?php if ($allowedRooms && $filterSubjectId): ?>
                <div class="input-group input-group-sm" style="max-width:220px;">
                    <span class="input-group-text bg-white"><i class="fa-solid fa-magnifying-glass"></i></span>
                    <input type="search" class="form-control" id="studentsSearchInput" placeholder="Search students...">
                </div>
            <?php endif; ?>
        </div>
        <div class="d-flex align-items-center gap-2 flex-wrap" id="studentsToolbarRight">
            <?php if ($allowedRooms && $filterSubjectId): ?>
                <button class="btn btn-primary" id="enrollStudentBtn" data-bs-toggle="modal" data-bs-target="#enrollStudentModal"><i class="fa-solid fa-user-plus me-1"></i> Enroll Student</button>
            <?php endif; ?>
        </div>
    </div>
    <?php if (!$allowedRooms): ?>
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
                    <th>Room</th>
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
                        <td><?php echo htmlspecialchars($row['room_name']); ?></td>
                        <td><?php echo badgeStatus($row['status']); ?></td>
                        <td>
                            <button class="btn btn-sm btn-outline-secondary btn-view" data-data='<?php echo json_encode($row); ?>' title="View"><i class="fa-solid fa-eye"></i></button>
                            <button class="btn btn-sm btn-outline-primary btn-edit" data-data='<?php echo json_encode($row); ?>' title="Edit"><i class="fa-solid fa-pen"></i></button>
                            <?php if (!empty($row['email'])): ?>
                                <a href="https://mail.google.com/mail/?view=cm&fs=1&to=<?php echo urlencode($row['email']); ?>" target="_blank" rel="noopener" class="btn btn-sm btn-outline-success" title="Message"><i class="fa-solid fa-comment"></i></a>
                            <?php else: ?>
                                <button type="button" class="btn btn-sm btn-outline-success" disabled title="No email on file"><i class="fa-solid fa-comment"></i></button>
                            <?php endif; ?>
                            <form method="post" class="d-inline-block" id="unenrollStudentForm<?php echo $row['id']; ?>">
                                <input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>">
                                <input type="hidden" name="action" value="unenroll_student">
                                <input type="hidden" name="student_id" value="<?php echo $row['id']; ?>">
                                <button type="button" class="btn btn-sm btn-outline-warning btn-unenroll-student" data-form-id="unenrollStudentForm<?php echo $row['id']; ?>" data-student-name="<?php echo htmlspecialchars($row['first_name'] . ' ' . $row['last_name']); ?>" title="Unenroll"><i class="fa-solid fa-user-slash"></i></button>
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
                    <div id="qrCard<?php echo $row['id']; ?>" class="sp-qr-card">
                        <?php if (!empty($row['photo'])): ?>
                            <img src="../<?php echo htmlspecialchars($row['photo']); ?>" class="sp-qr-avatar" alt="">
                        <?php else: ?>
                            <div class="sp-qr-avatar sp-qr-avatar-fallback"><i class="fa-solid fa-user"></i></div>
                        <?php endif; ?>
                        <span class="sp-shd-pill mt-2"><i class="fa-solid fa-id-card"></i> STUDENT ID</span>
                        <h2 class="sp-qr-code mt-3 mb-0"><?php echo htmlspecialchars($row['student_id']); ?></h2>
                        <p class="text-muted"><?php echo htmlspecialchars($row['first_name'] . ' ' . $row['last_name']); ?></p>
                        <hr class="sp-qr-divider">
                        <div class="sp-qr-frame">
                            <img src="../qrcodes/<?php echo urlencode($row['qr_filename']); ?>" alt="QR code for <?php echo htmlspecialchars($row['student_id']); ?>" class="img-fluid">
                        </div>
                    </div>
                    <button type="button" class="btn btn-outline-success rounded-pill w-100 mt-3 sp-qr-save-btn" data-target="qrCard<?php echo $row['id']; ?>" data-filename="qr_<?php echo htmlspecialchars($row['student_id']); ?>.png"><i class="fa-solid fa-download me-1"></i> Download QR Code</button>
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
                        <div class="sp-profile-info-label">Course / Room</div>
                        <div class="sp-profile-info-value" id="viewStudentCourseRoom"></div>
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

<div class="modal fade" id="unenrollStudentModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-4">
            <div class="modal-body text-center py-4">
                <div class="sp-unenroll-icon mb-3"><i class="fa-solid fa-user-slash"></i></div>
                <h5 class="mb-2">Unenroll this student?</h5>
                <p class="text-muted mb-0">This will remove <strong id="unenrollStudentName"></strong> from this room/class. Their student account and records are kept — an admin can re-assign them to a room later.</p>
            </div>
            <div class="modal-footer justify-content-center border-0 pb-4">
                <button type="button" class="btn btn-secondary px-4" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-warning px-4" id="confirmUnenrollStudentBtn">Unenroll</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="enrollStudentModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-4">
            <div class="modal-header">
                <h5 class="modal-title">Enroll Student</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted small mb-3">Enter the student's ID number to find their account and enroll them into this class. New students are added to the system by an administrator.</p>
                <div class="input-group mb-2">
                    <input type="text" class="form-control" id="enrollCodeInput" placeholder="e.g. 2021-00123">
                    <button class="btn btn-outline-primary" type="button" id="enrollVerifyBtn">Verify</button>
                </div>
                <div id="enrollLookupResult" class="mt-3"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="enrollConfirmBtn" disabled>Enroll Student</button>
            </div>
        </div>
    </div>
</div>
<form method="post" id="enrollStudentForm" class="d-none">
    <input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>">
    <input type="hidden" name="action" value="enroll_student">
    <input type="hidden" name="student_db_id" id="enrollStudentDbId">
</form>

<div class="modal fade" id="studentModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-4">
            <div class="modal-header">
                <h5 class="modal-title">Edit Contact Info</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="post">
                <input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>">
                <input type="hidden" name="action" value="update_student_contact">
                <input type="hidden" name="id" id="studentIdField">
                <div class="modal-body">
                    <div class="d-flex align-items-center gap-3 mb-3 pb-3 border-bottom">
                        <i class="fa-solid fa-circle-user fa-2x text-secondary"></i>
                        <div>
                            <div class="fw-semibold" id="studentModalName"></div>
                            <div class="text-muted small" id="studentModalCode"></div>
                        </div>
                    </div>
                    <p class="text-muted small">Only an administrator can change the student's name, status, course, or room. You can update their contact details below.</p>
                    <div class="mb-3">
                        <label class="form-label">Phone</label>
                        <input type="text" class="form-control" name="phone" id="phoneField">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Guardian / Contact Person</label>
                        <input type="text" class="form-control" name="guardian" id="guardianField">
                    </div>
                    <div class="mb-0">
                        <label class="form-label">Guardian Email</label>
                        <input type="email" class="form-control" name="guardian_email" id="guardianEmailField" placeholder="Attendance alerts will be sent here">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', () => {
    function escapeHtml(str) {
        const div = document.createElement('div');
        div.textContent = str == null ? '' : String(str);
        return div.innerHTML;
    }

    const enrollStudentModalEl = document.getElementById('enrollStudentModal');
    const enrollCodeInput = document.getElementById('enrollCodeInput');
    const enrollVerifyBtn = document.getElementById('enrollVerifyBtn');
    const enrollLookupResult = document.getElementById('enrollLookupResult');
    const enrollConfirmBtn = document.getElementById('enrollConfirmBtn');
    const enrollStudentDbId = document.getElementById('enrollStudentDbId');

    function resetEnrollModal() {
        enrollCodeInput.value = '';
        enrollLookupResult.innerHTML = '';
        enrollConfirmBtn.disabled = true;
        enrollStudentDbId.value = '';
    }
    if (enrollStudentModalEl) {
        enrollStudentModalEl.addEventListener('show.bs.modal', resetEnrollModal);
    }

    async function verifyStudentCode() {
        const code = enrollCodeInput.value.trim();
        enrollConfirmBtn.disabled = true;
        enrollStudentDbId.value = '';
        if (!code) {
            enrollLookupResult.innerHTML = '<div class="alert alert-warning py-2 mb-0">Enter a student ID.</div>';
            return;
        }
        enrollLookupResult.innerHTML = '<div class="text-muted small">Looking up...</div>';
        try {
            const res = await fetch('ajax_student_lookup.php?code=' + encodeURIComponent(code) + '&subject_id=' + <?php echo (int) $filterSubjectId; ?>);
            const data = await res.json();
            if (!data.found) {
                enrollLookupResult.innerHTML = '<div class="alert alert-danger py-2 mb-0">No student found with that ID. Ask an admin to add them first.</div>';
                return;
            }
            if (data.already_in_class) {
                enrollLookupResult.innerHTML = '<div class="alert alert-warning py-2 mb-0"><strong>' + escapeHtml(data.name) + '</strong> is already enrolled in this class.</div>';
                return;
            }
            let noteHtml = '';
            if (data.room_id && data.room_name) {
                noteHtml = '<div class="text-muted small mt-2"><i class="fa-solid fa-circle-info me-1"></i> Also enrolled in ' + escapeHtml(data.room_name) + '. Adding them here keeps that enrollment and adds this class too.</div>';
            }
            enrollLookupResult.innerHTML = '<div class="border rounded-3 p-3 d-flex align-items-center gap-3">'
                + '<i class="fa-solid fa-circle-user fa-2x text-secondary"></i>'
                + '<div><div class="fw-semibold">' + escapeHtml(data.name) + '</div>'
                + '<div class="text-muted small">' + escapeHtml(data.student_id) + (data.course ? ' &middot; ' + escapeHtml(data.course) : '') + '</div></div></div>'
                + noteHtml;
            enrollStudentDbId.value = data.id;
            enrollConfirmBtn.disabled = false;
        } catch (e) {
            enrollLookupResult.innerHTML = '<div class="alert alert-danger py-2 mb-0">Lookup failed. Try again.</div>';
        }
    }
    if (enrollVerifyBtn) {
        enrollVerifyBtn.addEventListener('click', verifyStudentCode);
        enrollCodeInput.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') {
                e.preventDefault();
                verifyStudentCode();
            }
        });
        enrollConfirmBtn.addEventListener('click', () => {
            if (enrollStudentDbId.value) {
                document.getElementById('enrollStudentForm').submit();
            }
        });
    }

    const editButtons = document.querySelectorAll('.btn-edit');
    const studentModal = new bootstrap.Modal(document.getElementById('studentModal'));
    editButtons.forEach(btn => {
        btn.addEventListener('click', () => {
            const data = JSON.parse(btn.getAttribute('data-data'));
            document.getElementById('studentIdField').value = data.id;
            document.getElementById('studentModalName').textContent = ((data.first_name || '') + ' ' + (data.last_name || '')).trim();
            document.getElementById('studentModalCode').textContent = data.student_id || '';
            document.getElementById('phoneField').value = data.phone || '';
            document.getElementById('guardianField').value = data.guardian_name || '';
            document.getElementById('guardianEmailField').value = data.guardian_email || '';
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
            document.getElementById('viewStudentCourseRoom').textContent = (data.course_code || 'N/A') + ' — ' + (data.room_name || 'N/A');
            document.getElementById('viewStudentPhone').textContent = data.phone || '—';
            document.getElementById('viewStudentEmail').textContent = data.email || '—';
            const statusBadge = data.status === 'active'
                ? '<span class="badge bg-success">Active</span>'
                : '<span class="badge bg-secondary">Inactive</span>';
            document.getElementById('viewStudentStatus').innerHTML = statusBadge;
            viewStudentModal.show();
        });
    });

    let pendingUnenrollFormId = null;
    const unenrollStudentModal = new bootstrap.Modal(document.getElementById('unenrollStudentModal'));
    document.querySelectorAll('.btn-unenroll-student').forEach(btn => {
        btn.addEventListener('click', () => {
            pendingUnenrollFormId = btn.getAttribute('data-form-id');
            document.getElementById('unenrollStudentName').textContent = btn.getAttribute('data-student-name');
            unenrollStudentModal.show();
        });
    });
    document.getElementById('confirmUnenrollStudentBtn').addEventListener('click', () => {
        if (pendingUnenrollFormId) {
            document.getElementById(pendingUnenrollFormId).submit();
        }
    });

    const studentsTable = $('#myStudentsTable').DataTable({ responsive: true, paging: false, ordering: false, dom: 'rt' });
    const studentsSearchInput = document.getElementById('studentsSearchInput');
    if (studentsSearchInput) {
        studentsSearchInput.addEventListener('keyup', () => {
            studentsTable.search(studentsSearchInput.value).draw();
        });
    }

    document.querySelectorAll('.sp-qr-save-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            const target = document.getElementById(btn.getAttribute('data-target'));
            const filename = btn.getAttribute('data-filename');
            if (!target || typeof html2canvas === 'undefined') {
                return;
            }
            const originalText = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = 'Preparing...';
            html2canvas(target, { backgroundColor: '#ffffff', scale: 2 }).then(function (canvas) {
                const link = document.createElement('a');
                link.download = filename;
                link.href = canvas.toDataURL('image/png');
                link.click();
                btn.disabled = false;
                btn.innerHTML = originalText;
            }).catch(function () {
                btn.disabled = false;
                btn.innerHTML = originalText;
            });
        });
    });
});
</script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
<?php require_once __DIR__ . '/../includes/teacher_footer.php'; ?>
