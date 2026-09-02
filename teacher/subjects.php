<?php
require_once __DIR__ . '/../includes/functions.php';
requireRole(['teacher']);
$pageTitle = 'My Classes';
$pageSubtitle = 'Manage the schedule and attendance for your classes.';

$teacherId = currentTeacherId();
if ($teacherId === false) {
    flash('Your teacher profile is not set up. Contact an administrator.', 'danger');
    redirect('../dashboard.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        flash('Invalid request.', 'danger');
        redirect('subjects.php');
    }
    if ($_POST['action'] === 'save_schedule') {
        $id = intval($_POST['id'] ?? 0);
        $allowedDays = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
        $selectedDays = array_values(array_intersect($allowedDays, $_POST['day_of_week'] ?? []));
        $startTime = sanitize($_POST['start_time'] ?? '07:30');
        $endTime = sanitize($_POST['end_time'] ?? '');
        $endTime = $endTime ?: null;

        if (empty($selectedDays)) {
            flash('Select at least one day.', 'danger');
            redirect('subjects.php');
        }

        $refStmt = $mysqli->prepare('SELECT code, name, room_id, subject_room, credit_units, status FROM subjects WHERE id = ? AND teacher_id = ? LIMIT 1');
        $refStmt->bind_param('ii', $id, $teacherId);
        $refStmt->execute();
        $ref = $refStmt->get_result()->fetch_assoc();
        $refStmt->close();

        if (!$ref) {
            flash('Class not found.', 'danger');
            redirect('subjects.php');
        }

        $groupStmt = $mysqli->prepare('SELECT id, day_of_week FROM subjects WHERE teacher_id = ? AND room_id = ? AND code = ?');
        $groupStmt->bind_param('iis', $teacherId, $ref['room_id'], $ref['code']);
        $groupStmt->execute();
        $existingByDay = [];
        $groupResult = $groupStmt->get_result();
        while ($row = $groupResult->fetch_assoc()) {
            $existingByDay[$row['day_of_week']] = (int) $row['id'];
        }
        $groupStmt->close();

        foreach ($selectedDays as $day) {
            if (isset($existingByDay[$day])) {
                $rowId = $existingByDay[$day];
                $upd = $mysqli->prepare('UPDATE subjects SET start_time = ?, end_time = ? WHERE id = ? AND teacher_id = ?');
                $upd->bind_param('ssii', $startTime, $endTime, $rowId, $teacherId);
                $upd->execute();
                $upd->close();
            } else {
                $ins = $mysqli->prepare('INSERT INTO subjects (code, name, teacher_id, room_id, day_of_week, start_time, end_time, subject_room, credit_units, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
                $ins->bind_param('ssiissssis', $ref['code'], $ref['name'], $teacherId, $ref['room_id'], $day, $startTime, $endTime, $ref['subject_room'], $ref['credit_units'], $ref['status']);
                $ins->execute();
                $ins->close();
            }
        }

        foreach ($existingByDay as $day => $rowId) {
            if (!in_array($day, $selectedDays, true)) {
                $del = $mysqli->prepare('DELETE FROM subjects WHERE id = ? AND teacher_id = ?');
                $del->bind_param('ii', $rowId, $teacherId);
                $del->execute();
                $del->close();
            }
        }

        flash('Schedule updated.', 'success');
        redirect('subjects.php');
    }
    if ($_POST['action'] === 'save_policy') {
        $id = intval($_POST['id'] ?? 0);
        $useDefault = isset($_POST['use_default']);
        $cutoff = $useDefault ? null : max(1, intval($_POST['absent_cutoff_minutes'] ?? 20));

        $refStmt = $mysqli->prepare('SELECT code, room_id FROM subjects WHERE id = ? AND teacher_id = ? LIMIT 1');
        $refStmt->bind_param('ii', $id, $teacherId);
        $refStmt->execute();
        $ref = $refStmt->get_result()->fetch_assoc();
        $refStmt->close();

        if (!$ref) {
            flash('Class not found.', 'danger');
            redirect('subjects.php');
        }

        $upd = $mysqli->prepare('UPDATE subjects SET absent_cutoff_minutes = ? WHERE teacher_id = ? AND room_id = ? AND code = ?');
        $upd->bind_param('iiis', $cutoff, $teacherId, $ref['room_id'], $ref['code']);
        $upd->execute();
        $upd->close();

        flash('Attendance policy updated.', 'success');
        redirect('subjects.php');
    }
    if ($_POST['action'] === 'save_subject_room') {
        $id = intval($_POST['id'] ?? 0);
        $subjectRoom = trim(sanitize($_POST['subject_room'] ?? ''));
        $subjectRoom = $subjectRoom !== '' ? $subjectRoom : null;

        $refStmt = $mysqli->prepare('SELECT code, room_id FROM subjects WHERE id = ? AND teacher_id = ? LIMIT 1');
        $refStmt->bind_param('ii', $id, $teacherId);
        $refStmt->execute();
        $ref = $refStmt->get_result()->fetch_assoc();
        $refStmt->close();

        if (!$ref) {
            flash('Class not found.', 'danger');
            redirect('subjects.php');
        }

        $upd = $mysqli->prepare('UPDATE subjects SET subject_room = ? WHERE teacher_id = ? AND room_id = ? AND code = ?');
        $upd->bind_param('siis', $subjectRoom, $teacherId, $ref['room_id'], $ref['code']);
        $upd->execute();
        $upd->close();

        flash('Subject room updated.', 'success');
        redirect('subjects.php');
    }
    if ($_POST['action'] === 'create_class') {
        $code = trim(sanitize($_POST['code'] ?? ''));
        $name = trim(sanitize($_POST['name'] ?? ''));
        $roomId = intval($_POST['room_id'] ?? 0);
        $allowedDays = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
        $selectedDays = array_values(array_intersect($allowedDays, $_POST['day_of_week'] ?? []));
        $startTime = sanitize($_POST['start_time'] ?? '07:30');
        $endTime = sanitize($_POST['end_time'] ?? '');
        $endTimeParam = $endTime ?: null;
        $subjectRoom = trim(sanitize($_POST['subject_room'] ?? ''));
        $subjectRoomParam = $subjectRoom !== '' ? $subjectRoom : null;
        $creditUnits = intval($_POST['credit_units'] ?? 0) ?: null;
        $importantNote = trim(sanitize($_POST['important_note'] ?? ''));
        $importantNoteParam = $importantNote !== '' ? $importantNote : null;

        if ($code === '' || $name === '' || !$roomId || empty($selectedDays)) {
            flash('Please fill in the class code, name, room, and select at least one day.', 'danger');
            redirect('subjects.php');
        }

        $roomCheck = $mysqli->prepare('SELECT id FROM rooms WHERE id = ? LIMIT 1');
        $roomCheck->bind_param('i', $roomId);
        $roomCheck->execute();
        $roomCheck->store_result();
        $validRoom = $roomCheck->num_rows > 0;
        $roomCheck->close();

        if (!$validRoom) {
            flash('Please select a valid room.', 'danger');
            redirect('subjects.php');
        }

        $newSubjectId = null;
        foreach ($selectedDays as $day) {
            $ins = $mysqli->prepare('INSERT INTO subjects (code, name, teacher_id, room_id, day_of_week, start_time, end_time, subject_room, credit_units, important_note, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, "active", NOW())');
            $ins->bind_param('ssiissssis', $code, $name, $teacherId, $roomId, $day, $startTime, $endTimeParam, $subjectRoomParam, $creditUnits, $importantNoteParam);
            $ins->execute();
            $newSubjectId = $mysqli->insert_id;
            $ins->close();
        }

        $joinCode = ensureClassJoinCode($mysqli, $newSubjectId);
        flash('Class created! Share this join code with your students: ' . $joinCode, 'success');
        redirect('subjects.php');
    }
}

$statusFilter = sanitize($_GET['status'] ?? 'active');
$courseFilter = intval($_GET['course_id'] ?? 0);
$roomFilter = intval($_GET['room_id'] ?? 0);

// Program (course) and Block (room) options, scoped to this teacher's own classes only.
$filterOptionsStmt = $mysqli->prepare('SELECT DISTINCT c.id AS course_id, c.code AS course_code, c.name AS course_name, sec.id AS room_id, sec.room_name, sec.year_level
    FROM subjects sub
    JOIN rooms sec ON sub.room_id = sec.id
    JOIN courses c ON sec.course_id = c.id
    WHERE sub.teacher_id = ?
    ORDER BY c.code, sec.year_level, sec.room_name');
$filterOptionsStmt->bind_param('i', $teacherId);
$filterOptionsStmt->execute();
$filterOptionsResult = $filterOptionsStmt->get_result();
$programOptions = [];
$blockOptions = [];
while ($opt = $filterOptionsResult->fetch_assoc()) {
    $programOptions[(int) $opt['course_id']] = $opt['course_code'] . ' - ' . $opt['course_name'];
    $blockOptions[(int) $opt['room_id']] = $opt['year_level'] . ' - ' . $opt['room_name'];
}
$filterOptionsStmt->close();

$query = 'SELECT sub.*, sec.room_name, sec.year_level, c.id AS course_id, c.code AS course_code
    FROM subjects sub
    JOIN rooms sec ON sub.room_id = sec.id
    JOIN courses c ON sec.course_id = c.id
    WHERE sub.teacher_id = ?';
$types = 'i';
$params = [$teacherId];
if (in_array($statusFilter, ['active', 'inactive'], true)) {
    $query .= ' AND sub.status = ?';
    $types .= 's';
    $params[] = $statusFilter;
}
if ($courseFilter) {
    $query .= ' AND c.id = ?';
    $types .= 'i';
    $params[] = $courseFilter;
}
if ($roomFilter) {
    $query .= ' AND sec.id = ?';
    $types .= 'i';
    $params[] = $roomFilter;
}
$query .= ' ORDER BY sub.day_of_week, sub.start_time';
$stmt = $mysqli->prepare($query);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$subjectsResult = $stmt->get_result();
$subjectRows = [];
while ($row = $subjectsResult->fetch_assoc()) {
    $subjectRows[] = $row;
}
$stmt->close();

// Map of "roomId|code" => [days already scheduled], so the Edit Schedule
// modal can pre-check every day this class meets on, not just this one row.
$groupDaysStmt = $mysqli->prepare('SELECT room_id, code, day_of_week FROM subjects WHERE teacher_id = ?');
$groupDaysStmt->bind_param('i', $teacherId);
$groupDaysStmt->execute();
$groupDaysResult = $groupDaysStmt->get_result();
$groupDaysMap = [];
while ($row = $groupDaysResult->fetch_assoc()) {
    $key = $row['room_id'] . '|' . $row['code'];
    $groupDaysMap[$key][] = $row['day_of_week'];
}
$groupDaysStmt->close();

$dayMap = ['Mon' => 1, 'Tue' => 2, 'Wed' => 3, 'Thu' => 4, 'Fri' => 5, 'Sat' => 6, 'Sun' => 7];
$todayCode = array_search((int) date('N'), $dayMap);
$now = date('H:i:s');

$totalClasses = count($subjectRows);
$todayCount = 0;
$totalStudentsAllTime = 0;
$attendanceRateSum = 0;
$attendanceRateCount = 0;
$uniqueRoomIds = [];

foreach ($subjectRows as &$row) {
    $groupKey = $row['room_id'] . '|' . $row['code'];
    $row['group_days'] = $groupDaysMap[$groupKey] ?? [$row['day_of_week']];

    $roster = getLiveRosterForSubject($mysqli, $row['id']);
    $row['roster'] = $roster;
    $row['enrolled'] = count($roster['rows']);

    if (!in_array((int) $row['room_id'], $uniqueRoomIds, true)) {
        $uniqueRoomIds[] = (int) $row['room_id'];
        $totalStudentsAllTime += $row['enrolled'];
    }

    if ($row['day_of_week'] === $todayCode) {
        $todayCount++;
    }

    $countStmt = $mysqli->prepare("SELECT COUNT(*) AS total, SUM(status IN ('present','late')) AS attended FROM attendance WHERE subject_id = ?");
    $countStmt->bind_param('i', $row['id']);
    $countStmt->execute();
    $counts = $countStmt->get_result()->fetch_assoc();
    $countStmt->close();
    $total = (int) $counts['total'];
    if ($total > 0) {
        $attendanceRateSum += round((((int) $counts['attended']) / $total) * 100);
        $attendanceRateCount++;
    }
}
unset($row);

$averageAttendance = $attendanceRateCount ? round($attendanceRateSum / $attendanceRateCount, 1) : 0;

// Consolidate same class+room rows (one per meeting day) into a single card,
// listing every day it meets and using today's day (or the earliest one) for
// the card's action links.
$dayOrder = ['Mon' => 1, 'Tue' => 2, 'Wed' => 3, 'Thu' => 4, 'Fri' => 5, 'Sat' => 6, 'Sun' => 7];
$cardGroups = [];
foreach ($subjectRows as $row) {
    $groupKey = $row['room_id'] . '|' . $row['code'];
    if (!isset($cardGroups[$groupKey])) {
        $cardGroups[$groupKey] = $row;
        $cardGroups[$groupKey]['days'] = [];
        $cardGroups[$groupKey]['day_ids'] = [];
    }
    $cardGroups[$groupKey]['days'][] = $row['day_of_week'];
    $cardGroups[$groupKey]['day_ids'][$row['day_of_week']] = $row['id'];
}
foreach ($cardGroups as &$group) {
    usort($group['days'], function ($a, $b) use ($dayOrder) {
        return $dayOrder[$a] <=> $dayOrder[$b];
    });
    $group['action_id'] = $group['day_ids'][$todayCode] ?? $group['day_ids'][$group['days'][0]];
    $group['join_code'] = ensureClassJoinCode($mysqli, $group['id']);
}
unset($group);

$allRoomsStmt = $mysqli->query('SELECT sec.id, sec.room_name, sec.year_level, c.code AS course_code FROM rooms sec LEFT JOIN courses c ON sec.course_id = c.id ORDER BY sec.year_level, sec.room_name');
$allRooms = $allRoomsStmt->fetch_all(MYSQLI_ASSOC);

require_once __DIR__ . '/../includes/teacher_header.php';
?>
<div class="row g-3 mb-3">
    <div class="col-md-3">
        <div class="card p-3 sp-mc-stat">
            <div class="sp-mc-stat-icon" style="background: var(--lp-pale-green); color: var(--lp-dark-green);"><i class="fa-solid fa-book"></i></div>
            <div>
                <div class="sp-mc-stat-value"><?php echo $totalClasses; ?></div>
                <div class="sp-mc-stat-label">Total Classes</div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card p-3 sp-mc-stat">
            <div class="sp-mc-stat-icon" style="background: #ece6fb; color: #6f42c1;"><i class="fa-solid fa-users"></i></div>
            <div>
                <div class="sp-mc-stat-value"><?php echo $totalStudentsAllTime; ?></div>
                <div class="sp-mc-stat-label">Total Students</div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card p-3 sp-mc-stat">
            <div class="sp-mc-stat-icon" style="background: #dbe8fb; color: #2f6fed;"><i class="fa-solid fa-chart-line"></i></div>
            <div>
                <div class="sp-mc-stat-value"><?php echo $averageAttendance; ?>%</div>
                <div class="sp-mc-stat-label">Average Attendance</div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card p-3 sp-mc-stat">
            <div class="sp-mc-stat-icon" style="background: #fdeedd; color: #e08b1d;"><i class="fa-solid fa-calendar-day"></i></div>
            <div>
                <div class="sp-mc-stat-value"><?php echo $todayCount; ?></div>
                <div class="sp-mc-stat-label">Today's Classes</div>
            </div>
        </div>
    </div>
</div>
<div class="d-flex justify-content-end mb-3">
    <button type="button" class="btn btn-primary rounded-pill" data-bs-toggle="modal" data-bs-target="#createClassModal"><i class="fa-solid fa-plus me-1"></i> Create Class</button>
</div>
<div class="card p-3 mb-3">
    <div class="sp-mc-toolbar">
        <div class="sp-mc-search">
            <i class="fa-solid fa-magnifying-glass"></i>
            <input type="text" id="mcSearchInput" placeholder="Search class by name or code...">
        </div>
        <form method="get" class="d-flex align-items-center gap-2 mb-0 flex-wrap">
            <select class="form-select form-select-sm sp-filter-select" name="course_id" onchange="this.form.submit()" style="width:auto;" title="Filter by program">
                <option value="0">All Programs</option>
                <?php foreach ($programOptions as $optId => $optLabel): ?>
                    <option value="<?php echo $optId; ?>" <?php echo $courseFilter === $optId ? 'selected' : ''; ?>><?php echo htmlspecialchars($optLabel); ?></option>
                <?php endforeach; ?>
            </select>
            <select class="form-select form-select-sm sp-filter-select" name="room_id" onchange="this.form.submit()" style="width:auto;" title="Filter by block">
                <option value="0">All Blocks</option>
                <?php foreach ($blockOptions as $optId => $optLabel): ?>
                    <option value="<?php echo $optId; ?>" <?php echo $roomFilter === $optId ? 'selected' : ''; ?>><?php echo htmlspecialchars($optLabel); ?></option>
                <?php endforeach; ?>
            </select>
            <select class="form-select form-select-sm sp-filter-select" name="status" onchange="this.form.submit()" style="width:auto;">
                <option value="active" <?php echo $statusFilter === 'active' ? 'selected' : ''; ?>>Active Classes</option>
                <option value="inactive" <?php echo $statusFilter === 'inactive' ? 'selected' : ''; ?>>Inactive Classes</option>
                <option value="all" <?php echo $statusFilter === 'all' ? 'selected' : ''; ?>>All Classes</option>
            </select>
        </form>
        <div class="sp-mc-view-toggle">
            <button type="button" class="active" id="mcGridBtn" title="Grid view"><i class="fa-solid fa-table-cells-large"></i></button>
            <button type="button" id="mcListBtn" title="List view"><i class="fa-solid fa-list"></i></button>
        </div>
    </div>
</div>

<?php if (empty($cardGroups)): ?>
    <div class="alert alert-info">No classes found for this filter.</div>
<?php else: ?>
    <div class="row g-3 sp-classes-grid" id="mcGrid">
        <?php foreach ($cardGroups as $group): $theme = subjectTheme($group['id']); ?>
            <div class="col-lg-4 col-md-6 sp-mc-col" data-search="<?php echo htmlspecialchars(strtolower($group['name'] . ' ' . $group['code'])); ?>">
                <div class="sp-subject-card">
                    <div class="sp-subject-band" style="background: <?php echo $theme['color']; ?>;">
                        <i class="fa-solid <?php echo $theme['icon']; ?> sp-subject-icon"></i>
                        <span class="sp-subject-code"><?php echo htmlspecialchars($group['code']); ?></span>
                        <span class="sp-subject-name"><?php echo htmlspecialchars($group['name']); ?></span>
                    </div>
                    <div class="sp-subject-body">
                        <div class="sp-subject-teacher">
                            <div class="sp-subject-meta">
                                <span class="sp-subject-prof"><?php echo htmlspecialchars($group['room_name']); ?></span>
                                <?php echo htmlspecialchars(implode(', ', $group['days'])); ?> | <?php echo formatTime($group['start_time']); ?><?php echo $group['end_time'] ? ' - ' . formatTime($group['end_time']) : ''; ?>
                                <br><span class="text-muted">Join Code: <strong><?php echo htmlspecialchars($group['join_code'] ?: '—'); ?></strong></span>
                            </div>
                            <div class="dropdown">
                                <button class="btn btn-sm btn-link text-secondary p-0" type="button" data-bs-toggle="dropdown" aria-expanded="false"><i class="fa-solid fa-ellipsis-vertical"></i></button>
                                <ul class="dropdown-menu dropdown-menu-end">
                                    <li><button class="dropdown-item btn-edit-schedule" type="button" data-data='<?php echo json_encode($group); ?>'>Edit Schedule</button></li>
                                    <li><button class="dropdown-item btn-edit-policy" type="button" data-data='<?php echo json_encode($group); ?>'>Edit Attendance Policy</button></li>
                                    <li><button class="dropdown-item btn-edit-subject-room" type="button" data-data='<?php echo json_encode($group); ?>'>Edit Subject Room</button></li>
                                </ul>
                            </div>
                        </div>
                        <div class="sp-subject-actions">
                            <a href="class-details.php?id=<?php echo $group['action_id']; ?>"><i class="fa-solid fa-file-lines"></i>Details</a>
                            <a href="students.php?subject_id=<?php echo $group['action_id']; ?>"><i class="fa-solid fa-users"></i>Students</a>
                            <a href="../admin/scanner.php?subject_id=<?php echo $group['action_id']; ?>"><i class="fa-solid fa-qrcode"></i>Take Attendance</a>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<div class="modal fade" id="scheduleModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-4">
            <div class="modal-header">
                <h5 class="modal-title" id="scheduleModalTitle">Edit Schedule</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="post">
                <input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>">
                <input type="hidden" name="action" value="save_schedule">
                <input type="hidden" name="id" id="scheduleIdField">
                <div class="modal-body row g-3">
                    <div class="col-12">
                        <label class="form-label">Days of Week</label>
                        <div class="d-flex flex-wrap gap-3" id="scheduleDayField">
                            <?php foreach (['Mon','Tue','Wed','Thu','Fri','Sat','Sun'] as $day): ?>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="day_of_week[]" value="<?php echo $day; ?>" id="scheduleDay<?php echo $day; ?>">
                                    <label class="form-check-label" for="scheduleDay<?php echo $day; ?>"><?php echo $day; ?></label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Start Time</label>
                        <input type="time" class="form-control" name="start_time" id="scheduleStartTimeField" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">End Time</label>
                        <input type="time" class="form-control" name="end_time" id="scheduleEndTimeField">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Schedule</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="policyModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-4">
            <div class="modal-header">
                <h5 class="modal-title" id="policyModalTitle">Edit Attendance Policy</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="post">
                <input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>">
                <input type="hidden" name="action" value="save_policy">
                <input type="hidden" name="id" id="policyIdField">
                <div class="modal-body">
                    <p class="text-muted small">A student is marked <strong>Late</strong> if they scan after the start time but within this many minutes, and <strong>Absent</strong> if they scan later than that (or never scan).</p>
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" name="use_default" id="policyUseDefaultField">
                        <label class="form-check-label" for="policyUseDefaultField">Use school default (<?php echo intval(getSetting('absent_cutoff_minutes', 20)); ?> minutes)</label>
                    </div>
                    <label class="form-label">Absent after (minutes late)</label>
                    <input type="number" class="form-control" name="absent_cutoff_minutes" id="policyCutoffField" min="1" max="180" required>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Policy</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="subjectRoomModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-4">
            <div class="modal-header">
                <h5 class="modal-title" id="subjectRoomModalTitle">Edit Subject Room</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="post">
                <input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>">
                <input type="hidden" name="action" value="save_subject_room">
                <input type="hidden" name="id" id="subjectRoomIdField">
                <div class="modal-body">
                    <p class="text-muted small">This also updates where students see this class's room.</p>
                    <label class="form-label">Subject Room</label>
                    <input type="text" class="form-control" name="subject_room" id="subjectRoomField" placeholder="e.g. IT Lab 5">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Subject Room</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="createClassModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-4">
            <div class="modal-header">
                <h5 class="modal-title">Create Class</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="post">
                <input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>">
                <input type="hidden" name="action" value="create_class">
                <div class="modal-body row g-3">
                    <p class="text-muted small mb-0">Create a class for a room you're teaching. A join code will be generated so you can share it with your students.</p>
                    <div class="col-md-6">
                        <label class="form-label">Class Code</label>
                        <input type="text" class="form-control" name="code" placeholder="e.g. IT 205" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Class Name</label>
                        <input type="text" class="form-control" name="name" placeholder="e.g. Web Development" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Room</label>
                        <select class="form-select" name="room_id" required>
                            <option value="">Select a room</option>
                            <?php foreach ($allRooms as $room): ?>
                                <option value="<?php echo $room['id']; ?>"><?php echo htmlspecialchars(($room['course_code'] ? $room['course_code'] . ' - ' : '') . $room['year_level'] . ' - ' . $room['room_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Subject Room</label>
                        <input type="text" class="form-control" name="subject_room" placeholder="e.g. IT Lab 5">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Days of Week</label>
                        <div class="d-flex flex-wrap gap-3">
                            <?php foreach (['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'] as $day): ?>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="day_of_week[]" value="<?php echo $day; ?>" id="createClassDay<?php echo $day; ?>">
                                    <label class="form-check-label" for="createClassDay<?php echo $day; ?>"><?php echo $day; ?></label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Start Time</label>
                        <input type="time" class="form-control" name="start_time" value="07:30" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">End Time</label>
                        <input type="time" class="form-control" name="end_time">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Credit Units</label>
                        <input type="number" min="0" class="form-control" name="credit_units">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Important Note</label>
                        <textarea class="form-control" name="important_note" rows="2" placeholder="Shown to students on the class page"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Create Class</button>
                </div>
            </form>
        </div>
    </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const scheduleModal = new bootstrap.Modal(document.getElementById('scheduleModal'));
    document.querySelectorAll('.btn-edit-schedule').forEach(btn => {
        btn.addEventListener('click', () => {
            const data = JSON.parse(btn.getAttribute('data-data'));
            document.getElementById('scheduleModalTitle').textContent = 'Edit Schedule — ' + data.name;
            document.getElementById('scheduleIdField').value = data.id;
            const selectedDays = data.group_days || [data.day_of_week];
            document.querySelectorAll('#scheduleDayField input[type="checkbox"]').forEach(cb => {
                cb.checked = selectedDays.includes(cb.value);
            });
            document.getElementById('scheduleStartTimeField').value = data.start_time;
            document.getElementById('scheduleEndTimeField').value = data.end_time || '';
            scheduleModal.show();
        });
    });

    const policyModal = new bootstrap.Modal(document.getElementById('policyModal'));
    const policyUseDefaultField = document.getElementById('policyUseDefaultField');
    const policyCutoffField = document.getElementById('policyCutoffField');
    policyUseDefaultField.addEventListener('change', function () {
        policyCutoffField.disabled = this.checked;
    });
    document.querySelectorAll('.btn-edit-policy').forEach(btn => {
        btn.addEventListener('click', () => {
            const data = JSON.parse(btn.getAttribute('data-data'));
            document.getElementById('policyModalTitle').textContent = 'Edit Attendance Policy — ' + data.name;
            document.getElementById('policyIdField').value = data.id;
            const hasCustom = data.absent_cutoff_minutes !== null && data.absent_cutoff_minutes !== undefined;
            policyUseDefaultField.checked = !hasCustom;
            policyCutoffField.value = hasCustom ? data.absent_cutoff_minutes : 20;
            policyCutoffField.disabled = !hasCustom;
            policyModal.show();
        });
    });

    const subjectRoomModal = new bootstrap.Modal(document.getElementById('subjectRoomModal'));
    document.querySelectorAll('.btn-edit-subject-room').forEach(btn => {
        btn.addEventListener('click', () => {
            const data = JSON.parse(btn.getAttribute('data-data'));
            document.getElementById('subjectRoomModalTitle').textContent = 'Edit Subject Room — ' + data.name;
            document.getElementById('subjectRoomIdField').value = data.id;
            document.getElementById('subjectRoomField').value = data.subject_room || '';
            subjectRoomModal.show();
        });
    });

    const mcSearchInput = document.getElementById('mcSearchInput');
    if (mcSearchInput) {
        mcSearchInput.addEventListener('input', function () {
            const term = this.value.trim().toLowerCase();
            document.querySelectorAll('.sp-mc-col').forEach(function (col) {
                col.style.display = col.getAttribute('data-search').includes(term) ? '' : 'none';
            });
        });
    }

    const mcGrid = document.getElementById('mcGrid');
    const mcGridBtn = document.getElementById('mcGridBtn');
    const mcListBtn = document.getElementById('mcListBtn');
    if (mcGrid && mcGridBtn && mcListBtn) {
        mcGridBtn.addEventListener('click', function () {
            mcGrid.classList.remove('sp-mc-list-view');
            mcGridBtn.classList.add('active');
            mcListBtn.classList.remove('active');
        });
        mcListBtn.addEventListener('click', function () {
            mcGrid.classList.add('sp-mc-list-view');
            mcListBtn.classList.add('active');
            mcGridBtn.classList.remove('active');
        });
    }
});
</script>
<?php require_once __DIR__ . '/../includes/teacher_footer.php'; ?>
