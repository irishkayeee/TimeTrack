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

    $classStmt = $mysqli->prepare('SELECT teacher_id, section_id, code, name FROM subjects WHERE join_code = ? LIMIT 1');
    $classStmt->bind_param('s', $classCode);
    $classStmt->execute();
    $targetClass = $classStmt->get_result()->fetch_assoc();
    $classStmt->close();

    if (!$targetClass) {
        flash('That class code doesn\'t match any class. Double-check it with your teacher.', 'danger');
        redirect('subjects.php');
    }

    // A class is every subjects row (one per meeting day) sharing the same
    // teacher + section + code, so enroll the student in all of them at once.
    $rowsStmt = $mysqli->prepare('SELECT id FROM subjects WHERE teacher_id <=> ? AND section_id = ? AND code = ?');
    $rowsStmt->bind_param('iis', $targetClass['teacher_id'], $targetClass['section_id'], $targetClass['code']);
    $rowsStmt->execute();
    $rowsResult = $rowsStmt->get_result();
    $classSubjectIds = [];
    while ($r = $rowsResult->fetch_assoc()) {
        $classSubjectIds[] = (int) $r['id'];
    }
    $rowsStmt->close();

    $joinedCount = 0;
    foreach ($classSubjectIds as $subjId) {
        $insStmt = $mysqli->prepare('INSERT IGNORE INTO enrollments (student_id, subject_id) VALUES (?, ?)');
        $insStmt->bind_param('ii', $studentDbId, $subjId);
        $insStmt->execute();
        $joinedCount += $mysqli->affected_rows > 0 ? 1 : 0;
        $insStmt->close();
    }

    if ($joinedCount === 0) {
        flash('You\'re already in ' . $targetClass['name'] . '.', 'info');
    } else {
        flash('Joined ' . $targetClass['name'] . '. It now appears in your class list.', 'success');
    }
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
$myFullName = trim($myFirstName . ' ' . $myLastName);

$myCourseYear = '';
if ($sectionId) {
    $courseStmt = $mysqli->prepare('SELECT c.code AS course_code, sec.year_level FROM sections sec JOIN courses c ON sec.course_id = c.id WHERE sec.id = ? LIMIT 1');
    $courseStmt->bind_param('i', $sectionId);
    $courseStmt->execute();
    $courseInfo = $courseStmt->get_result()->fetch_assoc();
    $courseStmt->close();
    if ($courseInfo) {
        $myCourseYear = $courseInfo['course_code'] . ' - ' . $courseInfo['year_level'];
    }
}

