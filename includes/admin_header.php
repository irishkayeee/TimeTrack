<?php
$schoolName = getSetting('school_name', 'Attendance Management System');
$currentPage = basename($_SERVER['PHP_SELF']);
$adminUser = currentUser();
$navItems = [
    ['href' => 'dashboard.php', 'icon' => 'fa-table-cells', 'label' => 'Dashboard'],
    ['href' => 'students.php', 'icon' => 'fa-user-graduate', 'label' => 'Students'],
    ['href' => 'teachers.php', 'icon' => 'fa-chalkboard-user', 'label' => 'Teachers'],
    ['href' => 'courses.php', 'icon' => 'fa-graduation-cap', 'label' => 'Courses'],
    ['href' => 'sections.php', 'icon' => 'fa-people-group', 'label' => 'Sections'],
    ['href' => 'subjects.php', 'icon' => 'fa-book', 'label' => 'Subjects'],
    ['href' => 'attendance.php', 'icon' => 'fa-clipboard-check', 'label' => 'Attendance'],
    ['href' => 'qr-generator.php', 'icon' => 'fa-qrcode', 'label' => 'QR Generator'],
    ['href' => 'scanner.php', 'icon' => 'fa-camera', 'label' => 'Scanner'],
    ['href' => 'reports.php', 'icon' => 'fa-chart-column', 'label' => 'Reports'],
    ['href' => 'settings.php', 'icon' => 'fa-gear', 'label' => 'Settings'],
];
if (($adminUser['role'] ?? '') === 'superadmin') {
    $navItems[] = ['href' => 'users.php', 'icon' => 'fa-user-shield', 'label' => 'User Accounts'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle ?? 'Admin Portal'); ?> | <?php echo htmlspecialchars($schoolName); ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/css/bootstrap.min.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/datatables/1.13.6/css/dataTables.bootstrap5.min.css" />
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
            <div class="sp-brand-sub">Admin Portal</div>
        </div>
        <div class="sp-brand-divider"></div>
        <nav class="sp-nav">
            <?php foreach ($navItems as $item): ?>
                <a href="<?php echo $item['href']; ?>" class="sp-nav-link<?php echo $currentPage === $item['href'] ? ' active' : ''; ?>">
                    <span class="sp-nav-icon"><i class="fa-solid <?php echo $item['icon']; ?>"></i></span>
                    <span class="sp-nav-label"><?php echo $item['label']; ?></span>
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
                    <i class="fa-solid fa-circle-user fa-2x text-secondary"></i>
                    <div>
                        <div class="sp-user-name"><?php echo htmlspecialchars($adminUser['username'] ?? 'Admin'); ?></div>
                        <div class="sp-user-role"><?php echo htmlspecialchars(ucfirst($adminUser['role'] ?? 'admin')); ?></div>
                    </div>
                </div>
            </div>
        </header>
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
