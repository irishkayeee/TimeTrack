<?php
require_once __DIR__ . '/../includes/functions.php';
requireRole(['teacher']);
$pageTitle = 'My Profile';
$pageSubtitle = 'View and update your personal information.';

$teacherId = currentTeacherId();
if ($teacherId === false) {
    flash('Your teacher profile is not set up. Contact an administrator.', 'danger');
    redirect('../dashboard.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        flash('Invalid request.', 'danger');
        redirect('profile.php');
    }

    if (($_POST['action'] ?? '') === 'update_profile') {
        $fullName = trim(sanitize($_POST['full_name'] ?? ''));
        $email = trim(sanitize($_POST['email'] ?? ''));
        $phone = trim(sanitize($_POST['phone'] ?? ''));

        if ($fullName === '') {
            flash('Full name is required.', 'danger');
            redirect('profile.php');
        }
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            flash('Please enter a valid email address.', 'danger');
            redirect('profile.php');
        }

        $lastSpace = strrpos($fullName, ' ');
        if ($lastSpace === false) {
            $firstName = $fullName;
            $lastName = '';
        } else {
            $firstName = substr($fullName, 0, $lastSpace);
            $lastName = substr($fullName, $lastSpace + 1);
        }

        $stmt = $mysqli->prepare('UPDATE teachers SET first_name = ?, last_name = ?, email = ?, phone = ? WHERE id = ?');
        $stmt->bind_param('ssssi', $firstName, $lastName, $email, $phone, $teacherId);
        $stmt->execute();
        $stmt->close();
        flash('Profile updated successfully.', 'success');
        redirect('profile.php');
    }

    if (($_POST['action'] ?? '') === 'update_photo') {
        if (empty($_FILES['photo']['name'])) {
            flash('Please choose a photo to upload.', 'danger');
            redirect('profile.php');
        }
        $ext = pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION);
        $allowed = ['jpg', 'jpeg', 'png'];
        if (!in_array(strtolower($ext), $allowed)) {
            flash('Photo must be a JPG or PNG file.', 'danger');
            redirect('profile.php');
        }
        $photo = 'uploads/teacher_' . time() . '.' . $ext;
        move_uploaded_file($_FILES['photo']['tmp_name'], __DIR__ . '/../' . $photo);
        $stmt = $mysqli->prepare('UPDATE teachers SET photo = ? WHERE id = ?');
        $stmt->bind_param('si', $photo, $teacherId);
        $stmt->execute();
        $stmt->close();
        flash('Profile photo updated.', 'success');
        redirect('profile.php');
    }

    if (($_POST['action'] ?? '') === 'change_password') {
        $user = currentUser();
        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        $stmt = $mysqli->prepare('SELECT password_hash FROM users WHERE id = ? LIMIT 1');
        $stmt->bind_param('i', $user['id']);
        $stmt->execute();
        $stmt->bind_result($currentHash);
        $stmt->fetch();
        $stmt->close();

        if (!password_verify($currentPassword, $currentHash)) {
            flash('Current password is incorrect.', 'danger');
        } elseif (strlen($newPassword) < 8) {
            flash('New password must be at least 8 characters.', 'danger');
        } elseif ($newPassword !== $confirmPassword) {
            flash('New password and confirmation do not match.', 'danger');
        } else {
            $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
            $stmt = $mysqli->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
            $stmt->bind_param('si', $newHash, $user['id']);
            $stmt->execute();
            $stmt->close();
            logActivity($user['id'], 'Changed password');
            flash('Password updated successfully.', 'success');
        }
        redirect('profile.php');
    }
}

$stmt = $mysqli->prepare('SELECT * FROM teachers WHERE id = ? LIMIT 1');
$stmt->bind_param('i', $teacherId);
$stmt->execute();
$teacher = $stmt->get_result()->fetch_assoc();
$stmt->close();

$fullName = trim($teacher['first_name'] . ' ' . $teacher['last_name']);