$activeSubjects = [];
$inactiveSubjects = [];
$sectionIdParam = $sectionId ?: 0;
$stmt = $mysqli->prepare("SELECT DISTINCT sub.*, CONCAT(t.first_name, ' ', t.last_name) AS teacher_name
    FROM subjects sub
    LEFT JOIN teachers t ON sub.teacher_id = t.id
    LEFT JOIN enrollments e ON e.subject_id = sub.id AND e.student_id = ?
    WHERE sub.section_id = ? OR e.id IS NOT NULL
    ORDER BY sub.name");
$stmt->bind_param('ii', $studentDbId, $sectionIdParam);
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

function renderSubjectCard($row, $qrToken, $studentName = '', $studentCourseYear = '') {
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
    <div class="col-md-4 sp-subject-col" data-search="<?php echo htmlspecialchars(strtolower($row['code'] . ' ' . $row['name'] . ' ' . $row['teacher_name'])); ?>">
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
                        <p class="text-muted mb-1"><?php echo htmlspecialchars($row['name']); ?></p>
                        <?php if ($studentName !== ''): ?>
                            <p class="sp-qr-owner mb-0"><?php echo htmlspecialchars($studentName); ?><?php echo $studentCourseYear !== '' ? ' &middot; ' . htmlspecialchars($studentCourseYear) : ''; ?></p>
                        <?php endif; ?>
                        <hr class="sp-qr-divider">
                        <?php if ($qrImageExists): ?>
                            <div class="sp-qr-frame">
                                <img src="../qrcodes/<?php echo urlencode($qrFilename); ?>" alt="QR code for <?php echo htmlspecialchars($row['code']); ?>" class="img-fluid">
                            </div>
                        <?php endif; ?>
                    </div>
                    <?php if ($qrImageExists): ?>
                        <button type="button" class="btn btn-outline-success rounded-pill w-100 mt-3 sp-qr-save-btn" data-target="qrCard<?php echo $row['id']; ?>" data-filename="qr_<?php echo htmlspecialchars($row['code']); ?>.png"><i class="fa-solid fa-download me-1"></i> Save QR Code</button>
                        <button type="button" class="btn btn-outline-secondary rounded-pill w-100 mt-2 sp-qr-print-btn" data-target="qrCard<?php echo $row['id']; ?>"><i class="fa-solid fa-print me-1"></i> Print QR Code</button>
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
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <div class="sp-subject-search">
        <i class="fa-solid fa-magnifying-glass"></i>
        <input type="text" class="form-control" id="spSubjectSearch" placeholder="Search subjects...">
    </div>
    <div class="d-flex gap-2">
        <?php $allSubjects = array_merge($activeSubjects, $inactiveSubjects); ?>
        <div class="dropdown">
            <button type="button" class="btn btn-outline-success rounded-pill dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false" <?php echo empty($allSubjects) ? 'disabled' : ''; ?>>
                <i class="fa-solid fa-qrcode me-1"></i> QR Code
            </button>
            <ul class="dropdown-menu dropdown-menu-end">
                <?php foreach ($allSubjects as $s): ?>
                    <li><a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#qrModal<?php echo $s['id']; ?>"><?php echo htmlspecialchars($s['code'] . ' - ' . $s['name']); ?></a></li>
                <?php endforeach; ?>
            </ul>
        </div>
        <button type="button" class="btn btn-primary rounded-pill" data-bs-toggle="modal" data-bs-target="#joinClassModal">
            <i class="fa-solid fa-plus me-1"></i> Join Class
        </button>
    </div>
</div>
<?php if (!$sectionId && empty($activeSubjects) && empty($inactiveSubjects)): ?>
    <div class="alert alert-info">You are not assigned to a section yet. Contact an administrator.</div>
<?php elseif (empty($activeSubjects) && empty($inactiveSubjects)): ?>
    <div class="alert alert-info">No subjects scheduled for your section yet.</div>
<?php else: ?>
    <div class="row g-3">
        <?php foreach ($activeSubjects as $row) { renderSubjectCard($row, $myQrToken, $myFullName, $myCourseYear); } ?>
    </div>
    <?php if (!empty($inactiveSubjects)): ?>
        <div class="text-center mt-4">
            <button class="btn btn-outline-secondary rounded-pill" type="button" data-bs-toggle="collapse" data-bs-target="#inactiveSubjects">
                View Inactive / Completed Subjects <i class="fa-solid fa-chevron-down ms-1"></i>
            </button>
        </div>
        <div class="collapse mt-3" id="inactiveSubjects">
            <div class="row g-3">
                <?php foreach ($inactiveSubjects as $row) { renderSubjectCard($row, $myQrToken, $myFullName, $myCourseYear); } ?>
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
                    <div class="alert alert-info small mb-3"><i class="fa-solid fa-circle-info me-1"></i> Joining a code adds that one class to your list &mdash; it won't remove or replace your other classes.</div>
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
    var subjectSearchField = document.getElementById('spSubjectSearch');
    if (subjectSearchField) {
        subjectSearchField.addEventListener('input', function () {
            var query = this.value.trim().toLowerCase();
            document.querySelectorAll('.sp-subject-col').forEach(function (col) {
                var matches = col.getAttribute('data-search').indexOf(query) !== -1;
                col.classList.toggle('d-none', !matches);
            });
        });
    }

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

    document.querySelectorAll('.sp-qr-print-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var target = document.getElementById(btn.getAttribute('data-target'));
            if (!target || typeof html2canvas === 'undefined') {
                return;
            }
            var originalText = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = 'Preparing...';
            html2canvas(target, { backgroundColor: '#ffffff', scale: 2 }).then(function (canvas) {
                var dataUrl = canvas.toDataURL('image/png');
                var printWindow = window.open('', '_blank');
                printWindow.document.write('<html><head><title>Print QR Code</title><style>body{margin:0;display:flex;align-items:center;justify-content:center;min-height:100vh;}img{max-width:100%;}</style></head><body><img src="' + dataUrl + '" onload="window.print();"></body></html>');
                printWindow.document.close();
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
