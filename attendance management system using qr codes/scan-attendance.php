<?php
require_once __DIR__ . '/includes/functions.php';
header('Content-Type: application/json');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method.']);
    exit;
}
$data = json_decode(file_get_contents('php://input'), true);
$qrValue = sanitize($data['qrValue'] ?? '');
if (!$qrValue) {
    echo json_encode(['status' => 'error', 'message' => 'QR value is missing.']);
    exit;
}
$stmt = $mysqli->prepare('SELECT s.id, s.first_name, s.last_name, s.student_id, s.course_id, s.room_id, c.code AS course_code, sec.room_name FROM students s LEFT JOIN courses c ON s.course_id = c.id LEFT JOIN rooms sec ON s.room_id = sec.id WHERE s.qr_code = ? OR s.student_id = ? LIMIT 1');
$stmt->bind_param('ss', $qrValue, $qrValue);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows === 0) {
    echo json_encode(['status' => 'error', 'message' => 'Student not found.']);
    exit;
}
$student = $result->fetch_assoc();
$stmt->close();
$existing = $mysqli->prepare('SELECT COUNT(*) FROM attendance WHERE student_id = ? AND date = CURDATE()');
$existing->bind_param('i', $student['id']);
$existing->execute();
$existing->bind_result($countToday);
$existing->fetch();
$existing->close();
$status = 'present';
$hour = date('H');
if ($hour >= 8 && $hour < 9) {
    $status = 'present';
} elseif ($hour >= 9 && $hour < 10) {
    $status = 'late';
} else {
    $status = 'present';
}
if ($countToday > 0) {
    echo json_encode(['status' => 'duplicate', 'message' => 'Attendance already recorded today.', 'student' => $student]);
    exit;
}
$stmt = $mysqli->prepare('INSERT INTO attendance (student_id, course_id, room_id, status, scan_type, date, time, created_at) VALUES (?, ?, ?, ?, ?, CURDATE(), CURTIME(), NOW())');
$stmt->bind_param('iiiss', $student['id'], $student['course_id'], $student['room_id'], $status, $status);
$stmt->execute();
$stmt->close();
logActivity($_SESSION['user']['id'] ?? 0, 'Scanned QR for student ' . $student['student_id']);
echo json_encode(['status' => 'success', 'message' => 'Attendance saved.', 'student' => $student, 'statusLabel' => $status]);
