<?php
require_once __DIR__ . '/../includes/functions.php';
requireRole(['teacher']);
$pageTitle = 'Teacher Dashboard';

$teacherId = currentTeacherId();
if ($teacherId === false) {
    flash('Your teacher profile is not set up. Contact an administrator.', 'danger');
    redirect('../dashboard.php');
}

$mySubjectsCount = 0;
$stmt = $mysqli->prepare('SELECT COUNT(*) FROM subjects WHERE teacher_id = ?');
$stmt->bind_param('i', $teacherId);
$stmt->execute();
$stmt->bind_result($mySubjectsCount);
$stmt->fetch();
$stmt->close();

$today = date('D');
$dayMap = ['Mon' => 'Mon', 'Tue' => 'Tue', 'Wed' => 'Wed', 'Thu' => 'Thu', 'Fri' => 'Fri', 'Sat' => 'Sat', 'Sun' => 'Sun'];
$todayCode = $dayMap[$today] ?? 'Mon';

$stmt = $mysqli->prepare("SELECT sub.*, sec.section_name FROM subjects sub JOIN sections sec ON sub.section_id = sec.id WHERE sub.teacher_id = ? AND sub.day_of_week = ? AND sub.status = 'active' ORDER BY sub.start_time");
$stmt->bind_param('is', $teacherId, $todayCode);
$stmt->execute();
$todaySubjects = $stmt->get_result();

$totalCounts = ['present' => 0, 'late' => 0, 'absent' => 0, 'pending' => 0];
$todaySubjectRows = [];
while ($row = $todaySubjects->fetch_assoc()) {
    $roster = getLiveRosterForSubject($mysqli, $row['id']);
    foreach ($totalCounts as $key => $value) {
        $totalCounts[$key] += $roster['counts'][$key];
    }
    $row['counts'] = $roster['counts'];
    $todaySubjectRows[] = $row;
}
$stmt->close();

require_once __DIR__ . '/../includes/header.php';
?>
<div class="container-fluid py-4">
    <div class="row g-3">
        <div class="col-md-3">
            <div class="card shadow-sm rounded-4 p-3">
                <h6>My Subjects</h6>
                <h2><?php echo $mySubjectsCount; ?></h2>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card shadow-sm rounded-4 p-3">
                <h6>Present Today</h6>
                <h2><?php echo $totalCounts['present']; ?></h2>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card shadow-sm rounded-4 p-3">
                <h6>Late Today</h6>
                <h2><?php echo $totalCounts['late']; ?></h2>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card shadow-sm rounded-4 p-3">
                <h6>Absent Today</h6>
                <h2><?php echo $totalCounts['absent']; ?></h2>
            </div>
        </div>
    </div>
    <div class="row g-3 mt-3">
        <div class="col-12">
            <div class="card rounded-4 shadow-sm p-3">
                <h5>Quick Actions</h5>
                <div class="d-flex flex-wrap gap-2">
                    <a href="live.php" class="btn btn-primary">Live Session</a>
                    <a href="subjects.php" class="btn btn-outline-primary">My Subjects</a>
                    <a href="students.php" class="btn btn-outline-primary">My Students</a>
                    <a href="../admin/scanner.php" class="btn btn-outline-primary">QR Scanner</a>
                </div>
            </div>
        </div>
    </div>
    <div class="row g-3 mt-3">
        <div class="col-12">
            <div class="card rounded-4 shadow-sm p-3">
                <h5>Today's Sessions (<?php echo $todayCode; ?>)</h5>
                <?php if (empty($todaySubjectRows)): ?>
                    <p class="text-muted mb-0">No sessions scheduled today.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-striped align-middle">
                            <thead class="table-light">
                                <tr><th>Subject</th><th>Section</th><th>Start Time</th><th>Present</th><th>Late</th><th>Absent</th><th>Pending</th><th></th></tr>
                            </thead>
                            <tbody>
                                <?php foreach ($todaySubjectRows as $row): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($row['name']); ?></td>
                                        <td><?php echo htmlspecialchars($row['section_name']); ?></td>
                                        <td><?php echo formatTime($row['start_time']); ?></td>
                                        <td><?php echo $row['counts']['present']; ?></td>
                                        <td><?php echo $row['counts']['late']; ?></td>
                                        <td><?php echo $row['counts']['absent']; ?></td>
                                        <td><?php echo $row['counts']['pending']; ?></td>
                                        <td><a href="live.php?subject_id=<?php echo $row['id']; ?>" class="btn btn-sm btn-outline-primary">View</a></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
