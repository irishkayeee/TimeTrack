<?php
require_once __DIR__ . '/../includes/functions.php';
requireRole(['student']);
header('Content-Type: application/json');

$studentDbId = currentStudentId();
if ($studentDbId === false) {
    echo json_encode(['status' => 'error', 'message' => 'Student profile not found.']);
    exit;
}

$notificationId = intval($_POST['id'] ?? 0);
if (!$notificationId) {
    echo json_encode(['status' => 'error', 'message' => 'Missing notification id.']);
    exit;
}

markNotificationRead($notificationId, $studentDbId);
echo json_encode(['status' => 'success', 'unreadCount' => unreadNotificationCount($studentDbId)]);
