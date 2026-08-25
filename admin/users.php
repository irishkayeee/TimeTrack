<?php
require_once __DIR__ . '/../includes/functions.php';
requireRole(['superadmin']);
$pageTitle = 'Admin Accounts';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        flash('Invalid request.', 'danger');
        redirect('users.php');
    }
    if ($_POST['action'] === 'save_user') {
        $id = intval($_POST['id'] ?? 0);
        $username = sanitize($_POST['username'] ?? '');
        $email = sanitize($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $status = sanitize($_POST['status'] ?? 'active');
        if (!$password) {
            flash('Password is required.', 'danger');
            redirect('users.php');
        }
        if ($id) {
            $stmt = $mysqli->prepare('SELECT role, password_hash FROM users WHERE id = ?');
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $existing = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if (!$existing) {
                flash('User not found.', 'danger');
                redirect('users.php');
            }
            $role = $existing['role'];
            if (password_verify($password, $existing['password_hash'])) {
                flash('New password must be different from the current password.', 'danger');
                redirect('users.php');
            }
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $mysqli->prepare('UPDATE users SET username = ?, email = ?, password_hash = ?, role = ?, status = ? WHERE id = ?');
            $stmt->bind_param('sssssi', $username, $email, $hash, $role, $status, $id);
            $stmt->execute();
            $stmt->close();
            flash('User updated.', 'success');
        } else {
            $role = 'admin';
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $mysqli->prepare('INSERT INTO users (username, email, password_hash, role, status, created_at) VALUES (?, ?, ?, ?, ?, NOW())');
            $stmt->bind_param('sssss', $username, $email, $hash, $role, $status);
            $stmt->execute();
            $stmt->close();
            flash('User created.', 'success');
        }
        redirect('users.php');
    }
    if ($_POST['action'] === 'delete_user' && !empty($_POST['user_id'])) {
        $id = intval($_POST['user_id']);
        $stmt = $mysqli->prepare('DELETE FROM users WHERE id = ?');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $stmt->close();
        flash('User deleted.', 'success');
        redirect('users.php');
    }
}
$users = $mysqli->query("SELECT id, username, email, role, status, created_at FROM users WHERE role IN ('admin', 'superadmin') ORDER BY created_at DESC");
require_once __DIR__ . '/../includes/admin_header.php';
?>
<div class="card rounded-4 shadow-sm p-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4>User Management</h4>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#userModal">Add User</button>
    </div>
    <div class="table-responsive">
        <table class="table table-hover" id="usersTable">
            <thead class="table-light">
                <tr><th>Username</th><th>Email</th><th>Role</th><th>Status</th><th>Created</th><th>Actions</th></tr>
            </thead>
            <tbody>
                <?php while ($row = $users->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($row['username']); ?></td>
                        <td><?php echo htmlspecialchars($row['email']); ?></td>
                        <td><?php echo htmlspecialchars(ucfirst($row['role'])); ?></td>
                        <td><?php echo badgeStatus($row['status']); ?></td>
                        <td><?php echo formatDateTime($row['created_at']); ?></td>
                        <td>
                            <div class="d-flex align-items-center gap-1">
                                <button class="btn btn-sm btn-outline-secondary btn-icon btn-view-user" data-data='<?php echo json_encode($row); ?>' title="View"><i class="fa-solid fa-eye"></i></button>
                                <button class="btn btn-sm btn-outline-primary btn-icon btn-edit-user" data-data='<?php echo json_encode($row); ?>' title="Edit"><i class="fa-solid fa-pen"></i></button>
                                <form method="post" class="d-inline-block" onsubmit="return confirm('Delete this user?');">
                                    <input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>">
                                    <input type="hidden" name="action" value="delete_user">
                                    <input type="hidden" name="user_id" value="<?php echo $row['id']; ?>">
                                    <button class="btn btn-sm btn-outline-danger btn-icon" title="Delete"><i class="fa-solid fa-trash"></i></button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>
<div class="modal fade" id="userModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-4">
            <div class="modal-header">
                <h5 class="modal-title">User Form</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="post">
                <input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>">
                <input type="hidden" name="action" value="save_user">
                <input type="hidden" name="id" id="userIdField">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Username</label>
                        <input type="text" class="form-control" name="username" id="usernameField" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Email</label>
                        <input type="email" class="form-control" name="email" id="emailField" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Password</label>
                        <input type="password" class="form-control" name="password" id="passwordField" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Role</label>
                        <select class="form-select" id="roleField" disabled>
                            <option value="admin">Admin</option>
                            <option value="superadmin">Super Admin</option>
                        </select>
                        <small class="text-muted">New users are always created as Admin. Role cannot be changed here.</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Status</label>
                        <select class="form-select" name="status" id="userStatusField">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save User</button>
                </div>
            </form>
        </div>
    </div>
</div>
<div class="modal fade" id="userViewModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-4">
            <div class="modal-header">
                <h5 class="modal-title">User Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <dl class="row mb-0">
                    <dt class="col-5">Username</dt><dd class="col-7" id="viewUserUsername"></dd>
                    <dt class="col-5">Email</dt><dd class="col-7" id="viewUserEmail"></dd>
                    <dt class="col-5">Role</dt><dd class="col-7" id="viewUserRole"></dd>
                    <dt class="col-5">Status</dt><dd class="col-7" id="viewUserStatus"></dd>
                    <dt class="col-5">Created</dt><dd class="col-7" id="viewUserCreated"></dd>
                </dl>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
const statusBadgeClass = { active: 'success', inactive: 'secondary' };
const monthNames = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
function formatDateTime(sqlDateTime) {
    if (!sqlDateTime) return '—';
    const [datePart, timePart] = sqlDateTime.split(' ');
    const [year, month, day] = datePart.split('-').map(Number);
    const [hour, minute] = timePart.split(':').map(Number);
    const period = hour >= 12 ? 'PM' : 'AM';
    const hour12 = hour % 12 || 12;
    return `${monthNames[month - 1]} ${day}, ${year} ${hour12}:${String(minute).padStart(2, '0')} ${period}`;
}
const userViewModal = new bootstrap.Modal(document.getElementById('userViewModal'));
document.querySelectorAll('.btn-view-user').forEach(btn => {
    btn.addEventListener('click', () => {
        const data = JSON.parse(btn.getAttribute('data-data'));
        document.getElementById('viewUserUsername').textContent = data.username || '—';
        document.getElementById('viewUserEmail').textContent = data.email || '—';
        document.getElementById('viewUserRole').textContent = data.role ? data.role.charAt(0).toUpperCase() + data.role.slice(1) : '—';
        document.getElementById('viewUserStatus').innerHTML = `<span class="badge bg-${statusBadgeClass[data.status] || 'secondary'}">${(data.status || '').charAt(0).toUpperCase() + (data.status || '').slice(1)}</span>`;
        document.getElementById('viewUserCreated').textContent = formatDateTime(data.created_at);
        userViewModal.show();
    });
});
const userModal = new bootstrap.Modal(document.getElementById('userModal'));
document.querySelectorAll('.btn-edit-user').forEach(btn => {
    btn.addEventListener('click', () => {
        const data = JSON.parse(btn.getAttribute('data-data'));
        document.getElementById('userIdField').value = data.id;
        document.getElementById('usernameField').value = data.username;
        document.getElementById('emailField').value = data.email;
        document.getElementById('roleField').value = data.role;
        document.getElementById('userStatusField').value = data.status;
        userModal.show();
    });
});
$('#usersTable').DataTable({ responsive: true });
});
</script>
<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>