<?php
require_once __DIR__ . '/../includes/functions.php';
requireRole(['superadmin']);
$pageTitle = 'User Accounts';
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
        $role = sanitize($_POST['role'] ?? 'admin');
        $status = sanitize($_POST['status'] ?? 'active');
        if ($id) {
            if ($password) {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $mysqli->prepare('UPDATE users SET username = ?, email = ?, password_hash = ?, role = ?, status = ? WHERE id = ?');
                $stmt->bind_param('sssssi', $username, $email, $hash, $role, $status, $id);
            } else {
                $stmt = $mysqli->prepare('UPDATE users SET username = ?, email = ?, role = ?, status = ? WHERE id = ?');
                $stmt->bind_param('ssssi', $username, $email, $role, $status, $id);
            }
            $stmt->execute();
            $stmt->close();
            flash('User updated.', 'success');
        } else {
            $hash = password_hash($password ?: 'password123', PASSWORD_DEFAULT);
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
$users = $mysqli->query('SELECT id, username, email, role, status, created_at FROM users ORDER BY created_at DESC');
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/admin_nav.php';
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
                        <td><?php echo htmlspecialchars($row['created_at']); ?></td>
                        <td>
                            <button class="btn btn-sm btn-outline-primary btn-edit-user" data-data='<?php echo json_encode($row); ?>'>Edit</button>
                            <form method="post" class="d-inline-block" onsubmit="return confirm('Delete this user?');">
                                <input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>">
                                <input type="hidden" name="action" value="delete_user">
                                <input type="hidden" name="user_id" value="<?php echo $row['id']; ?>">
                                <button class="btn btn-sm btn-outline-danger">Delete</button>
                            </form>
                        </td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>
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
                        <input type="password" class="form-control" name="password" id="passwordField">
                        <small class="text-muted">Leave blank to keep existing password.</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Role</label>
                        <select class="form-select" name="role" id="roleField">
                            <option value="superadmin">Super Admin</option>
                            <option value="admin">Admin</option>
                            <option value="teacher">Teacher</option>
                        </select>
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
<script>
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
$(document).ready(function () {
    $('#usersTable').DataTable({ responsive: true });
});
</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>