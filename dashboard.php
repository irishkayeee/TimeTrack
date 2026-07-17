<?php
require_once __DIR__ . '/includes/functions.php';
requireLogin();
$user = currentUser();
if ($user['role'] === 'teacher') {
    redirect('teacher/dashboard.php');
}
if ($user['role'] === 'student') {
    redirect('student/dashboard.php');
}
redirect('admin/dashboard.php');
