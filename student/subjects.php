<?php
require_once __DIR__ . '/../includes/functions.php';
requireRole(['student']);
$pageTitle = 'Subjects';
$pageSubtitle = 'View all your subjects and access attendance through QR code.';

$studentDbId = currentStudentId();
if ($studentDbId === false) {
    flash('Your student profile is not set up. Contact an administrator.', 'danger');
    redirect('../dashboard.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'join_class') {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        flash('Invalid request.', 'danger');
        redirect('subjects.php');
    }

    $classCode = strtoupper(trim(sanitize($_POST['class_code'] ?? '')));
    if ($classCode === '') {
        flash('Please enter a class code.', 'danger');
        redirect('subjects.php');
    }

    $sectionStmt = $mysqli->prepare('SELECT id, section_name, year_level FROM sections WHERE join_code = ? LIMIT 1');
    $sectionStmt->bind_param('s', $classCode);
    $sectionStmt->execute();
    $targetSection = $sectionStmt->get_result()->fetch_assoc();
    $sectionStmt->close();

    if (!$targetSection) {
        flash('That class code doesn\'t match any class. Double-check it with your teacher.', 'danger');
        redirect('subjects.php');
    }

    $currentSectionStmt = $mysqli->prepare('SELECT section_id FROM students WHERE id = ?');
    $currentSectionStmt->bind_param('i', $studentDbId);
    $currentSectionStmt->execute();
    $currentSectionStmt->bind_result($currentSectionId);
    $currentSectionStmt->fetch();
    $currentSectionStmt->close();

    if ((int) $currentSectionId === (int) $targetSection['id']) {
        flash('You\'re already in ' . $targetSection['year_level'] . ' - ' . $targetSection['section_name'] . '.', 'info');
        redirect('subjects.php');
    }

    $updateStmt = $mysqli->prepare('UPDATE students SET section_id = ? WHERE id = ?');
    $updateStmt->bind_param('ii', $targetSection['id'], $studentDbId);
    $updateStmt->execute();
    $updateStmt->close();

    flash('Joined ' . $targetSection['year_level'] . ' - ' . $targetSection['section_name'] . '. Your class list now reflects this section.', 'success');
    redirect('subjects.php');
}

$me = currentUser();
$stmt = $mysqli->prepare('SELECT first_name, last_name, section_id, student_id, qr_code FROM students WHERE id = ?');
$stmt->bind_param('i', $studentDbId);
$stmt->execute();
$stmt->bind_result($myFirstName, $myLastName, $sectionId, $myStudentCode, $myQrCode);
$stmt->fetch();
$stmt->close();
$myQrToken = $myQrCode ?: $myStudentCode;

$activeSubjects = [];
$inactiveSubjects = [];
if ($sectionId) {
    $stmt = $mysqli->prepare("SELECT sub.*, CONCAT(t.first_name, ' ', t.last_name) AS teacher_name FROM subjects sub LEFT JOIN teachers t ON sub.teacher_id = t.id WHERE sub.section_id = ? ORDER BY sub.name");
    $stmt->bind_param('i', $sectionId);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        if ($row['status'] === 'active') {
            $activeSubjects[] = $row;
        } else {
            $inactiveSubjects[] = $row;
        }
    }
    $stmt->close();
}

function renderSubjectCard($row, $qrToken) {
    $theme = subjectTheme($row['id']);

    $qrText = subjectQrText($qrToken, $row['id']);
    $qrFilename = 'qr_' . preg_replace('/[^A-Za-z0-9_-]/', '_', $qrToken) . '_subj' . $row['id'] . '.png';
    $qrDirectory = __DIR__ . '/../qrcodes/';
    $qrFilePath = $qrDirectory . $qrFilename;
    $qrImageExists = false;
    if (ensureQrDirectory($qrDirectory)) {
        if (!file_exists($qrFilePath) || !isValidPngFile($qrFilePath)) {
            generateStudentQrFile($qrText, $qrFilePath);
        }
        $qrImageExists = file_exists($qrFilePath) && isValidPngFile($qrFilePath);
    }
    ?>
    <div class="col-md-4">
        <div class="sp-subject-card">
            <div class="sp-subject-band" style="background: <?php echo $theme['color']; ?>;">
                <i class="fa-solid <?php echo $theme['icon']; ?> sp-subject-icon"></i>
                <span class="sp-subject-code"><?php echo htmlspecialchars($row['code']); ?></span>
                <span class="sp-subject-name"><?php echo htmlspecialchars($row['name']); ?></span>
            </div>
            <div class="sp-subject-body">
                <div class="sp-subject-teacher">
                    <div class="sp-subject-meta">
                        <span class="sp-subject-prof"><?php echo htmlspecialchars($row['teacher_name'] ? 'Prof. ' . $row['teacher_name'] : 'Unassigned'); ?></span>
                        <?php echo htmlspecialchars($row['day_of_week']); ?> | <?php echo formatTime($row['start_time']); ?>
                    </div>
                    <i class="fa-solid fa-circle-user sp-subject-avatar fa-2x text-secondary"></i>
                </div>
                <div class="sp-subject-actions">
                    <a href="subject-details.php?id=<?php echo $row['id']; ?>"><i class="fa-solid fa-file-lines"></i>Details</a>
                    <a href="#" data-bs-toggle="modal" data-bs-target="#qrModal<?php echo $row['id']; ?>"><i class="fa-solid fa-qrcode"></i>QR Code</a>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="qrModal<?php echo $row['id']; ?>" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered sp-qr-modal-dialog">
            <div class="modal-content rounded-4 sp-qr-modal">
                <button type="button" class="btn-close sp-qr-close" data-bs-dismiss="modal" aria-label="Close"></button>
                <div class="modal-body text-center py-5">
                    <div id="qrCard<?php echo $row['id']; ?>" class="sp-qr-card">
                        <span class="sp-shd-pill"><i class="fa-solid fa-book-open"></i> SUBJECT</span>
                        <h2 class="sp-qr-code mt-3 mb-0"><?php echo htmlspecialchars($row['code']); ?></h2>
                        <p class="text-muted"><?php echo htmlspecialchars($row['name']); ?></p>
                        <hr class="sp-qr-divider">
                        <?php if ($qrImageExists): ?>
                            <div class="sp-qr-frame">
                                <img src="../qrcodes/<?php echo urlencode($qrFilename); ?>" alt="QR code for <?php echo htmlspecialchars($row['code']); ?>" class="img-fluid">
                            </div>
                        <?php endif; ?>
                    </div>
                    <?php if ($qrImageExists): ?>
                        <button type="button" class="btn btn-outline-success rounded-pill w-100 mt-3 sp-qr-save-btn" data-target="qrCard<?php echo $row['id']; ?>" data-filename="qr_<?php echo htmlspecialchars($row['code']); ?>.png"><i class="fa-solid fa-download me-1"></i> Save QR Code</button>
                    <?php else: ?>
                        <div class="alert alert-warning mb-0">QR code unavailable. Contact an administrator.</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <?php
}

