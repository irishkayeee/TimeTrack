<?php
require_once __DIR__ . '/includes/functions.php';
if (isLoggedIn()) {
    redirect('dashboard.php');
}
$pageTitle = 'Login';
require_once __DIR__ . '/includes/header.php';
?>
<div class="row justify-content-center align-items-center min-vh-75">
    <div class="col-md-5">
        <div class="card shadow-lg border-0 rounded-4">
            <div class="card-body p-4">
                <h3 class="card-title text-center mb-3">School Attendance Login</h3>
                <form action="login.php" method="post" class="needs-validation" novalidate>
                    <input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>">
                    <div class="mb-3">
                        <label class="form-label">Username</label>
                        <input type="text" class="form-control form-control-lg" name="username" required>
                        <div class="invalid-feedback">Enter your username.</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Password</label>
                        <input type="password" class="form-control form-control-lg" name="password" required>
                        <div class="invalid-feedback">Enter your password.</div>
                    </div>
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" name="remember" id="remember">
                        <label class="form-check-label" for="remember">Remember Me</label>
                    </div>
                    <button type="submit" class="btn btn-primary btn-lg w-100">Sign In</button>
                    <div class="text-center mt-3">
                        <a href="forgot-password.php">Forgot Password?</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>