<?php
require_once __DIR__ . '/includes/auth.php';
$pageTitle = 'Reset Password';
if (empty($_SESSION['reset_user_id'])) {
    flash('Please request a password reset code first.', 'danger');
    redirect('forgot-password.php');
}
$userId = $_SESSION['reset_user_id'];
$verified = !empty($_SESSION['reset_otp_verified']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        flash('Invalid request.', 'danger');
        redirect('reset-password.php');
    }

    if (($_POST['action'] ?? '') === 'verify_otp') {
        $otp = sanitize($_POST['otp'] ?? '');
        if (!preg_match('/^\d{6}$/', $otp)) {
            flash('Enter the 6-digit code sent to your email.', 'danger');
            redirect('reset-password.php');
        }
        if (verifyResetOtp($userId, $otp)) {
            $_SESSION['reset_otp'] = $otp;
            $_SESSION['reset_otp_verified'] = true;
            unset($_SESSION['reset_otp_debug']);
            flash('Code verified. Enter your new password.', 'success');
        } else {
            flash('That code is invalid or has expired. Request a new one.', 'danger');
        }
        redirect('reset-password.php');
    }

    if (($_POST['action'] ?? '') === 'set_password') {
        if (!$verified) {
            flash('Please verify your code first.', 'danger');
            redirect('reset-password.php');
        }
        $password = $_POST['password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';
        if ($password !== $confirm || strlen($password) < 6) {
            flash('Passwords must match and be at least 6 characters.', 'danger');
            redirect('reset-password.php');
        }
        if (resetPasswordWithOtp($userId, $_SESSION['reset_otp'], $password)) {
            unset($_SESSION['reset_user_id'], $_SESSION['reset_otp'], $_SESSION['reset_otp_verified'], $_SESSION['reset_otp_debug']);
            flash('Password updated successfully. Please login.', 'success');
            redirect('landing.php?login=1');
        }
        unset($_SESSION['reset_otp'], $_SESSION['reset_otp_verified']);
        flash('Your code expired while setting the password. Request a new one.', 'danger');
        redirect('reset-password.php');
    }
}
$flash = flashMessage();
$schoolName = getSetting('school_name', 'Dr. Francisco L. Calingasan Memorial Colleges Foundation Inc.');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $verified ? 'Set New Password' : 'Enter Code'; ?> | TimeTrack</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/css/bootstrap.min.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" />
    <link rel="stylesheet" href="assets/css/theme-tokens.css">
    <link rel="stylesheet" href="assets/css/landing.css">
</head>
<body class="landing-body">

<div class="lp-viewport">
<nav class="navbar navbar-expand-lg navbar-light lp-navbar">
    <div class="container-fluid flex-wrap">
        <a class="d-flex align-items-center gap-3 text-decoration-none" href="landing.php#home">
            <div class="lp-brand-mark">
                <img src="assets/images/iblogo.png" alt="TimeTrack logo">
            </div>
            <div class="lp-brand-text">
                <div class="lp-brand-name"><span class="lp-brand-time">Time</span><span class="lp-brand-track">Track</span></div>
                <small>Smart Attendance. Stronger Community.</small>
            </div>
        </a>
        <button class="navbar-toggler order-lg-3 ms-2" type="button" data-bs-toggle="collapse" data-bs-target="#lpNav" aria-controls="lpNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse order-lg-2" id="lpNav">
            <div class="lp-nav-links mt-3 mt-lg-0 ms-lg-auto me-lg-4">
                <a href="landing.php#home" class="lp-nav-link">
                    <i class="fa-solid fa-house"></i>
                    <span>Home</span>
                </a>
                <span class="lp-nav-divider"></span>
                <a href="landing.php#features" class="lp-nav-link">
                    <i class="fa-solid fa-table-cells"></i>
                    <span>Features</span>
                </a>
            </div>
        </div>
        <a href="landing.php?login=1" class="lp-btn-login order-lg-3 ms-lg-3">
            <i class="fa-solid fa-user"></i> Login
        </a>
    </div>
</nav>

<section class="lp-hero" id="home">
    <div class="lp-hero-photo"></div>
    <div class="container-fluid lp-hero-inner">
        <div class="row">
            <div class="col-lg-6">
                <span class="lp-hero-badge"><i class="fa-solid fa-circle"></i> Welcome to TimeTrack</span>
                <h1 class="lp-hero-title">
                    <span class="lp-hero-title-line">
                        <span class="lp-hero-title-dark">Smart</span> <span class="lp-hero-title-accent">Attendance.</span>
                    </span>
                    <span class="lp-hero-title-line lp-hero-title-accent">Stronger Community.</span>
                </h1>
                <div class="lp-hero-divider"></div>
                <p class="lp-hero-desc">
                    TimeTrack is a modern attendance tracking system designed to record and monitor
                    college students' attendance during class sessions, with accuracy and ease.
                    Empowering leaders. Building accountability.
                </p>
                <div class="d-flex flex-wrap gap-3">
                    <a href="landing.php?login=1" class="lp-btn-primary">Get Started <i class="fa-solid fa-arrow-right"></i></a>
                    <a href="landing.php#features" class="lp-btn-outline">Learn More <i class="fa-solid fa-circle-info"></i></a>
                </div>
                <p class="lp-hero-quote"><i class="fa-solid fa-quote-left"></i>Don't just fly, soar high!</p>
            </div>
        </div>
    </div>