require_once __DIR__ . '/../includes/student_header.php';
?>
<div class="d-flex justify-content-end mb-3">
    <button type="button" class="btn btn-primary rounded-pill" data-bs-toggle="modal" data-bs-target="#joinClassModal">
        <i class="fa-solid fa-plus me-1"></i> Join Class
    </button>
</div>
<?php if (!$sectionId): ?>
    <div class="alert alert-info">You are not assigned to a section yet. Contact an administrator.</div>
<?php elseif (empty($activeSubjects) && empty($inactiveSubjects)): ?>
    <div class="alert alert-info">No subjects scheduled for your section yet.</div>
<?php else: ?>
    <div class="row g-3">
        <?php foreach ($activeSubjects as $row) { renderSubjectCard($row, $myQrToken); } ?>
    </div>
    <?php if (!empty($inactiveSubjects)): ?>
        <div class="text-center mt-4">
            <button class="btn btn-outline-secondary rounded-pill" type="button" data-bs-toggle="collapse" data-bs-target="#inactiveSubjects">
                View Inactive / Completed Subjects <i class="fa-solid fa-chevron-down ms-1"></i>
            </button>
        </div>
        <div class="collapse mt-3" id="inactiveSubjects">
            <div class="row g-3">
                <?php foreach ($inactiveSubjects as $row) { renderSubjectCard($row, $myQrToken); } ?>
            </div>
        </div>
    <?php endif; ?>
<?php endif; ?>

<div class="modal fade" id="joinClassModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-4">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title">Join class</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="post">
                <input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>">
                <input type="hidden" name="action" value="join_class">
                <div class="modal-body">
                    <div class="bg-light rounded-4 p-3 mb-3">
                        <p class="text-muted small mb-2">You're currently signed in as</p>
                        <div class="sp-person-row">
                            <i class="fa-solid fa-circle-user sp-person-avatar fa-2x text-secondary"></i>
                            <div>
                                <div class="fw-semibold"><?php echo htmlspecialchars(trim($myFirstName . ' ' . $myLastName)); ?></div>
                                <div class="sp-person-role"><?php echo htmlspecialchars($me['email']); ?></div>
                            </div>
                        </div>
                    </div>
                    <div class="bg-light rounded-4 p-3 mb-3">
                        <label class="form-label fw-semibold mb-1">Class code</label>
                        <p class="text-muted small mb-2">Ask your teacher for the class code, then enter it here.</p>
                        <input type="text" class="form-control" name="class_code" id="classCodeField" placeholder="Class code">
                    </div>
                    <div class="alert alert-warning small mb-3"><i class="fa-solid fa-triangle-exclamation me-1"></i> Joining a code replaces your current section and class list &mdash; it doesn't just add one class.</div>
                    <p class="fw-semibold small mb-1">To sign in with a class code</p>
                    <ul class="text-muted small mb-0 ps-3">
                        <li>Use a class code with 5&ndash;8 letters or numbers, no spaces or symbols</li>
                        <li>Ask your teacher or admin if you're having trouble joining</li>
                    </ul>
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-link text-decoration-none" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary rounded-pill" id="joinClassSubmit" disabled>Join</button>
                </div>
            </form>
        </div>
    </div>
</div>
<script>
    var classCodeField = document.getElementById('classCodeField');
    var joinClassSubmit = document.getElementById('joinClassSubmit');
    if (classCodeField && joinClassSubmit) {
        classCodeField.addEventListener('input', function () {
            joinClassSubmit.disabled = classCodeField.value.trim().length === 0;
        });
    }

    document.querySelectorAll('.sp-qr-save-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var target = document.getElementById(btn.getAttribute('data-target'));
            var filename = btn.getAttribute('data-filename');
            if (!target || typeof html2canvas === 'undefined') {
                return;
            }
            var originalText = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = 'Preparing...';
            html2canvas(target, { backgroundColor: '#ffffff', scale: 2 }).then(function (canvas) {
                var link = document.createElement('a');
                link.download = filename;
                link.href = canvas.toDataURL('image/png');
                link.click();
                btn.disabled = false;
                btn.innerHTML = originalText;
            }).catch(function () {
                btn.disabled = false;
                btn.innerHTML = originalText;
            });
        });
    });
</script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
<?php require_once __DIR__ . '/../includes/student_footer.php'; ?>
