<?php
require_once __DIR__ . '/../includes/functions.php';
requireRole(['teacher']);
$pageTitle = 'Live Session';
$pageSubtitle = 'Real-time attendance for an ongoing class.';

$teacherId = currentTeacherId();
if ($teacherId === false) {
    flash('Your teacher profile is not set up. Contact an administrator.', 'danger');
    redirect('../dashboard.php');
}

$stmt = $mysqli->prepare("SELECT sub.*, sec.room_name FROM subjects sub JOIN rooms sec ON sub.room_id = sec.id WHERE sub.teacher_id = ? AND sub.status = 'active' ORDER BY sub.name");
$stmt->bind_param('i', $teacherId);
$stmt->execute();
$subjects = $stmt->get_result();

$subjectId = intval($_GET['subject_id'] ?? 0);
$roster = null;
if ($subjectId) {
    $verify = $mysqli->prepare('SELECT id FROM subjects WHERE id = ? AND teacher_id = ?');
    $verify->bind_param('ii', $subjectId, $teacherId);
    $verify->execute();
    $verify->store_result();
    if ($verify->num_rows > 0) {
        $roster = getLiveRosterForSubject($mysqli, $subjectId);
    }
    $verify->close();
}

require_once __DIR__ . '/../includes/teacher_header.php';
?>
<div class="card p-4">
    <?php if (!$roster): ?>
        <a href="subjects.php" class="sp-back-link d-inline-flex mb-3"><i class="fa-solid fa-arrow-left"></i> Back to My Classes</a>
        <p class="text-muted">Select a subject to view its live attendance.</p>
        <div class="row g-3">
            <?php if ($subjects->num_rows === 0): ?>
                <div class="col-12"><div class="alert alert-info">You have no active subjects assigned yet.</div></div>
            <?php endif; ?>
            <?php while ($row = $subjects->fetch_assoc()): ?>
                <div class="col-md-4">
                    <a href="live.php?subject_id=<?php echo $row['id']; ?>" class="text-decoration-none">
                        <div class="card rounded-4 p-3 h-100 border">
                            <h6 class="mb-1"><?php echo htmlspecialchars($row['name']); ?> (<?php echo htmlspecialchars($row['code']); ?>)</h6>
                            <p class="text-muted mb-0"><?php echo htmlspecialchars($row['room_name']); ?> &middot; <?php echo htmlspecialchars($row['day_of_week']); ?> <?php echo formatTime($row['start_time']); ?></p>
                        </div>
                    </a>
                </div>
            <?php endwhile; ?>
        </div>
    <?php else: ?>
        <div class="d-flex justify-content-between align-items-center mb-3">
            <a href="subjects.php" class="sp-back-link"><i class="fa-solid fa-arrow-left"></i> Back to My Classes</a>
            <a href="live.php" class="btn btn-outline-secondary rounded-pill px-4">Change Subject</a>
        </div>
        <div class="row g-3 mb-4">
            <div class="col-md-3">
                <div class="card rounded-4 p-3 bg-success bg-opacity-75 text-white">
                    <h6>Present</h6>
                    <h2><?php echo $roster['counts']['present']; ?></h2>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card rounded-4 p-3 bg-warning bg-opacity-75 text-white">
                    <h6>Late</h6>
                    <h2><?php echo $roster['counts']['late']; ?></h2>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card rounded-4 p-3 bg-danger bg-opacity-75 text-white">
                    <h6>Absent</h6>
                    <h2><?php echo $roster['counts']['absent']; ?></h2>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card rounded-4 p-3 bg-secondary bg-opacity-75 text-white">
                    <h6>Pending</h6>
                    <h2><?php echo $roster['counts']['pending']; ?></h2>
                </div>
            </div>
        </div>
        <div class="table-responsive">
            <table class="table table-striped align-middle">
                <thead class="table-light">
                    <tr><th>Photo</th><th>ID</th><th>Name</th><th>Status</th><th>Scan Time</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($roster['rows'] as $row): ?>
                        <tr>
                            <td>
                                <?php if ($row['photo']): ?>
                                    <img src="../<?php echo htmlspecialchars($row['photo']); ?>" class="rounded-circle" style="width:40px;height:40px;object-fit:cover;">
                                <?php else: ?>
                                    <i class="fa-solid fa-circle-user fa-2x text-secondary"></i>
                                <?php endif; ?>
                            </td>
                            <td><?php echo htmlspecialchars($row['student_code']); ?></td>
                            <td><?php echo htmlspecialchars($row['first_name'] . ' ' . $row['last_name']); ?></td>
                            <td><?php echo badgeStatus($row['display_status']); ?></td>
                            <td><?php echo $row['scan_time'] ? formatTime($row['scan_time']) : '—'; ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <script>
            setTimeout(() => location.reload(), 30000);
        </script>
    <?php endif; ?>
</div>
<?php require_once __DIR__ . '/../includes/teacher_footer.php'; ?>
