<?php
require_once __DIR__ . '/../includes/functions.php';
requireRole(['teacher']);
header('Content-Type: application/json');

$teacherId = currentTeacherId();
$code = trim($_GET['code'] ?? '');
if ($code === '' || $teacherId === false) {
    echo json_encode(['found' => false]);
    exit;
}

$row = findStudentByCode($mysqli, $code);
if (!$row) {
    echo json_encode(['found' => false]);
    exit;
}

$subjectId = intval($_GET['subject_id'] ?? 0);
$alreadyInClass = false;
if ($subjectId) {
    $subjStmt = $mysqli->prepare('SELECT room_id FROM subjects WHERE id = ? AND teacher_id = ?');
    $subjStmt->bind_param('ii', $subjectId, $teacherId);
    $subjStmt->execute();
    $subjStmt->bind_result($subjectRoomId);
    $subjFound = $subjStmt->fetch();
    $subjStmt->close();

    if ($subjFound) {
        if ($row['room_id'] !== null && (int) $row['room_id'] === (int) $subjectRoomId) {
            $alreadyInClass = true;
        } else {
            $check = $mysqli->prepare('SELECT id FROM enrollments WHERE student_id = ? AND subject_id = ? LIMIT 1');
            $check->bind_param('ii', $row['id'], $subjectId);
            $check->execute();
            $check->store_result();
            $alreadyInClass = $check->num_rows > 0;
            $check->close();
        }
    }
}

echo json_encode([
    'found' => true,
    'id' => (int) $row['id'],
    'student_id' => $row['student_id'],
    'name' => trim($row['first_name'] . ' ' . $row['last_name']),
    'course' => $row['course_code'],
    'room_id' => $row['room_id'] !== null ? (int) $row['room_id'] : null,
    'room_name' => $row['room_name'],
    'already_in_class' => $alreadyInClass,
]);
