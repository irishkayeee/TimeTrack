<?php
require_once __DIR__ . '/includes/auth.php';
$pageTitle = 'Reset Password';
$token = sanitize($_GET['token'] ?? '');
if (!$token) {
    flash('Invalid reset token.', 'danger');
    redirect('index.php');
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        flash('Invalid request.', 'danger');
        redirect('reset-password.php?token=' . $token);
    }
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';
    if ($password !== $confirm || strlen($password) < 6) {
        flash('Passwords must match and be at least 6 characters.', 'danger');
        redirect('reset-password.php?token=' . $token);
    }
    if (resetPassword($token, $password)) {
        flash('Password updated successfully. Please login.', 'success');
        redirect('index.php');
    }
    flash('Reset token is invalid or expired.', 'danger');
    redirect('index.php');
}
require_once __DIR__ . '/includes/header.php';
?>
<div class="row justify-content-center align-items-center min-vh-75">
    <div class="col-md-5">
        <div class="card shadow-lg border-0 rounded-4">
            <div class="card-body p-4">
                <h3 class="card-title text-center mb-3">Reset Password</h3>
                <form method="post" class="needs-validation" novalidate>
                    <input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>">
                    <div class="mb-3">
                        <label class="form-label">New Password</label>
                        <input type="password" class="form-control" name="password" required>
                        <div class="invalid-feedback">Enter a new password.</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Confirm Password</label>
                        <input type="password" class="form-control" name="confirm_password" required>
                        <div class="invalid-feedback">Confirm your password.</div>
                    </div>
                    <button class="btn btn-primary w-100" type="submit">Update Password</button>
                </form>
            </div>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>