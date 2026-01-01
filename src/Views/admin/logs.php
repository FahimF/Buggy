<h3>System Logs</h3>
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
