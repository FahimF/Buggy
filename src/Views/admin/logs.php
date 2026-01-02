<div class="d-flex justify-content-between align-items-center mb-3">
    <h3>System Logs (<?= count($logs) ?>)</h3>
    <form action="/admin/logs/clear" method="post" onsubmit="return confirm('Are you sure you want to delete ALL system logs? This action cannot be undone.');">
        <button type="submit" class="btn btn-danger btn-sm">
            <i class="bi bi-trash"></i> Clear All Logs
        </button>
    </form>
</div>

<div class="table-responsive">
    <table class="table table-sm table-bordered">
        <thead class="table-dark">
            <tr>
                <th>Time</th>
                <th>User</th>
                <th>Action</th>
                <th>Details</th>
                <th>IP</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($logs as $log): ?>
            <tr>
                <td class="small"><?= $log['created_at'] ?></td>
                <td>
                    <?php if ($log['username']): ?>
                        <?= htmlspecialchars($log['username']) ?>
                    <?php else: ?>
                        <span class="text-muted">System/Guest</span>
                    <?php endif; ?>
                </td>
                <td><?= htmlspecialchars($log['action']) ?></td>
                <td class="small text-muted text-break"><?= htmlspecialchars($log['details']) ?></td>
                <td class="small"><?= htmlspecialchars($log['ip_address']) ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
