<?php
require_once __DIR__ . '/includes/auth.php';
$pageTitle = 'Forgot Password';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        flash('Invalid request.', 'danger');
        redirect('forgot-password.php');
    }
    $username = sanitize($_POST['username'] ?? '');
    $token = requestPasswordReset($username);
    if ($token) {
        flash('Password reset link generated. Use the token in the link shown below.', 'success');
        $_SESSION['reset_link'] = 'reset-password.php?token=' . $token;
        redirect('forgot-password.php');
    }
    flash('Unable to find that account.', 'danger');
    redirect('forgot-password.php');
}
require_once __DIR__ . '/includes/header.php';
?>
<div class="row justify-content-center align-items-center min-vh-75">
    <div class="col-md-5">
        <div class="card shadow-lg border-0 rounded-4">
            <div class="card-body p-4">
                <h3 class="card-title text-center mb-3">Forgot Password</h3>
                <form method="post" class="needs-validation" novalidate>
                    <input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>">
                    <div class="mb-3">
                        <label class="form-label">Username or Email</label>
                        <input type="text" class="form-control" name="username" required>
                        <div class="invalid-feedback">Enter the registered username.</div>
                    </div>
                    <button class="btn btn-primary w-100" type="submit">Request Reset</button>
                </form>
                <?php if (!empty($_SESSION['reset_link'])): ?>
                    <div class="alert alert-warning mt-3">
                        Reset URL: <a href="<?php echo htmlspecialchars($_SESSION['reset_link']); ?>"><?php echo htmlspecialchars($_SESSION['reset_link']); ?></a>
                        <?php unset($_SESSION['reset_link']); ?>
                    </div>
                <?php endif; ?>
                <div class="text-center mt-3">
                    <a href="index.php">Return to login</a>
                </div>
            </div>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>