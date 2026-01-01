<h3>User Management</h3>
<div class="table-responsive">
    <table class="table table-striped">
        <thead>
            <tr>
                <th>ID</th>
                <th>Username</th>
                <th>Role</th>
                <th>Created At</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($users as $u): ?>
            <tr>
                <td><?= $u['id'] ?></td>
                <td><?= htmlspecialchars($u['username']) ?></td>
                <td>
                    <?php if ($u['is_admin']): ?>
                        <span class="badge bg-danger">Admin</span>
                    <?php else: ?>
                        <span class="badge bg-secondary">User</span>
                    <?php endif; ?>
                </td>
                <td><?= $u['created_at'] ?></td>
                <td>
                    <?php if ($u['id'] != Auth::user()['id']): ?>
                    <form action="/admin/users/toggle_admin" method="post" class="d-inline">
                        <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                        <button class="btn btn-sm btn-outline-warning" title="Toggle Admin">
                            <i class="bi bi-shield-lock"></i>
                        </button>
                    </form>
                    <form action="/admin/users/delete" method="post" class="d-inline" onsubmit="return confirm('Delete this user? All their content will be removed (or handled by DB constraints).');">
                        <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                        <button class="btn btn-sm btn-outline-danger" title="Delete User">
                            <i class="bi bi-trash"></i>
                        </button>
                    </form>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
