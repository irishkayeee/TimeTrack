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
        $sectionId = intval($_POST['section_id'] ?? 0) ?: null;
        $phone = sanitize($_POST['phone'] ?? '');
        $email = sanitize($_POST['email'] ?? '');
        $status = sanitize($_POST['status'] ?? 'active');
        $photo = '';
        if (!empty($_FILES['photo']['name'])) {
            $ext = pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION);
            $allowed = ['jpg', 'jpeg', 'png'];
            if (in_array(strtolower($ext), $allowed)) {
                $photo = 'uploads/teacher_' . time() . '.' . $ext;
                move_uploaded_file($_FILES['photo']['tmp_name'], __DIR__ . '/../' . $photo);
            }
        }
        if ($id) {
            $stmt = $mysqli->prepare('UPDATE teachers SET teacher_id = ?, first_name = ?, last_name = ?, subject = ?, section_id = ?, phone = ?, email = ?, status = ?' . ($photo ? ', photo = ?' : '') . ' WHERE id = ?');
            if ($photo) {
                $stmt->bind_param('ssssissssi', $teacherId, $firstName, $lastName, $subject, $sectionId, $phone, $email, $status, $photo, $id);
            } else {
                $stmt->bind_param('ssssisssi', $teacherId, $firstName, $lastName, $subject, $sectionId, $phone, $email, $status, $id);
            }
            $stmt->execute();
            $stmt->close();
            flash('Teacher updated successfully.', 'success');
        } else {
            $stmt = $mysqli->prepare('INSERT INTO teachers (teacher_id, first_name, last_name, subject, section_id, phone, email, photo, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())');
            $stmt->bind_param('ssssissss', $teacherId, $firstName, $lastName, $subject, $sectionId, $phone, $email, $photo, $status);
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
        redirect('teachers.php');
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
        redirect('teachers.php');
    }
    if (in_array($_POST['action'], ['create_teacher_credentials', 'regenerate_teacher_credentials']) && !empty($_POST['teacher_id'])) {
        $tid = intval($_POST['teacher_id']);
        $stmt = $mysqli->prepare('SELECT teacher_id, user_id, email FROM teachers WHERE id = ?');
        $stmt->bind_param('i', $tid);
        $stmt->execute();
        $stmt->bind_result($teacherCode, $linkedUserId, $teacherEmail);
        if ($stmt->fetch()) {
            $stmt->close();
            $plainPassword = '';
            if ($linkedUserId) {
                regenerateCredentials($mysqli, $linkedUserId, $plainPassword);
                flashCredentials($teacherCode, $plainPassword);
            } else {
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
        redirect('teachers.php');
    }
}
$courses = $mysqli->query('SELECT id, code FROM courses ORDER BY code');
$sections = $mysqli->query('SELECT id, section_name FROM sections ORDER BY section_name');
$teachers = $mysqli->query('SELECT t.*, sec.section_name FROM teachers t LEFT JOIN sections sec ON t.section_id = sec.id ORDER BY t.created_at DESC');
$newCredentials = flashCredentialsMessage();
require_once __DIR__ . '/../includes/admin_header.php';
?>
<?php if ($newCredentials): ?>
    <div class="alert alert-success rounded-4">
        <strong>Login credentials generated.</strong> Share these with the teacher now — the password will not be shown again.
        <div class="mt-2">
            <span class="me-3">Username: <code id="credUsername"><?php echo htmlspecialchars($newCredentials['username']); ?></code></span>
            <span>Password: <code id="credPassword"><?php echo htmlspecialchars($newCredentials['password']); ?></code></span>
        </div>
    </div>
<?php endif; ?>
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
                    <th>Created</th>
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
                        <td><?php echo formatDateTime($row['created_at']); ?></td>
                        <td>
                            <div class="d-flex align-items-center gap-1">
                                <button class="btn btn-sm btn-outline-secondary btn-icon btn-view-teacher" data-data='<?php echo json_encode($row); ?>' title="View"><i class="fa-solid fa-eye"></i></button>
                                <button class="btn btn-sm btn-outline-primary btn-icon btn-edit-teacher" data-data='<?php echo json_encode($row); ?>' title="Edit"><i class="fa-solid fa-pen"></i></button>
                                <form method="post" class="d-inline-block" onsubmit="return confirm('Delete this teacher?');">
                                    <input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>">
                                    <input type="hidden" name="action" value="delete_teacher">
                                    <input type="hidden" name="teacher_id" value="<?php echo $row['id']; ?>">
                                    <button class="btn btn-sm btn-outline-danger btn-icon" title="Delete"><i class="fa-solid fa-trash"></i></button>
                                </form>
                                <form method="post" class="d-inline-block" onsubmit="return confirm('<?php echo $row['user_id'] ? 'Reset this teacher\'s password?' : 'Create a login for this teacher?'; ?>');">
                                    <input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>">
                                    <input type="hidden" name="action" value="<?php echo $row['user_id'] ? 'regenerate_teacher_credentials' : 'create_teacher_credentials'; ?>">
                                    <input type="hidden" name="teacher_id" value="<?php echo $row['id']; ?>">
                                    <button class="btn btn-sm btn-outline-secondary"><?php echo $row['user_id'] ? 'Reset Password' : 'Create Login'; ?></button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
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
                    <div class="col-md-6">
                        <label class="form-label">Photo</label>
                        <input type="file" class="form-control" name="photo" accept="image/*">
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
<script>
document.addEventListener('DOMContentLoaded', function () {
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
$('#teachersTable').DataTable({ responsive: true });
});
</script>
<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>