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

        $refStmt = $mysqli->prepare('SELECT code, name, section_id, room, credit_units, status FROM subjects WHERE id = ? AND teacher_id = ? LIMIT 1');
        $refStmt->bind_param('ii', $id, $teacherId);
        $refStmt->execute();
        $ref = $refStmt->get_result()->fetch_assoc();
        $refStmt->close();

        if (!$ref) {
            flash('Class not found.', 'danger');
            redirect('subjects.php');
        }

        $groupStmt = $mysqli->prepare('SELECT id, day_of_week FROM subjects WHERE teacher_id = ? AND section_id = ? AND code = ?');
        $groupStmt->bind_param('iis', $teacherId, $ref['section_id'], $ref['code']);
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
                $ins = $mysqli->prepare('INSERT INTO subjects (code, name, teacher_id, section_id, day_of_week, start_time, end_time, room, credit_units, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
                $ins->bind_param('ssiissssis', $ref['code'], $ref['name'], $teacherId, $ref['section_id'], $day, $startTime, $endTime, $ref['room'], $ref['credit_units'], $ref['status']);
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
}

$statusFilter = sanitize($_GET['status'] ?? 'active');
$courseFilter = intval($_GET['course_id'] ?? 0);
$sectionFilter = intval($_GET['section_id'] ?? 0);

// Program (course) and Block (section) options, scoped to this teacher's own classes only.
$filterOptionsStmt = $mysqli->prepare('SELECT DISTINCT c.id AS course_id, c.code AS course_code, c.name AS course_name, sec.id AS section_id, sec.section_name, sec.year_level
    FROM subjects sub
    JOIN sections sec ON sub.section_id = sec.id
    JOIN courses c ON sec.course_id = c.id
    WHERE sub.teacher_id = ?
    ORDER BY c.code, sec.year_level, sec.section_name');
$filterOptionsStmt->bind_param('i', $teacherId);
$filterOptionsStmt->execute();
$filterOptionsResult = $filterOptionsStmt->get_result();
$programOptions = [];
$blockOptions = [];
while ($opt = $filterOptionsResult->fetch_assoc()) {
    $programOptions[(int) $opt['course_id']] = $opt['course_code'] . ' - ' . $opt['course_name'];
    $blockOptions[(int) $opt['section_id']] = $opt['year_level'] . ' - ' . $opt['section_name'];
}
$filterOptionsStmt->close();

$query = 'SELECT sub.*, sec.section_name, sec.year_level, c.id AS course_id, c.code AS course_code
    FROM subjects sub
    JOIN sections sec ON sub.section_id = sec.id
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
if ($sectionFilter) {
    $query .= ' AND sec.id = ?';
    $types .= 'i';
    $params[] = $sectionFilter;
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

// Map of "sectionId|code" => [days already scheduled], so the Edit Schedule
// modal can pre-check every day this class meets on, not just this one row.
$groupDaysStmt = $mysqli->prepare('SELECT section_id, code, day_of_week FROM subjects WHERE teacher_id = ?');
$groupDaysStmt->bind_param('i', $teacherId);
$groupDaysStmt->execute();
$groupDaysResult = $groupDaysStmt->get_result();
$groupDaysMap = [];
while ($row = $groupDaysResult->fetch_assoc()) {
    $key = $row['section_id'] . '|' . $row['code'];
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
$uniqueSectionIds = [];

foreach ($subjectRows as &$row) {
    $groupKey = $row['section_id'] . '|' . $row['code'];
    $row['group_days'] = $groupDaysMap[$groupKey] ?? [$row['day_of_week']];

    $roster = getLiveRosterForSubject($mysqli, $row['id']);
    $row['roster'] = $roster;
    $row['enrolled'] = count($roster['rows']);

    if (!in_array((int) $row['section_id'], $uniqueSectionIds, true)) {
        $uniqueSectionIds[] = (int) $row['section_id'];
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

// Consolidate same class+section rows (one per meeting day) into a single card,
// listing every day it meets and using today's day (or the earliest one) for
// the card's action links.
$dayOrder = ['Mon' => 1, 'Tue' => 2, 'Wed' => 3, 'Thu' => 4, 'Fri' => 5, 'Sat' => 6, 'Sun' => 7];
$cardGroups = [];
foreach ($subjectRows as $row) {
    $groupKey = $row['section_id'] . '|' . $row['code'];
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
}
unset($group);

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
            <select class="form-select form-select-sm sp-filter-select" name="section_id" onchange="this.form.submit()" style="width:auto;" title="Filter by block">
                <option value="0">All Blocks</option>
                <?php foreach ($blockOptions as $optId => $optLabel): ?>
                    <option value="<?php echo $optId; ?>" <?php echo $sectionFilter === $optId ? 'selected' : ''; ?>><?php echo htmlspecialchars($optLabel); ?></option>
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
                                <span class="sp-subject-prof"><?php echo htmlspecialchars($group['section_name']); ?></span>
                                <?php echo htmlspecialchars(implode(', ', $group['days'])); ?> | <?php echo formatTime($group['start_time']); ?><?php echo $group['end_time'] ? ' - ' . formatTime($group['end_time']) : ''; ?>
                            </div>
                            <div class="dropdown">
                                <button class="btn btn-sm btn-link text-secondary p-0" type="button" data-bs-toggle="dropdown" aria-expanded="false"><i class="fa-solid fa-ellipsis-vertical"></i></button>
                                <ul class="dropdown-menu dropdown-menu-end">
                                    <li><button class="dropdown-item btn-edit-schedule" type="button" data-data='<?php echo json_encode($group); ?>'>Edit Schedule</button></li>
                                </ul>
                            </div>
                        </div>
                        <div class="sp-subject-actions">
                            <a href="class-details.php?id=<?php echo $group['action_id']; ?>"><i class="fa-solid fa-file-lines"></i>Details</a>
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
