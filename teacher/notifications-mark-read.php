<?php
require_once __DIR__ . '/../includes/functions.php';
requireRole(['teacher']);
header('Content-Type: application/json');

$teacherDbId = currentTeacherId();
if ($teacherDbId === false) {
    echo json_encode(['status' => 'error', 'message' => 'Teacher profile not found.']);
    exit;
}

$notificationId = intval($_POST['id'] ?? 0);
if (!$notificationId) {
    echo json_encode(['status' => 'error', 'message' => 'Missing notification id.']);
    exit;
}

markTeacherNotificationRead($notificationId, $teacherDbId);
echo json_encode(['status' => 'success', 'unreadCount' => unreadTeacherNotificationCount($teacherDbId)]);
