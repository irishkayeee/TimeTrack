<?php
require_once __DIR__ . '/../includes/functions.php';
requireRole(['teacher']);

$teacherId = currentTeacherId();
if ($teacherId === false) {
    flash('Your teacher profile is not set up. Contact an administrator.', 'danger');
    redirect('../dashboard.php');
}

$subjectId = intval($_GET['subject_id'] ?? 0);
$stmt = $mysqli->prepare('SELECT sub.*, sec.room_name, sec.year_level, c.code AS course_code, c.name AS course_name
    FROM subjects sub
    JOIN rooms sec ON sub.room_id = sec.id
    JOIN courses c ON sec.course_id = c.id
    WHERE sub.id = ? AND sub.teacher_id = ? LIMIT 1');
$stmt->bind_param('ii', $subjectId, $teacherId);
$stmt->execute();
$subject = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$subject) {
    flash('Class not found.', 'danger');
    redirect('subjects.php');
}

$redirectTarget = 'class-announcements.php?subject_id=' . $subjectId;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        flash('Invalid form submission.', 'danger');
        redirect($redirectTarget);
    }
    $action = $_POST['action'] ?? '';

    if ($action === 'post_announcement' || $action === 'update_announcement') {
        $title = trim($_POST['title'] ?? '');
        $message = trim($_POST['message'] ?? '');
        if ($title === '') {
            $title = 'Announcement';
        }
        if ($message === '') {
            flash('Announcement message cannot be empty.', 'danger');
            redirect($redirectTarget);
        }

        if ($action === 'post_announcement') {
            $stmt = $mysqli->prepare('INSERT INTO class_announcements (subject_id, teacher_id, title, message) VALUES (?, ?, ?, ?)');
            $stmt->bind_param('iiss', $subjectId, $teacherId, $title, $message);
            $stmt->execute();
            $stmt->close();
            flash('Announcement posted.', 'success');
        } else {
            $id = intval($_POST['id'] ?? 0);
            $stmt = $mysqli->prepare('UPDATE class_announcements SET title = ?, message = ?, updated_at = NOW() WHERE id = ? AND subject_id = ? AND teacher_id = ?');
            $stmt->bind_param('ssiii', $title, $message, $id, $subjectId, $teacherId);
            $stmt->execute();
            $stmt->close();
            flash('Announcement updated.', 'success');
        }
        redirect($redirectTarget);
    }

    if ($action === 'delete_announcement') {
        $id = intval($_POST['id'] ?? 0);
        $stmt = $mysqli->prepare('DELETE FROM class_announcements WHERE id = ? AND subject_id = ? AND teacher_id = ?');
        $stmt->bind_param('iii', $id, $subjectId, $teacherId);
        $stmt->execute();
        $stmt->close();
        flash('Announcement deleted.', 'success');
        redirect($redirectTarget);
    }
}

$stmt = $mysqli->prepare('SELECT * FROM class_announcements WHERE subject_id = ? ORDER BY created_at DESC');
$stmt->bind_param('i', $subjectId);
$stmt->execute();
$announcements = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$pageTitle = $subject['code'];
$pageSubtitle = $subject['name'];

require_once __DIR__ . '/../includes/teacher_header.php';
?>
<a href="subjects.php" class="sp-back-link d-inline-flex mb-3"><i class="fa-solid fa-arrow-left"></i> Back to My Classes</a>

<?php renderTeacherClassHeader($subject, 'announcements'); ?>

<div class="card p-4 mb-3">
    <form method="post">
        <input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>">
        <input type="hidden" name="action" value="post_announcement">
        <div class="mb-3">
            <label class="form-label">Title <span class="text-muted fw-normal">(optional)</span></label>
            <input type="text" class="form-control" name="title" maxlength="150" placeholder="e.g. Reminder for next meeting">
        </div>
        <div class="mb-3">
            <label class="form-label">Message</label>
            <textarea class="form-control" name="message" rows="3" maxlength="2000" placeholder="Share an update, reminder, or reference with your students..." required></textarea>
        </div>
        <div class="text-end">
            <button type="submit" class="btn btn-primary rounded-pill px-4"><i class="fa-solid fa-paper-plane me-1"></i> Post Announcement</button>
        </div>
    </form>
</div>

<?php if (empty($announcements)): ?>
<div class="card sp-empty-state">
    <div class="sp-empty-state-icon"><i class="fa-solid fa-bullhorn"></i></div>
    <h5 class="sp-empty-state-title">No Announcements Yet</h5>
    <p class="sp-empty-state-text">Share updates, reminders, or reference materials with your students — they'll see them here as soon as you post.</p>
</div>
<?php else: ?>
<div class="d-flex flex-column gap-3">
    <?php foreach ($announcements as $a): ?>
    <div class="card p-3 sp-announce-card">
        <div class="d-flex align-items-start justify-content-between gap-3">
            <div class="d-flex align-items-start gap-3 flex-grow-1">
                <span class="sp-announce-icon"><i class="fa-solid fa-bullhorn"></i></span>
                <div class="flex-grow-1">
                    <strong class="d-block mb-1"><?php echo htmlspecialchars($a['title']); ?></strong>
                    <p class="mb-2 sp-announce-message"><?php echo nl2br(htmlspecialchars($a['message'])); ?></p>
                    <small class="text-muted">
                        Posted <?php echo formatDateTime($a['created_at']); ?><?php if ($a['updated_at']): ?> · Edited <?php echo formatDateTime($a['updated_at']); ?><?php endif; ?>
                    </small>
                </div>
            </div>
            <div class="d-flex gap-2 flex-shrink-0">
                <button type="button" class="btn btn-sm btn-outline-secondary btn-edit-announcement" data-data='<?php echo htmlspecialchars(json_encode($a), ENT_QUOTES, 'UTF-8'); ?>' title="Edit"><i class="fa-solid fa-pen"></i></button>
                <form method="post" onsubmit="return confirm('Delete this announcement?');">
                    <input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>">
                    <input type="hidden" name="action" value="delete_announcement">
                    <input type="hidden" name="id" value="<?php echo (int) $a['id']; ?>">
                    <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete"><i class="fa-solid fa-trash"></i></button>
                </form>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<div class="modal fade" id="editAnnouncementModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-4">
            <div class="modal-header">
                <h5 class="modal-title">Edit Announcement</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="post">
                <input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>">
                <input type="hidden" name="action" value="update_announcement">
                <input type="hidden" name="id" id="editAnnouncementId">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Title</label>
                        <input type="text" class="form-control" name="title" id="editAnnouncementTitle" maxlength="150">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Message</label>
                        <textarea class="form-control" name="message" id="editAnnouncementMessage" rows="4" maxlength="2000" required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    var editAnnouncementModal = new bootstrap.Modal(document.getElementById('editAnnouncementModal'));
    document.querySelectorAll('.btn-edit-announcement').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var data = JSON.parse(btn.getAttribute('data-data'));
            document.getElementById('editAnnouncementId').value = data.id;
            document.getElementById('editAnnouncementTitle').value = data.title;
            document.getElementById('editAnnouncementMessage').value = data.message;
            editAnnouncementModal.show();
        });
    });
</script>

<?php require_once __DIR__ . '/../includes/teacher_footer.php'; ?>