</section>
</div>

<section class="lp-why" id="features">
    <div class="lp-why-dots" aria-hidden="true"></div>
    <i class="fa-solid fa-leaf lp-why-leaf" aria-hidden="true"></i>
    <div class="container">
        <div class="text-center">
            <span class="lp-why-badge"><i class="fa-solid fa-star"></i> Powerful Features, Built for You</span>
            <h2 class="lp-why-title">Powerful Features for <span class="lp-why-title-accent">Smarter Attendance</span></h2>
            <div class="lp-why-divider">
                <span></span>
                <i class="fa-solid fa-circle"></i>
                <span></span>
            </div>
        </div>
        <div class="row g-4">
            <div class="col-md-6 col-lg-3">
                <div class="lp-feature-card">
                    <div class="lp-feature-icon"><i class="fa-solid fa-clipboard-check"></i></div>
                    <h6>Accurate Tracking</h6>
                    <p>Record attendance in real-time with precision and reliability.</p>
                </div>
            </div>
            <div class="col-md-6 col-lg-3">
                <div class="lp-feature-card">
                    <div class="lp-feature-icon"><i class="fa-solid fa-chart-simple"></i></div>
                    <h6>Organized Records</h6>
                    <p>Keep all your attendance data well-organized and easy to access.</p>
                </div>
            </div>
            <div class="col-md-6 col-lg-3">
                <div class="lp-feature-card">
                    <div class="lp-feature-icon"><i class="fa-solid fa-bell"></i></div>
                    <h6>Instant Notifications</h6>
                    <p>Receive timely updates and reminders for every activity.</p>
                </div>
            </div>
            <div class="col-md-6 col-lg-3">
                <div class="lp-feature-card">
                    <div class="lp-feature-icon"><i class="fa-solid fa-shield-halved"></i></div>
                    <h6>Secure &amp; Trusted</h6>
                    <p>Your data is safe with our secure and reliable system.</p>
                </div>
            </div>
        </div>
    </div>
</section>

<footer class="lp-footer">
    <p>&copy; <?php echo date('Y'); ?> TimeTrack. All rights reserved.</p>
</footer>

<div class="modal-backdrop fade show"></div>
<div class="modal fade show" style="display: block;" tabindex="-1" role="dialog" aria-labelledby="resetModalLabel">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content lp-login-modal">
            <a href="landing.php" class="btn-close lp-login-close" aria-label="Close"></a>
            <div class="modal-body text-center">
                <img src="assets/images/iblogo.png" alt="TimeTrack logo" class="lp-login-logo">
                <?php if (!$verified): ?>
                    <h4 class="lp-login-title" id="resetModalLabel">Enter Code</h4>
                    <p class="lp-login-subtitle">Enter the 6-digit code sent to your email. It expires 5 minutes after it was sent.</p>
                    <?php if ($flash): ?>
                        <div class="alert alert-<?php echo htmlspecialchars($flash['type']); ?>"><?php echo htmlspecialchars($flash['message']); ?></div>
                    <?php endif; ?>
                    <?php if (!empty($_SESSION['reset_otp_debug'])): ?>
                        <div class="alert alert-warning">
                            Your code: <strong><?php echo htmlspecialchars($_SESSION['reset_otp_debug']); ?></strong>
                        </div>
                    <?php endif; ?>
                    <form method="post" class="text-start">
                        <input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>">
                        <input type="hidden" name="action" value="verify_otp">
                        <div class="lp-login-field mb-3">
                            <i class="fa-solid fa-key"></i>
                            <input type="text" name="otp" class="form-control text-center" inputmode="numeric" pattern="\d{6}" maxlength="6" autocomplete="one-time-code" placeholder="6-digit code" required autofocus>
                        </div>
                        <button type="submit" class="lp-login-submit w-100">Verify Code <i class="fa-solid fa-arrow-right"></i></button>
                    </form>
                <?php else: ?>
                    <h4 class="lp-login-title" id="resetModalLabel">Set New Password</h4>
                    <p class="lp-login-subtitle">Your code has been verified.</p>
                    <?php if ($flash): ?>
                        <div class="alert alert-<?php echo htmlspecialchars($flash['type']); ?>"><?php echo htmlspecialchars($flash['message']); ?></div>
                    <?php endif; ?>
                    <form method="post" class="text-start">
                        <input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>">
                        <input type="hidden" name="action" value="set_password">
                        <div class="lp-login-field mb-3">
                            <i class="fa-solid fa-lock"></i>
                            <input type="password" name="password" class="form-control" placeholder="New password" required autofocus>
                        </div>
                        <div class="lp-login-field mb-3">
                            <i class="fa-solid fa-lock"></i>
                            <input type="password" name="confirm_password" class="form-control" placeholder="Confirm password" required>
                        </div>
                        <button type="submit" class="lp-login-submit w-100">Update Password <i class="fa-solid fa-arrow-right"></i></button>
                    </form>
                <?php endif; ?>
                <div class="mt-3">
                    <a href="forgot-password.php" class="lp-login-forgot"><i class="fa-solid fa-arrow-left"></i> Didn't get a code? Request again</a>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/js/bootstrap.bundle.min.js"></script>
</body>
</html>
