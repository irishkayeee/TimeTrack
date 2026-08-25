<?php
require_once __DIR__ . '/includes/functions.php';
header('Content-Type: application/json');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method.']);
    exit;
}
$data = json_decode(file_get_contents('php://input'), true);
$qrValue = sanitize($data['qrValue'] ?? '');
$subjectId = intval($data['subjectId'] ?? 0);
if (!$qrValue) {
    echo json_encode(['status' => 'error', 'message' => 'QR value is missing.']);
    exit;
}
if (!$subjectId) {
    echo json_encode(['status' => 'error', 'message' => 'No subject/session selected for this scan.']);
    exit;
}

// Per-subject QR codes are encoded as "<studentToken>|SUBJ<subjectId>". Older
// plain per-student QR codes (no "|SUBJ" suffix) are still accepted and are
// matched purely against the teacher's currently selected session.
$qrSubjectId = null;
if (preg_match('/^(.+)\|SUBJ(\d+)$/', $qrValue, $matches)) {
    $qrValue = $matches[1];
    $qrSubjectId = intval($matches[2]);
    if ($qrSubjectId !== $subjectId) {
        echo json_encode(['status' => 'error', 'message' => 'This QR code is for a different subject.']);
        exit;
    }
}
$stmt = $mysqli->prepare("SELECT id, name, section_id, start_time, day_of_week, absent_cutoff_minutes FROM subjects WHERE id = ? AND status = 'active' LIMIT 1");
$stmt->bind_param('i', $subjectId);
$stmt->execute();
$subject = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$subject) {
    echo json_encode(['status' => 'error', 'message' => 'Selected subject/session is invalid or inactive.']);
    exit;
}

$stmt = $mysqli->prepare('SELECT s.id, s.first_name, s.last_name, s.student_id, s.course_id, s.section_id, s.photo, c.code AS course_code, sec.section_name FROM students s LEFT JOIN courses c ON s.course_id = c.id LEFT JOIN sections sec ON s.section_id = sec.id WHERE s.qr_code = ? OR s.student_id = ? LIMIT 1');
$stmt->bind_param('ss', $qrValue, $qrValue);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows === 0) {
    echo json_encode(['status' => 'error', 'message' => 'Student not found.']);
    exit;
}
$student = $result->fetch_assoc();
$stmt->close();

if (intval($student['section_id']) !== intval($subject['section_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'This student is not enrolled in this subject\'s section.']);
    exit;
}

$existing = $mysqli->prepare('SELECT COUNT(*) FROM attendance WHERE student_id = ? AND subject_id = ? AND date = CURDATE()');
$existing->bind_param('ii', $student['id'], $subjectId);
$existing->execute();
$existing->bind_result($countToday);
$existing->fetch();
$existing->close();
if ($countToday > 0) {
    echo json_encode(['status' => 'duplicate', 'message' => 'Attendance already recorded for this subject today.', 'student' => $student]);
    exit;
}

$scanTime = date('H:i:s');
$status = computeAttendanceStatus($subject['start_time'], $scanTime, $subject['absent_cutoff_minutes']);

$stmt = $mysqli->prepare('INSERT INTO attendance (student_id, course_id, section_id, subject_id, status, scan_type, date, time, created_at) VALUES (?, ?, ?, ?, ?, ?, CURDATE(), ?, NOW())');
$stmt->bind_param('iiiisss', $student['id'], $student['course_id'], $student['section_id'], $subjectId, $status, $status, $scanTime);
$stmt->execute();
$stmt->close();

$notifTitles = ['present' => 'Attendance Recorded', 'late' => 'Marked Late', 'absent' => 'Marked Absent'];
$notifMessage = 'You were marked ' . $status . ' in ' . $subject['name'] . ' today.';
notifyStudent($student['id'], $status, $notifTitles[$status] ?? 'Attendance Recorded', $notifMessage, $subjectId);

logActivity($_SESSION['user']['id'] ?? 0, 'Scanned QR for student ' . $student['student_id']);
echo json_encode(['status' => 'success', 'message' => 'Attendance saved.', 'student' => $student, 'statusLabel' => $status]);