require_once __DIR__ . '/../includes/teacher_header.php';
?>
<div class="d-flex align-items-center" style="min-height: calc(100vh - 150px);">
<div class="row g-3 mx-auto w-100" style="max-width: 1100px;">
    <div class="col-lg-4">
        <div class="card p-4 text-center h-100 sp-profile-card">
            <div class="sp-profile-photo-wrap mx-auto">
                <?php if ($teacher['photo']): ?>
                    <img src="../<?php echo htmlspecialchars($teacher['photo']); ?>" class="sp-profile-photo" alt="">
                <?php else: ?>
                    <div class="sp-profile-photo sp-profile-photo-fallback"><i class="fa-solid fa-user"></i></div>
                <?php endif; ?>
                <button type="button" class="sp-profile-photo-btn" onclick="document.getElementById('photoUploadInput').click();"><i class="fa-solid fa-camera"></i></button>
            </div>
            <form method="post" enctype="multipart/form-data" id="photoUploadForm">
                <input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>">
                <input type="hidden" name="action" value="update_photo">
                <input type="file" name="photo" id="photoUploadInput" accept="image/png,image/jpeg" class="d-none" onchange="document.getElementById('photoUploadForm').submit();">
            </form>
            <h5 class="mt-3 mb-0"><?php echo htmlspecialchars($fullName); ?></h5>
            <p class="text-muted mb-2">Teacher</p>
            <hr class="my-3">
            <div class="text-start">
                <div class="sp-profile-info-row">
                    <i class="fa-solid fa-id-card"></i>
                    <div>
                        <div class="sp-profile-info-label">Teacher ID</div>
                        <div class="sp-profile-info-value"><?php echo htmlspecialchars($teacher['teacher_id']); ?></div>
                    </div>
                </div>
                <div class="sp-profile-info-row">
                    <i class="fa-solid fa-envelope"></i>
                    <div>
                        <div class="sp-profile-info-label">Email</div>
                        <div class="sp-profile-info-value"><?php echo htmlspecialchars($teacher['email'] ?: '—'); ?></div>
                    </div>
                </div>
                <div class="sp-profile-info-row">
                    <i class="fa-solid fa-phone"></i>
                    <div>
                        <div class="sp-profile-info-label">Phone</div>
                        <div class="sp-profile-info-value"><?php echo htmlspecialchars($teacher['phone'] ?: '—'); ?></div>
                    </div>
                </div>
                <div class="sp-profile-info-row mb-0">
                    <i class="fa-solid fa-shield-halved"></i>
                    <div>
                        <div class="sp-profile-info-label">Account Status</div>
                        <div><?php echo badgeStatus($teacher['status']); ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-8 d-flex flex-column">
        <div class="card p-4 mb-3">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h6 class="mb-0"><i class="fa-solid fa-user me-2 text-muted"></i>Personal Information</h6>
                <button type="button" class="btn btn-outline-success btn-sm rounded-pill" id="editProfileBtn"><i class="fa-solid fa-pen me-1"></i> Edit Profile</button>
            </div>
            <form method="post" id="profileForm">
                <input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>">
                <input type="hidden" name="action" value="update_profile">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label small text-muted">Full Name</label>
                        <input type="text" class="form-control sp-profile-editable" name="full_name" value="<?php echo htmlspecialchars($fullName); ?>" disabled required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small text-muted">Teacher ID</label>
                        <input type="text" class="form-control" value="<?php echo htmlspecialchars($teacher['teacher_id']); ?>" disabled>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small text-muted">Email Address</label>
                        <input type="email" class="form-control sp-profile-editable" name="email" value="<?php echo htmlspecialchars($teacher['email']); ?>" disabled>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small text-muted">Phone Number</label>
                        <input type="text" class="form-control sp-profile-editable" name="phone" value="<?php echo htmlspecialchars($teacher['phone']); ?>" disabled>
                    </div>
                </div>
                <p class="text-muted small mt-3 mb-0">Teacher ID is managed by the school. Contact an administrator to update it.</p>
                <div class="mt-3 d-none" id="profileFormActions">
                    <button type="submit" class="btn btn-primary rounded-pill">Save Changes</button>
                    <button type="button" class="btn btn-link" id="cancelEditBtn">Cancel</button>
                </div>
            </form>
        </div>

        <div class="card p-4 flex-grow-1">
            <h6 class="mb-3"><i class="fa-solid fa-lock me-2 text-muted"></i>Account &amp; Security</h6>
            <a href="#" class="sp-security-row" data-bs-toggle="modal" data-bs-target="#changePasswordModal">
                <i class="fa-solid fa-key"></i>
                <div class="flex-grow-1">
                    <div class="fw-semibold">Password</div>
                    <div class="text-muted small">Change your account password</div>
                </div>
                <i class="fa-solid fa-chevron-right text-muted"></i>
            </a>
        </div>
    </div>
</div>
</div>

<div class="modal fade" id="changePasswordModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-4">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title">Change Password</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="post">
                <input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>">
                <input type="hidden" name="action" value="change_password">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Current Password</label>
                        <input type="password" class="form-control" name="current_password" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">New Password</label>
                        <input type="password" class="form-control" name="new_password" minlength="8" required>
                        <div class="form-text">At least 8 characters.</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Confirm New Password</label>
                        <input type="password" class="form-control" name="confirm_password" minlength="8" required>
                    </div>
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-link text-decoration-none" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary rounded-pill">Update Password</button>
                </div>
            </form>
        </div>
    </div>
</div>
<script>
    var editBtn = document.getElementById('editProfileBtn');
    var cancelBtn = document.getElementById('cancelEditBtn');
    var actions = document.getElementById('profileFormActions');
    var editableInputs = document.querySelectorAll('.sp-profile-editable');
    var originalValues = Array.prototype.map.call(editableInputs, function (el) { return el.value; });

    editBtn.addEventListener('click', function () {
        editableInputs.forEach(function (el) { el.disabled = false; });
        actions.classList.remove('d-none');
        editBtn.classList.add('d-none');
    });

    cancelBtn.addEventListener('click', function () {
        editableInputs.forEach(function (el, i) {
            el.value = originalValues[i];
            el.disabled = true;
        });
        actions.classList.add('d-none');
        editBtn.classList.remove('d-none');
    });
</script>
<?php require_once __DIR__ . '/../includes/teacher_footer.php'; ?>
