<nav aria-label="breadcrumb">
  <ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="/admin/archive">Archived Tasks</a></li>
    <li class="breadcrumb-item active" aria-current="page"><?= htmlspecialchars($project['name']) ?></li>
  </ol>
</nav>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h3>Archived Tasks in <?= htmlspecialchars($project['name']) ?> (<?= count($tasks) ?>)</h3>
</div>

<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th>Title</th>
                        <th>Priority</th>
                        <th>Type</th>
                        <th>Assigned To</th>
                        <th>Author</th>
                        <th>Archived Date</th>
                        <th class="text-end" style="width: 200px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($tasks)): ?>
                        <tr>
                            <td colspan="7" class="p-4 text-center text-muted">
                                No archived tasks found in this project.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($tasks as $task): ?>
                            <tr>
                                <td>
                                    <strong class="text-dark"><?= htmlspecialchars($task['title']) ?></strong>
                                    <div class="description-preview text-muted small">
                                        <?= strip_tags($task['description']) ?>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge <?= getPriorityBadgeClass($task['priority'] ?? 'Medium') ?>">
                                        <?= htmlspecialchars($task['priority'] ?? 'Medium') ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if (($task['type'] ?? 'Bug') === 'Bug'): ?>
                                        <span class="badge bg-danger">Bug</span>
                                    <?php else: ?>
                                        <span class="badge bg-info text-dark">Feature</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?= $task['assigned_to_name'] ? htmlspecialchars($task['assigned_to_name']) : '<span class="text-muted small">Unassigned</span>' ?>
                                </td>
                                <td><?= htmlspecialchars($task['creator_name']) ?></td>
                                <td class="small text-muted"><?= date('M j, Y H:i', strtotime($task['updated_at'])) ?></td>
                                <td class="text-end">
                                    <div class="btn-group btn-group-sm">
                                        <form action="/admin/archive/tasks/<?= $task['id'] ?>/unarchive" method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to unarchive this task and move it back to Completed?');">
                                            <button type="submit" class="btn btn-outline-success" title="Unarchive & Move to Completed">
                                                <i class="bi bi-arrow-up-left-circle"></i> Unarchive
                                            </button>
                                        </form>
                                        <form action="/admin/archive/tasks/<?= $task['id'] ?>/delete" method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to permanently delete this task? This action cannot be undone.');">
                                            <button type="submit" class="btn btn-outline-danger" title="Permanently Delete">
                                                <i class="bi bi-trash"></i> Delete
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
