<?php
require_once __DIR__ . '/../includes/functions.php';
requireRole(['student']);
header('Content-Type: application/json');

$studentDbId = currentStudentId();
if ($studentDbId === false) {
    echo json_encode(['error' => 'Student profile not set up.']);
    exit;
}

$type = sanitize($_GET['type'] ?? 'trend');
$subjectId = intval($_GET['subject_id'] ?? 0);
$month = sanitize($_GET['month'] ?? 'all');
$monthFilter = ($month !== 'all' && preg_match('/^\d{4}-\d{2}$/', $month)) ? $month : null;

$stmt = $mysqli->prepare('SELECT room_id FROM students WHERE id = ?');
$stmt->bind_param('i', $studentDbId);
$stmt->execute();
$stmt->bind_result($roomId);
$stmt->fetch();
$stmt->close();

if ($type === 'subjects') {
    $result = ['labels' => [], 'data' => [], 'subjectIds' => []];
    if ($roomId) {
        $stmt = $mysqli->prepare("SELECT id, code FROM subjects WHERE room_id = ? AND status = 'active' ORDER BY name");
        $stmt->bind_param('i', $roomId);
        $stmt->execute();
        $subjectsResult = $stmt->get_result();
        while ($subjectRow = $subjectsResult->fetch_assoc()) {
            $query = "SELECT COUNT(*) AS total, SUM(status IN ('present','late')) AS attended FROM attendance WHERE student_id = ? AND subject_id = ?";
            $types = 'ii';
            $params = [$studentDbId, $subjectRow['id']];
            if ($monthFilter) {
                $query .= " AND DATE_FORMAT(date, '%Y-%m') = ?";
                $types .= 's';
                $params[] = $monthFilter;
            }
            $countStmt = $mysqli->prepare($query);
            $countStmt->bind_param($types, ...$params);
            $countStmt->execute();
            $counts = $countStmt->get_result()->fetch_assoc();
            $countStmt->close();
            $total = (int) $counts['total'];
            $rate = $total ? round((((int) $counts['attended']) / $total) * 100) : 0;
            $result['labels'][] = $subjectRow['code'];
            $result['data'][] = $rate;
            $result['subjectIds'][] = (int) $subjectRow['id'];
        }
        $stmt->close();
    }
    echo json_encode($result);
    exit;
}

// type === 'trend'
$query = "SELECT YEARWEEK(date, 1) AS yw, COUNT(*) AS total, SUM(status IN ('present','late')) AS attended FROM attendance WHERE student_id = ?";
$types = 'i';
$params = [$studentDbId];
if ($subjectId) {
    $query .= ' AND subject_id = ?';
    $types .= 'i';
    $params[] = $subjectId;
}
if ($monthFilter) {
    $query .= " AND DATE_FORMAT(date, '%Y-%m') = ?";
    $types .= 's';
    $params[] = $monthFilter;
}
$query .= ' GROUP BY YEARWEEK(date, 1) ORDER BY yw LIMIT 12';
$stmt = $mysqli->prepare($query);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$weeksResult = $stmt->get_result();
$labels = [];
$data = [];
$i = 1;
while ($row = $weeksResult->fetch_assoc()) {
    $labels[] = 'Week ' . $i;
    $total = (int) $row['total'];
    $data[] = $total ? round((((int) $row['attended']) / $total) * 100) : 0;
    $i++;
}
$stmt->close();
echo json_encode(['labels' => $labels, 'data' => $data]);
