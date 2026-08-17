<?php
require_once __DIR__ . '/functions.php';
checkRememberMe();
$user = currentUser();
$pageTitle = isset($pageTitle) ? $pageTitle : 'School Attendance Management';
$schoolName = getSetting('school_name', 'Attendance Management System');
$projectRoot = realpath(__DIR__ . '/..');
$scriptDir = realpath(dirname($_SERVER['SCRIPT_FILENAME']));
$rootPrefix = ($scriptDir === $projectRoot) ? '' : '../';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?> | <?php echo htmlspecialchars($schoolName); ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/css/bootstrap.min.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/datatables/1.13.6/css/dataTables.bootstrap5.min.css" />
    <link rel="stylesheet" href="<?php echo $rootPrefix; ?>assets/css/theme-tokens.css">
    <link rel="stylesheet" href="<?php echo $rootPrefix; ?>assets/css/style.css">
    <link rel="stylesheet" href="<?php echo $rootPrefix; ?>assets/css/dashboard-theme.css">
</head>
<body class="bg-light">
<header class="navbar navbar-expand-lg navbar-dark bg-primary sticky-top shadow-sm">
    <div class="container-fluid">
        <a class="navbar-brand d-flex align-items-center" href="<?php echo $rootPrefix; ?>dashboard.php">
            <i class="fa-solid fa-school me-2"></i>
            <span><?php echo htmlspecialchars($schoolName); ?></span>
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav" aria-controls="mainNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="mainNav">
            <?php if ($user): ?>
            <ul class="navbar-nav ms-auto align-items-center">
                <li class="nav-item me-3"><span class="nav-link text-white">Welcome, <?php echo htmlspecialchars($user['username']); ?></span></li>
                <li class="nav-item"><a class="nav-link" href="<?php echo $rootPrefix; ?>logout.php"><i class="fa-solid fa-right-from-bracket me-1"></i>Logout</a></li>
            </ul>
            <?php endif; ?>
        </div>
    </div>
</header>
<main class="container-fluid py-4">
<?php $flash = flashMessage(); if ($flash): ?>
<div class="toast-container position-fixed top-0 end-0 p-3">
    <div class="toast align-items-center text-bg-<?php echo $flash['type']; ?> border-0 show" role="alert" aria-live="assertive" aria-atomic="true">
        <div class="d-flex">
            <div class="toast-body">
                <?php echo htmlspecialchars($flash['message']); ?>
            </div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
        </div>
    </div>
</div>
<?php endif; ?>
