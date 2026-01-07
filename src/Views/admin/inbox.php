<div class="d-flex justify-content-between align-items-center mb-4">
    <h2>Inbox Items</h2>
    <form action="/admin/inbox/clear-read" method="post" class="d-inline" onsubmit="return confirm('Are you sure you want to delete ALL read inbox items? This action cannot be undone.');">
        <button type="submit" class="btn btn-outline-danger">
            <i class="bi bi-trash"></i> Remove All Read Items
        </button>
    </form>
</div>

<?php if (empty($inboxItems)): ?>
    <div class="alert alert-info">
        <i class="bi bi-info-circle"></i> No inbox items found.
    </div>
<?php else: ?>
    <form action="/admin/inbox/delete-selected" method="post">
        <div class="card">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>
                                    <input type="checkbox" id="selectAll" onclick="toggleSelectAll(this)">
                                </th>
                                <th>ID</th>
                                <th>User</th>
                                <th>Task</th>
                                <th>Read Status</th>
                                <th>Created At</th>
                                <th>Due At</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($inboxItems as $item): ?>
                            <tr>
                                <td>
                                    <input type="checkbox" name="inbox_ids[]" value="<?= $item['id'] ?>">
                                </td>
                                <td><?= $item['id'] ?></td>
                                <td>
                                    <?php if ($item['user_id']): ?>
                                        <a href="/admin/users/update/<?= $item['user_id'] ?>"><?= htmlspecialchars($item['username'] ?? 'Unknown User') ?></a>
                                    <?php else: ?>
                                        <span class="text-muted">No user</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($item['task_id']): ?>
                                        <a href="/tasks/list/<?= $item['task_list_id'] ?? '#' ?>"><?= htmlspecialchars($item['task_title'] ?? 'Unknown Task') ?></a>
                                    <?php else: ?>
                                        <span class="text-muted">No task</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($item['is_read']): ?>
                                        <span class="badge bg-success">Read</span>
                                    <?php else: ?>
                                        <span class="badge bg-warning text-dark">Unread</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= date('M j, Y g:i A', strtotime($item['created_at'])) ?></td>
                                <td>
                                    <?php if ($item['due_at'] === null): ?>
                                        <span class="badge bg-primary">Now</span>
                                    <?php else: ?>
                                        <?= date('M j, Y g:i A', strtotime($item['due_at'])) ?>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <button type="submit" class="btn btn-danger" onclick="return confirm('Are you sure you want to delete the selected inbox items? This action cannot be undone.');">
                            <i class="bi bi-trash"></i> Delete Selected
                        </button>
                    </div>
                    <div>
                        <?= $pagination ?>
                    </div>
                </div>
            </div>
        </div>
    </form>
<?php endif; ?>

<script>
function toggleSelectAll(checkbox) {
    const checkboxes = document.querySelectorAll('input[name="inbox_ids[]"]');
    checkboxes.forEach(cb => cb.checked = checkbox.checked);
}
</script>