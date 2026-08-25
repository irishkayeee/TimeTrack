<?php
require_once __DIR__ . '/../includes/functions.php';
requireRole(['admin', 'superadmin']);
$pageTitle = 'Dashboard';
$pageSubtitle = 'Overview of attendance across the school.';

$hour = (int) date('G');
$greeting = $hour < 12 ? 'Good morning' : ($hour < 18 ? 'Good afternoon' : 'Good evening');
$semester = getSetting('semester', '1st Semester');
$schoolYear = getSetting('school_year', date('Y') . '-' . (date('Y') + 1));

$studentsCount = $mysqli->query('SELECT COUNT(*) FROM students')->fetch_row()[0];
$teachersCount = $mysqli->query('SELECT COUNT(*) FROM teachers')->fetch_row()[0];
$coursesCount = $mysqli->query('SELECT COUNT(*) FROM courses')->fetch_row()[0];
$sectionsCount = $mysqli->query('SELECT COUNT(*) FROM sections')->fetch_row()[0];
$attendanceToday = $mysqli->query("SELECT COUNT(*) FROM attendance WHERE date = CURDATE()")->fetch_row()[0];

$studentsList = $mysqli->query('SELECT s.student_id, s.first_name, s.last_name, c.code AS course_code, sec.section_name FROM students s LEFT JOIN courses c ON s.course_id = c.id LEFT JOIN sections sec ON s.section_id = sec.id ORDER BY s.first_name');
$teachersList = $mysqli->query('SELECT teacher_id, first_name, last_name, subject FROM teachers ORDER BY first_name');
$coursesListDash = $mysqli->query('SELECT code, name FROM courses ORDER BY code');
$sectionsListDash = $mysqli->query('SELECT year_level, section_name FROM sections ORDER BY year_level, section_name');
$attendanceTodayList = $mysqli->query("SELECT a.status, a.time, s.first_name, s.last_name FROM attendance a LEFT JOIN students s ON a.student_id = s.id WHERE a.date = CURDATE() ORDER BY a.time DESC");

require_once __DIR__ . '/../includes/admin_header.php';
?>
<div class="card p-4 mb-3 sp-greeting-card">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
        <div>
            <h4 class="mb-1"><span id="spGreetingWord"><?php echo htmlspecialchars($greeting); ?></span>, <?php echo htmlspecialchars($adminUser['role'] ?? 'admin'); ?>! 👋</h4>
            <p class="text-muted mb-2">Here's what's happening across the school today.</p>
            <span class="sp-shd-pill"><i class="fa-solid fa-calendar"></i> <?php echo htmlspecialchars($semester . ', AY ' . $schoolYear); ?></span>
        </div>
        <div class="sp-clock-widget text-center">
            <div class="sp-clock-time" id="spClockTime">--:--:-- --</div>
            <div class="sp-clock-date" id="spClockDate">Loading...</div>
        </div>
    </div>
</div>
<script>
    function spUpdateClock() {
        var now = new Date();
        var timeEl = document.getElementById('spClockTime');
        var dateEl = document.getElementById('spClockDate');
        if (timeEl) {
            timeEl.textContent = now.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true });
        }
        if (dateEl) {
            dateEl.textContent = now.toLocaleDateString('en-US', { weekday: 'long', month: 'long', day: 'numeric', year: 'numeric' });
        }
        var greetingEl = document.getElementById('spGreetingWord');
        if (greetingEl) {
            var h = now.getHours();
            greetingEl.textContent = h < 12 ? 'Good morning' : (h < 18 ? 'Good afternoon' : 'Good evening');
        }
    }
    spUpdateClock();
    setInterval(spUpdateClock, 1000);
