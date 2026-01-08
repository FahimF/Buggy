<?php require __DIR__ . '/../header.php'; ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1>User Inbox</h1>
    <div class="d-flex gap-2">
        <form action="/tasks/mark-all-completed" method="post" class="d-inline">
            <button type="submit" class="btn btn-outline-primary" onclick="return confirm('Mark all tasks as completed?')">Mark All Completed</button>
        </form>
    </div>
</div>

<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-body">
                <?php if (empty($inboxTasks)): ?>
                    <div class="text-center py-5">
                        <i class="bi bi-inbox fs-1 text-muted"></i>
                        <p class="text-muted">No tasks in your inbox. All caught up!</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Task</th>
                                    <th>List</th>
                                    <th>Priority</th>
                                    <th>Status</th>
                                    <th>Due</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($inboxTasks as $task): ?>
                                <tr>
                                    <td>
                                        <strong><?= htmlspecialchars($task['title']) ?></strong>
                                        <div class="text-muted small"><?= htmlspecialchars(substr($task['description'], 0, 100)) . (strlen($task['description']) > 100 ? '...' : '') ?></div>
                                    </td>
                                    <td>
                                        <?= htmlspecialchars($task['list_title']) ?>
                                        <?php if ($task['assigned_by_name']): ?>
                                            <div class="text-muted small">by <?= htmlspecialchars($task['assigned_by_name']) ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php
                                        $priorityClass = '';
                                        switch ($task['priority']) {
                                            case 'High': $priorityClass = 'danger'; break;
                                            case 'Medium': $priorityClass = 'warning'; break;
                                            case 'Low': $priorityClass = 'success'; break;
                                        }
                                        ?>
                                        <span class="badge bg-<?= $priorityClass ?>"><?= htmlspecialchars($task['priority']) ?></span>
                                    </td>
                                    <td>
                                        <?php
                                        $statusClass = '';
                                        switch ($task['status']) {
                                            case 'completed': $statusClass = 'success'; break;
                                            case 'ND': $statusClass = 'secondary'; break;
                                            case 'WND': $statusClass = 'dark'; break;
                                            default: $statusClass = 'primary'; break;
                                        }
                                        ?>
                                        <span class="badge bg-<?= $statusClass ?>"><?= htmlspecialchars($task['status']) ?></span>
                                    </td>
                                    <td>
                                        <?php if ($task['due_at'] === null): ?>
                                            <span class="badge bg-primary">Now</span>
                                        <?php else: ?>
                                            <?= date('M j, Y g:i A', strtotime($task['due_at'])) ?>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="btn-group">
                                            <a href="/tasks/list/<?= $task['list_id'] ?>" class="btn btn-sm btn-outline-primary" title="View Task">
                                                <i class="bi bi-eye"></i>
                                            </a>
                                            <form action="/tasks/mark-completed" method="post" class="d-inline">
                                                <input type="hidden" name="task_id" value="<?= $task['id'] ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-success" title="Mark Completed">
                                                    <i class="bi bi-check"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../footer.php'; ?>