<?php
$schoolName = getSetting('school_name', 'Attendance Management System');
$currentPage = basename($_SERVER['PHP_SELF']);
$adminUser = currentUser();
$navItems = [
    ['href' => 'dashboard.php', 'icon' => 'fa-table-cells', 'label' => 'Dashboard'],
    ['href' => 'academics.php', 'icon' => 'fa-graduation-cap', 'label' => 'Academics'],
    ['href' => 'attendance.php', 'icon' => 'fa-clipboard-check', 'label' => 'Attendance'],
];
if (($adminUser['role'] ?? '') === 'superadmin') {
    $navItems[] = ['href' => 'users.php', 'icon' => 'fa-user-shield', 'label' => 'Admin Accounts'];
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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/datatables.net-bs5/2.3.8/dataTables.bootstrap5.min.css" />
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Caveat:wght@600;700&display=swap">
    <link rel="stylesheet" href="../assets/css/theme-tokens.css">
    <link rel="stylesheet" href="../assets/css/dashboard-theme.css">
    <link rel="stylesheet" href="../assets/css/student-portal.css?v=<?php echo filemtime(__DIR__ . '/../assets/css/student-portal.css'); ?>">
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
        <div class="sp-nav-footer">
            <a href="#" class="sp-nav-link" data-bs-toggle="modal" data-bs-target="#logoutConfirmModal">
                <span class="sp-nav-icon"><i class="fa-solid fa-right-from-bracket"></i></span>
                <span class="sp-nav-label">Logout</span>
            </a>
        </div>
        <div class="sp-sidebar-illustration">
            <img src="../assets/images/sidebar.png" alt="">
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
                <div class="dropdown">
                    <div class="sp-user sp-user-toggle" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <span class="sp-user-initials"><?php echo htmlspecialchars(nameInitials($adminUser['username'] ?? 'Admin')); ?></span>
                        <div>
                            <div class="sp-user-name"><?php echo htmlspecialchars($adminUser['username'] ?? 'Admin'); ?></div>
                            <div class="sp-user-role"><?php echo htmlspecialchars(ucfirst($adminUser['role'] ?? 'admin')); ?></div>
                        </div>
                        <i class="fa-solid fa-chevron-down sp-user-caret"></i>
                    </div>
                    <ul class="dropdown-menu dropdown-menu-end sp-user-menu">
                        <li class="sp-user-menu__head">
                            <span class="sp-user-initials"><?php echo htmlspecialchars(nameInitials($adminUser['username'] ?? 'Admin')); ?></span>
                            <div>
                                <div class="sp-user-menu__name"><?php echo htmlspecialchars($adminUser['username'] ?? 'Admin'); ?></div>
                                <div class="sp-user-menu__role"><?php echo htmlspecialchars(ucfirst($adminUser['role'] ?? 'admin')); ?></div>
                            </div>
                        </li>
                        <li>
                            <a class="dropdown-item sp-user-menu__item" href="profile.php">
                                <span class="sp-user-menu__icon"><i class="fa-solid fa-user"></i></span>
                                <span><span class="sp-user-menu__label">Profile</span><span class="sp-user-menu__hint">View and edit your account</span></span>
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item sp-user-menu__item" href="settings.php">
                                <span class="sp-user-menu__icon"><i class="fa-solid fa-gear"></i></span>
                                <span><span class="sp-user-menu__label">Settings</span><span class="sp-user-menu__hint">School and system preferences</span></span>
                            </a>
                        </li>
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <a class="dropdown-item sp-user-menu__item sp-user-menu__item--danger" href="#" data-bs-toggle="modal" data-bs-target="#logoutConfirmModal">
                                <span class="sp-user-menu__icon"><i class="fa-solid fa-right-from-bracket"></i></span>
                                <span class="sp-user-menu__label">Logout</span>
                            </a>
                        </li>
                    </ul>
                </div>
            </div>
        </header>
        <?php $flash = flashMessage(); $welcome = welcomeBannerMessage(); ?>
        <main class="sp-content">
