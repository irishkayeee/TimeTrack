<?php
require_once __DIR__ . '/../includes/functions.php';
requireRole(['admin', 'superadmin']);
$pageTitle = 'My Profile';
$pageSubtitle = 'View and update your account information.';

$user = currentUser();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        flash('Invalid request.', 'danger');
        redirect('profile.php');
    }

    if (($_POST['action'] ?? '') === 'update_profile') {
        $username = trim(sanitize($_POST['username'] ?? ''));
        $email = trim(sanitize($_POST['email'] ?? ''));

        if ($username === '') {
            flash('Username is required.', 'danger');
            redirect('profile.php');
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            flash('Please enter a valid email address.', 'danger');
            redirect('profile.php');
        }

        $stmt = $mysqli->prepare('UPDATE users SET username = ?, email = ? WHERE id = ?');
        $stmt->bind_param('ssi', $username, $email, $user['id']);
        $stmt->execute();
        $stmt->close();
        $_SESSION['user']['username'] = $username;
        $_SESSION['user']['email'] = $email;
        flash('Profile updated successfully.', 'success');
        redirect('profile.php');
    }

    if (($_POST['action'] ?? '') === 'change_password') {
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

$stmt = $mysqli->prepare('SELECT * FROM users WHERE id = ? LIMIT 1');
$stmt->bind_param('i', $user['id']);
$stmt->execute();
$account = $stmt->get_result()->fetch_assoc();
$stmt->close();

require_once __DIR__ . '/../includes/admin_header.php';
?>
<div class="d-flex align-items-center" style="min-height: calc(100vh - 150px);">
<div class="row g-3 mx-auto w-100" style="max-width: 1100px;">
    <div class="col-lg-4">
        <div class="card p-4 text-center h-100 sp-profile-card">
            <div class="sp-profile-photo-wrap mx-auto">
                <div class="sp-profile-photo sp-profile-photo-fallback"><i class="fa-solid fa-user"></i></div>
            </div>
            <h5 class="mt-3 mb-0"><?php echo htmlspecialchars($account['username']); ?></h5>
            <p class="text-muted mb-2"><?php echo htmlspecialchars(ucfirst($account['role'])); ?></p>
            <hr class="my-3">
            <div class="text-start">
                <div class="sp-profile-info-row">
                    <i class="fa-solid fa-envelope"></i>
                    <div>
                        <div class="sp-profile-info-label">Email</div>
                        <div class="sp-profile-info-value"><?php echo htmlspecialchars($account['email']); ?></div>
                    </div>
                </div>
                <div class="sp-profile-info-row">
                    <i class="fa-solid fa-calendar"></i>
                    <div>
                        <div class="sp-profile-info-label">Member Since</div>
                        <div class="sp-profile-info-value"><?php echo formatDate($account['created_at']); ?></div>
                    </div>
                </div>
                <div class="sp-profile-info-row mb-0">
                    <i class="fa-solid fa-shield-halved"></i>
                    <div>
                        <div class="sp-profile-info-label">Account Status</div>
                        <div><?php echo badgeStatus($account['status']); ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-8 d-flex flex-column">
        <div class="card p-4 mb-3">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h6 class="mb-0"><i class="fa-solid fa-user me-2 text-muted"></i>Account Information</h6>
                <button type="button" class="btn btn-outline-success btn-sm rounded-pill" id="editProfileBtn"><i class="fa-solid fa-pen me-1"></i> Edit Profile</button>
            </div>
            <form method="post" id="profileForm">
                <input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>">
                <input type="hidden" name="action" value="update_profile">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label small text-muted">Username</label>
                        <input type="text" class="form-control sp-profile-editable" name="username" value="<?php echo htmlspecialchars($account['username']); ?>" disabled required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small text-muted">Role</label>
                        <input type="text" class="form-control" value="<?php echo htmlspecialchars(ucfirst($account['role'])); ?>" disabled>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small text-muted">Email Address</label>
                        <input type="email" class="form-control sp-profile-editable" name="email" value="<?php echo htmlspecialchars($account['email']); ?>" disabled required>
                    </div>
                </div>
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
<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>