</script>
<div class="row g-3">
    <div class="col-6 col-lg">
        <div class="card p-3 text-center stat-card-clickable" data-bs-toggle="modal" data-bs-target="#studentsListModal">
            <div class="d-flex align-items-center justify-content-center gap-2 mb-2">
                <span class="stat-icon"><i class="fa-solid fa-user-graduate"></i></span>
                <h6 class="text-muted small text-uppercase mb-0">Students</h6>
            </div>
            <h2 class="mb-0"><?php echo $studentsCount; ?></h2>
        </div>
    </div>
    <div class="col-6 col-lg">
        <div class="card p-3 text-center stat-card-clickable" data-bs-toggle="modal" data-bs-target="#teachersListModal">
            <div class="d-flex align-items-center justify-content-center gap-2 mb-2">
                <span class="stat-icon"><i class="fa-solid fa-chalkboard-user"></i></span>
                <h6 class="text-muted small text-uppercase mb-0">Teachers</h6>
            </div>
            <h2 class="mb-0"><?php echo $teachersCount; ?></h2>
        </div>
    </div>
    <div class="col-6 col-lg">
        <div class="card p-3 text-center stat-card-clickable" data-bs-toggle="modal" data-bs-target="#coursesListModal">
            <div class="d-flex align-items-center justify-content-center gap-2 mb-2">
                <span class="stat-icon"><i class="fa-solid fa-graduation-cap"></i></span>
                <h6 class="text-muted small text-uppercase mb-0">Courses</h6>
            </div>
            <h2 class="mb-0"><?php echo $coursesCount; ?></h2>
        </div>
    </div>
    <div class="col-6 col-lg">
        <div class="card p-3 text-center stat-card-clickable" data-bs-toggle="modal" data-bs-target="#sectionsListModal">
            <div class="d-flex align-items-center justify-content-center gap-2 mb-2">
                <span class="stat-icon"><i class="fa-solid fa-people-group"></i></span>
                <h6 class="text-muted small text-uppercase mb-0">Sections</h6>
            </div>
            <h2 class="mb-0"><?php echo $sectionsCount; ?></h2>
        </div>
    </div>
    <div class="col-6 col-lg">
        <div class="card p-3 text-center stat-card-clickable" data-bs-toggle="modal" data-bs-target="#attendanceTodayListModal">
            <div class="d-flex align-items-center justify-content-center gap-2 mb-2">
                <span class="stat-icon"><i class="fa-solid fa-clipboard-check"></i></span>
                <h6 class="text-muted small text-uppercase mb-0">Attendance Today</h6>
            </div>
            <h2 class="mb-0"><?php echo $attendanceToday; ?></h2>
        </div>
    </div>
</div>

<div class="modal fade" id="studentsListModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content rounded-4">
            <div class="modal-header">
                <h5 class="modal-title">Students (<?php echo $studentsCount; ?>)</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <ul class="list-group list-group-flush">
                    <?php if ($studentsList->num_rows === 0): ?>
                        <li class="list-group-item text-muted small">No students yet.</li>
                    <?php else: ?>
                        <?php while ($row = $studentsList->fetch_assoc()): ?>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <span><?php echo htmlspecialchars($row['first_name'] . ' ' . $row['last_name']); ?></span>
                                <span class="text-muted small"><?php echo htmlspecialchars(trim(($row['course_code'] ?: '') . ' ' . ($row['section_name'] ?: ''))); ?></span>
                            </li>
                        <?php endwhile; ?>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="teachersListModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content rounded-4">
            <div class="modal-header">
                <h5 class="modal-title">Teachers (<?php echo $teachersCount; ?>)</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <ul class="list-group list-group-flush">
                    <?php if ($teachersList->num_rows === 0): ?>
                        <li class="list-group-item text-muted small">No teachers yet.</li>
                    <?php else: ?>
                        <?php while ($row = $teachersList->fetch_assoc()): ?>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <span><?php echo htmlspecialchars($row['first_name'] . ' ' . $row['last_name']); ?></span>
                                <span class="text-muted small"><?php echo htmlspecialchars($row['subject']); ?></span>
                            </li>
                        <?php endwhile; ?>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="coursesListModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content rounded-4">
            <div class="modal-header">
                <h5 class="modal-title">Courses (<?php echo $coursesCount; ?>)</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <ul class="list-group list-group-flush">
                    <?php if ($coursesListDash->num_rows === 0): ?>
                        <li class="list-group-item text-muted small">No courses yet.</li>
                    <?php else: ?>
                        <?php while ($row = $coursesListDash->fetch_assoc()): ?>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <span><?php echo htmlspecialchars($row['name']); ?></span>
                                <span class="text-muted small"><?php echo htmlspecialchars($row['code']); ?></span>
                            </li>
                        <?php endwhile; ?>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="sectionsListModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content rounded-4">
            <div class="modal-header">
                <h5 class="modal-title">Sections (<?php echo $sectionsCount; ?>)</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <ul class="list-group list-group-flush">
                    <?php if ($sectionsListDash->num_rows === 0): ?>
                        <li class="list-group-item text-muted small">No sections yet.</li>
                    <?php else: ?>
                        <?php while ($row = $sectionsListDash->fetch_assoc()): ?>
                            <li class="list-group-item"><?php echo htmlspecialchars($row['year_level'] . ' - ' . $row['section_name']); ?></li>
                        <?php endwhile; ?>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="attendanceTodayListModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content rounded-4">
            <div class="modal-header">
                <h5 class="modal-title">Attendance Today (<?php echo $attendanceToday; ?>)</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <ul class="list-group list-group-flush">
                    <?php if ($attendanceTodayList->num_rows === 0): ?>
                        <li class="list-group-item text-muted small">No attendance recorded yet today.</li>
                    <?php else: ?>
                        <?php while ($row = $attendanceTodayList->fetch_assoc()): ?>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <span><?php echo htmlspecialchars($row['first_name'] . ' ' . $row['last_name']); ?></span>
                                <span class="d-flex align-items-center gap-2">
                                    <?php echo badgeStatus($row['status']); ?>
                                    <span class="text-muted small"><?php echo formatTime($row['time']); ?></span>
                                </span>
                            </li>
                        <?php endwhile; ?>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>
