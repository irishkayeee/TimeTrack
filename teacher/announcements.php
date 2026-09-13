<?php
require_once __DIR__ . '/../includes/functions.php';
requireRole(['teacher']);
$pageTitle = 'Notifications';
$pageSubtitle = 'Stay updated on your classes.';

$teacherId = currentTeacherId();
if ($teacherId === false) {
    flash('Your teacher profile is not set up. Contact an administrator.', 'danger');
    redirect('../dashboard.php');
}

$stmt = $mysqli->prepare('SELECT n.*, s.name AS subject_name, s.code AS subject_code FROM notifications n LEFT JOIN subjects s ON n.subject_id = s.id WHERE n.teacher_id = ? ORDER BY n.created_at DESC');
$stmt->bind_param('i', $teacherId);
$stmt->execute();
$notifications = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$notifIcons = ['reminder' => 'fa-clock', 'summary' => 'fa-chart-simple', 'assignment' => 'fa-chalkboard-user', 'schedule_update' => 'fa-calendar-days'];

$notifSubjects = [];
foreach ($notifications as $n) {
    if ($n['subject_code'] && !isset($notifSubjects[$n['subject_code']])) {
        $notifSubjects[$n['subject_code']] = $n['subject_name'];
    }
}
ksort($notifSubjects);

require_once __DIR__ . '/../includes/teacher_header.php';
?>
<?php if (empty($notifications)): ?>
<div class="card p-4 text-center text-muted">
    <i class="fa-solid fa-bell fa-2x mb-2"></i>
    <p class="mb-0">No notifications yet. You'll get a reminder before class and a summary after each session.</p>
</div>
<?php else: ?>
<div class="d-flex flex-wrap align-items-center gap-2 mb-3 sp-notif-filters">
    <button type="button" class="btn btn-sm rounded-pill active" data-filter="all">All</button>
    <button type="button" class="btn btn-sm rounded-pill" data-filter="reminder">Reminders</button>
    <button type="button" class="btn btn-sm rounded-pill" data-filter="summary">Summaries</button>
    <button type="button" class="btn btn-sm rounded-pill" data-filter="assignment">Assignments</button>
    <button type="button" class="btn btn-sm rounded-pill" data-filter="schedule_update">Schedule Changes</button>
    <button type="button" class="btn btn-sm rounded-pill" data-filter="unread">Unread</button>
    <select class="form-select form-select-sm sp-filter-select ms-auto" id="spNotifSubjectFilter" style="width:auto;">
        <option value="all">All Subjects</option>
        <?php foreach ($notifSubjects as $code => $name): ?>
            <option value="<?php echo htmlspecialchars($code); ?>"><?php echo htmlspecialchars($code); ?></option>
        <?php endforeach; ?>
    </select>
</div>
<div class="d-flex flex-column gap-3" id="spNotifList">
    <?php foreach ($notifications as $n): ?>
    <div class="card p-3 sp-notif-card<?php echo $n['is_read'] ? '' : ' sp-notif-unread'; ?>" data-id="<?php echo $n['id']; ?>" data-type="<?php echo htmlspecialchars($n['type']); ?>" data-unread="<?php echo $n['is_read'] ? '0' : '1'; ?>" data-subject="<?php echo htmlspecialchars($n['subject_code'] ?: ''); ?>" role="button" tabindex="0">
        <div class="d-flex align-items-start gap-3">
            <i class="fa-solid <?php echo $notifIcons[$n['type']] ?? 'fa-bell'; ?> mt-1"></i>
            <div class="flex-grow-1">
                <div class="d-flex align-items-center gap-2 mb-1">
                    <strong class="small"><?php echo htmlspecialchars($n['title']); ?></strong>
                    <?php echo badgeStatus($n['type']); ?>
                    <?php if (!$n['is_read']): ?>
                        <span class="badge bg-primary sp-notif-new">New</span>
                    <?php endif; ?>
                </div>
                <p class="mb-2 text-muted small"><?php echo htmlspecialchars($n['message']); ?></p>
                <?php if ($n['subject_code']): ?>
                    <small class="text-muted"><?php echo htmlspecialchars($n['subject_code']); ?></small>
                <?php endif; ?>
            </div>
            <small class="text-muted text-end flex-shrink-0 sp-notif-time"><?php echo date('F j, Y g:i A', strtotime($n['created_at'])); ?></small>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<p class="text-muted text-center mt-3 d-none" id="spNotifEmpty">No notifications match this filter.</p>
<script>
    var filterBtns = document.querySelectorAll('.sp-notif-filters [data-filter]');
    var subjectSelect = document.getElementById('spNotifSubjectFilter');
    var notifCards = document.querySelectorAll('.sp-notif-card');
    var emptyMsg = document.getElementById('spNotifEmpty');
    var activeFilter = 'all';

    function applyNotifFilters() {
        var subject = subjectSelect.value;
        var visibleCount = 0;
        notifCards.forEach(function (card) {
            var matchesFilter = activeFilter === 'all'
                || (activeFilter === 'unread' && card.getAttribute('data-unread') === '1')
                || card.getAttribute('data-type') === activeFilter;
            var matchesSubject = subject === 'all' || card.getAttribute('data-subject') === subject;
            var matches = matchesFilter && matchesSubject;
            card.classList.toggle('d-none', !matches);
            if (matches) visibleCount++;
        });
        emptyMsg.classList.toggle('d-none', visibleCount !== 0);
    }

    filterBtns.forEach(function (btn) {
        btn.addEventListener('click', function () {
            filterBtns.forEach(function (b) { b.classList.remove('active'); });
            btn.classList.add('active');
            activeFilter = btn.getAttribute('data-filter');
            applyNotifFilters();
        });
    });

    subjectSelect.addEventListener('change', applyNotifFilters);

    var navBadge = document.getElementById('spNavNotifBadge');
    notifCards.forEach(function (card) {
        card.addEventListener('click', function () {
            if (card.getAttribute('data-unread') !== '1') {
                return;
            }
            card.setAttribute('data-unread', '0');
            card.classList.remove('sp-notif-unread');
            var newBadge = card.querySelector('.sp-notif-new');
            if (newBadge) {
                newBadge.remove();
            }
            if (navBadge) {
                var count = Math.max(0, (parseInt(navBadge.textContent, 10) || 0) - 1);
                navBadge.textContent = count > 9 ? '9+' : count;
                navBadge.classList.toggle('d-none', count === 0);
            }
            fetch('notifications-mark-read.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'id=' + encodeURIComponent(card.getAttribute('data-id'))
            });
        });
    });
</script>
<?php endif; ?>
<?php require_once __DIR__ . '/../includes/teacher_footer.php'; ?>
