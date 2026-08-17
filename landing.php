<?php
require_once __DIR__ . '/includes/functions.php';
if (isLoggedIn()) {
    $user = currentUser();
    redirect($user['role'] === 'teacher' ? 'teacher/dashboard.php' : 'admin/dashboard.php');
}
$schoolName = getSetting('school_name', 'Dr. Francisco L. Calingasan Memorial Colleges Foundation Inc.');
$flash = flashMessage();
$openLogin = isset($_GET['login']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TimeTrack | <?php echo htmlspecialchars($schoolName); ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/css/bootstrap.min.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" />
    <link rel="stylesheet" href="assets/css/theme-tokens.css">
    <link rel="stylesheet" href="assets/css/landing.css">
</head>
<body class="landing-body">

<div class="lp-viewport">
<nav class="navbar navbar-expand-lg navbar-light lp-navbar">
    <div class="container-fluid flex-wrap">
        <a class="d-flex align-items-center gap-3 text-decoration-none" href="#home">
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
                <a href="#home" class="lp-nav-link active">
                    <i class="fa-solid fa-house"></i>
                    <span>Home</span>
                </a>
                <span class="lp-nav-divider"></span>
                <a href="#features" class="lp-nav-link">
                    <i class="fa-solid fa-table-cells"></i>
                    <span>Features</span>
                </a>
            </div>
        </div>
        <button type="button" class="lp-btn-login order-lg-3 ms-lg-3" data-bs-toggle="modal" data-bs-target="#loginModal">
            <i class="fa-solid fa-user"></i> Login
        </button>
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
                    <a href="#" class="lp-btn-primary" data-bs-toggle="modal" data-bs-target="#loginModal">Get Started <i class="fa-solid fa-arrow-right"></i></a>
                    <a href="#features" class="lp-btn-outline">Learn More <i class="fa-solid fa-circle-info"></i></a>
                </div>
                <p class="lp-hero-quote"><i class="fa-solid fa-quote-left"></i>Don't just fly, soar high!</p>
            </div>
        </div>
    </div>
</section>
</div>

<div class="lp-viewport">
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
            <div class="col-md-6 col-lg-4">
                <div class="lp-feature-card">
                    <div class="lp-feature-icon"><i class="fa-solid fa-clipboard-check"></i></div>
                    <h6>Accurate Tracking</h6>
                    <p>Record attendance in real-time with precision and reliability.</p>
                </div>
            </div>
            <div class="col-md-6 col-lg-4">
                <div class="lp-feature-card">
                    <div class="lp-feature-icon"><i class="fa-solid fa-chart-simple"></i></div>
                    <h6>Organized Records</h6>
                    <p>Keep all your attendance data well-organized and easy to access.</p>
                </div>
            </div>
            <div class="col-md-6 col-lg-4">
                <div class="lp-feature-card">
                    <div class="lp-feature-icon"><i class="fa-solid fa-bell"></i></div>
                    <h6>Instant Notifications</h6>
                    <p>Receive timely updates and reminders for every activity.</p>
                </div>
            </div>
            <div class="col-md-6 col-lg-4">
                <div class="lp-feature-card">
                    <div class="lp-feature-icon"><i class="fa-solid fa-shield-halved"></i></div>
                    <h6>Secure &amp; Trusted</h6>
                    <p>Your data is safe with our secure and reliable system.</p>
                </div>
            </div>
            <div class="col-md-6 col-lg-4">
                <div class="lp-feature-card">
                    <div class="lp-feature-icon"><i class="fa-solid fa-chart-pie"></i></div>
                    <h6>Insightful Reports</h6>
                    <p>Generate detailed reports and gain valuable insights instantly.</p>
                </div>
            </div>
            <div class="col-md-6 col-lg-4">
                <div class="lp-feature-card">
                    <div class="lp-feature-icon"><i class="fa-solid fa-users"></i></div>
                    <h6>User Management</h6>
                    <p>Manage users and permissions easily and efficiently.</p>
                </div>
            </div>
        </div>
    </div>
</section>

<footer class="lp-footer">
    <p>&copy; <?php echo date('Y'); ?> TimeTrack. All rights reserved.</p>
</footer>
</div>

<div class="modal fade" id="loginModal" tabindex="-1" aria-labelledby="loginModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content lp-login-modal">
            <button type="button" class="btn-close lp-login-close" data-bs-dismiss="modal" aria-label="Close"></button>
            <div class="modal-body text-center">
                <img src="assets/images/iblogo.png" alt="TimeTrack logo" class="lp-login-logo">
                <h4 class="lp-login-title" id="loginModalLabel">Welcome Back!</h4>
                <p class="lp-login-subtitle">Sign in to continue to TimeTrack</p>
                <?php if ($flash): ?>
                    <div class="alert alert-<?php echo htmlspecialchars($flash['type']); ?>"><?php echo htmlspecialchars($flash['message']); ?></div>
                <?php endif; ?>
                <form action="login.php" method="post" class="text-start">
                    <input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>">
                    <div class="lp-login-field mb-3">
                        <i class="fa-solid fa-envelope"></i>
                        <input type="text" name="username" class="form-control" placeholder="Email or Student ID" required>
                    </div>
                    <div class="lp-login-field lp-login-field-password mb-3">
                        <i class="fa-solid fa-lock"></i>
                        <input type="password" name="password" id="loginPassword" class="form-control" placeholder="Password" required>
                        <button type="button" class="lp-login-eye" id="toggleLoginPassword"><i class="fa-solid fa-eye"></i></button>
                    </div>
                    <div class="d-flex justify-content-end align-items-center mb-3">
                        <a href="forgot-password.php" class="lp-login-forgot"><i class="fa-solid fa-key"></i> Forgot Password?</a>
                    </div>
                    <button type="submit" class="lp-login-submit w-100">Login <i class="fa-solid fa-arrow-right"></i></button>
                </form>
            </div>
        </div>
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/js/bootstrap.bundle.min.js"></script>
<script>
    var toggleLoginPassword = document.getElementById('toggleLoginPassword');
    var loginPassword = document.getElementById('loginPassword');
    if (toggleLoginPassword) {
        toggleLoginPassword.addEventListener('click', function () {
            var isHidden = loginPassword.type === 'password';
            loginPassword.type = isHidden ? 'text' : 'password';
            this.innerHTML = isHidden ? '<i class="fa-solid fa-eye-slash"></i>' : '<i class="fa-solid fa-eye"></i>';
        });
    }
    <?php if ($openLogin || $flash): ?>
    var loginModalEl = document.getElementById('loginModal');
    if (loginModalEl) {
        new bootstrap.Modal(loginModalEl).show();
    }
    <?php endif; ?>
</script>
</body>
</html>
