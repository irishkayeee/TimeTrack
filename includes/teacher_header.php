<?php
$schoolName = getSetting('school_name', 'Attendance Management System');
$currentPage = basename($_SERVER['PHP_SELF']);
$teacherDbId = currentTeacherId();
$sidebarTeacher = null;
$teacherUnreadCount = 0;
if ($teacherDbId !== false) {
    $sidebarStmt = $mysqli->prepare('SELECT first_name, last_name, photo FROM teachers WHERE id = ? LIMIT 1');
    $sidebarStmt->bind_param('i', $teacherDbId);
    $sidebarStmt->execute();
    $sidebarTeacher = $sidebarStmt->get_result()->fetch_assoc();
    $sidebarStmt->close();
    generateTeacherClassNotifications($mysqli, $teacherDbId);
    $teacherUnreadCount = unreadTeacherNotificationCount($teacherDbId);
}
// This header is also included from pages outside /teacher/ (e.g. admin/scanner.php,
// shared for the teacher view of Take Attendance). Its nav links are written relative
// to /teacher/, so from anywhere else they must be prefixed back to that folder or
// they resolve against the including page's own directory and 404.
$teacherBase = (strtolower(basename(dirname($_SERVER['SCRIPT_FILENAME']))) === 'teacher') ? '' : '../teacher/';
$navItems = [
    ['href' => 'dashboard.php', 'icon' => 'fa-table-cells', 'label' => 'Dashboard'],
    ['href' => 'subjects.php', 'icon' => 'fa-book', 'label' => 'My Classes'],
    ['href' => 'announcements.php', 'icon' => 'fa-bell', 'label' => 'Notifications', 'badge' => $teacherUnreadCount],
    ['href' => 'profile.php', 'icon' => 'fa-user', 'label' => 'My Profile'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle ?? 'Teacher Portal'); ?> | <?php echo htmlspecialchars($schoolName); ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/css/bootstrap.min.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/datatables.net-bs5/2.3.8/dataTables.bootstrap5.min.css" />
    <link rel="stylesheet" href="../assets/css/theme-tokens.css">
    <link rel="stylesheet" href="../assets/css/dashboard-theme.css">
    <link rel="stylesheet" href="../assets/css/student-portal.css">
</head>
<body class="student-body">
<div class="sp-shell" id="spShell">
    <aside class="sp-sidebar" id="spSidebar">
        <div class="sp-brand">
            <img src="../assets/images/iblogo2.png" alt="TimeTrack logo">
            <div class="sp-brand-name">TimeTrack</div>
            <div class="sp-brand-sub">Teacher Portal</div>
        </div>
        <div class="sp-brand-divider"></div>
        <nav class="sp-nav">
            <?php foreach ($navItems as $item): ?>
                <a href="<?php echo $teacherBase . $item['href']; ?>" class="sp-nav-link<?php echo $currentPage === $item['href'] ? ' active' : ''; ?>">
                    <span class="sp-nav-icon"><i class="fa-solid <?php echo $item['icon']; ?>"></i></span>
                    <span class="sp-nav-label"><?php echo $item['label']; ?></span>
                    <?php if ($item['href'] === 'announcements.php'): ?>
                        <?php $badgeCount = (int) ($item['badge'] ?? 0); ?>
                        <span class="sp-nav-badge<?php echo $badgeCount === 0 ? ' d-none' : ''; ?>" id="spNavNotifBadge"><?php echo $badgeCount > 9 ? '9+' : $badgeCount; ?></span>
                    <?php elseif (!empty($item['badge'])): ?>
                        <span class="sp-nav-badge"><?php echo $item['badge'] > 9 ? '9+' : $item['badge']; ?></span>
                    <?php endif; ?>
                    <i class="fa-solid fa-chevron-right sp-nav-chevron"></i>
                </a>
            <?php endforeach; ?>
        </nav>
        <div class="sp-sidebar-illustration">
            <img src="../assets/images/sidebar.png" alt="">
        </div>
        <div class="sp-nav-footer">
            <a href="#" class="sp-nav-link" data-bs-toggle="modal" data-bs-target="#logoutConfirmModal">
                <span class="sp-nav-icon"><i class="fa-solid fa-right-from-bracket"></i></span>
                <span class="sp-nav-label">Logout</span>
            </a>
        </div>
    </aside>
    <div class="sp-main">
        <header class="sp-topbar">
            <div class="d-flex align-items-center gap-3">
                <button class="sp-sidebar-toggle" id="spSidebarToggle" type="button" aria-label="Toggle menu">
                    <i class="fa-solid fa-bars"></i>
                </button>
                <div>
                    <h1 class="sp-topbar-title"><?php echo htmlspecialchars($pageTitle ?? 'Dashboard'); ?></h1>
                    <?php if (!empty($pageSubtitle)): ?>
                        <p class="sp-topbar-subtitle"><?php echo htmlspecialchars($pageSubtitle); ?></p>
                    <?php endif; ?>
                </div>
            </div>
            <div class="sp-topbar-right">
                <div class="sp-user">
                    <?php if ($sidebarTeacher && $sidebarTeacher['photo']): ?>
                        <img src="../<?php echo htmlspecialchars($sidebarTeacher['photo']); ?>" alt="">
                    <?php else: ?>
                        <i class="fa-solid fa-circle-user fa-2x text-secondary"></i>
                    <?php endif; ?>
                    <div>
                        <div class="sp-user-name"><?php echo htmlspecialchars($sidebarTeacher ? $sidebarTeacher['first_name'] . ' ' . $sidebarTeacher['last_name'] : 'Teacher'); ?></div>
                        <div class="sp-user-role">Teacher</div>
                    </div>
                </div>
            </div>
        </header>
        <?php $welcome = welcomeBannerMessage(); if ($welcome): ?>
        <div class="sp-welcome-overlay" id="spWelcomeOverlay">
            <div class="sp-welcome-card">
                <div class="sp-welcome-icon"><i class="fa-solid fa-circle-check"></i></div>
                <h5 class="sp-welcome-title">Welcome back, <?php echo htmlspecialchars($welcome['name']); ?>!</h5>
                <p class="sp-welcome-message"><?php echo htmlspecialchars($welcome['message']); ?></p>
                <button type="button" class="sp-welcome-btn" onclick="spDismissWelcome()">Let's Go <i class="fa-solid fa-arrow-right"></i></button>
            </div>
        </div>
        <script>
            function spDismissWelcome() {
                var el = document.getElementById('spWelcomeOverlay');
                if (!el) return;
                el.classList.add('sp-flash-hide');
                setTimeout(function () { el.remove(); }, 350);
            }
        </script>
        <?php endif; ?>
        <?php $classJoinCode = classJoinCodeMessage(); if ($classJoinCode): ?>
        <div class="sp-welcome-overlay" id="spClassCodeOverlay">
            <div class="sp-welcome-card">
                <div class="sp-welcome-icon"><i class="fa-solid fa-circle-check"></i></div>
                <h5 class="sp-welcome-title">Class created!</h5>
                <p class="sp-welcome-message">Share this join code with your students for <strong><?php echo htmlspecialchars($classJoinCode['name']); ?></strong>:</p>
                <div class="sp-welcome-code"><?php echo htmlspecialchars($classJoinCode['code']); ?></div>
                <button type="button" class="sp-welcome-btn" onclick="spDismissClassCode()">Done</button>
            </div>
        </div>
        <script>
            function spDismissClassCode() {
                var el = document.getElementById('spClassCodeOverlay');
                if (!el) return;
                el.classList.add('sp-flash-hide');
                setTimeout(function () { el.remove(); }, 350);
            }
        </script>
        <?php endif; ?>
        <?php $flash = flashMessage(); if ($flash): ?>
        <div class="sp-flash-overlay" id="spFlashOverlay">
            <div class="sp-flash-card sp-flash-<?php echo htmlspecialchars($flash['type']); ?>">
                <i class="fa-solid <?php echo $flash['type'] === 'success' ? 'fa-circle-check' : ($flash['type'] === 'danger' ? 'fa-circle-exclamation' : 'fa-circle-info'); ?>"></i>
                <span><?php echo htmlspecialchars($flash['message']); ?></span>
                <button type="button" class="sp-flash-close" onclick="spDismissFlash()" aria-label="Close">&times;</button>
            </div>
        </div>
        <script>
            function spDismissFlash() {
                var el = document.getElementById('spFlashOverlay');
                if (!el) return;
                el.classList.add('sp-flash-hide');
                setTimeout(function () { el.remove(); }, 400);
            }
            setTimeout(spDismissFlash, 5000);
        </script>
        <?php endif; ?>
        <main class="sp-content">
