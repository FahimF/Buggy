<?php require __DIR__ . '/../header.php'; ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1>User Inbox</h1>
    <div class="d-flex gap-2">
        <form action="/jobs/mark-all-completed" method="post" class="d-inline">
            <button type="submit" class="btn btn-outline-primary" onclick="return confirm('Mark all jobs as completed?')">Mark All Completed</button>
        </form>
    </div>
</div>

<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-body">
                <?php if (empty($inboxJobs)): ?>
                    <div class="text-center py-5">
                        <i class="bi bi-inbox fs-1 text-muted"></i>
                        <p class="text-muted">No jobs in your inbox. All caught up!</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Job</th>
                                    <th>List</th>
                                    <th>Priority</th>
                                    <th>Status</th>
                                    <th>Due</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($inboxJobs as $job): ?>
                                <tr>
                                    <td>
                                        <strong><?= htmlspecialchars($job['title']) ?></strong>
                                        <div class="text-muted small"><?= htmlspecialchars(substr($job['description'], 0, 100)) . (strlen($job['description']) > 100 ? '...' : '') ?></div>
                                    </td>
                                    <td>
                                        <?= htmlspecialchars($job['list_title']) ?>
                                        <?php if ($job['assigned_by_name']): ?>
                                            <div class="text-muted small">by <?= htmlspecialchars($job['assigned_by_name']) ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php
                                        $priorityClass = '';
                                        switch ($job['priority']) {
                                            case 'High': $priorityClass = 'danger'; break;
                                            case 'Medium': $priorityClass = 'warning'; break;
                                            case 'Low': $priorityClass = 'success'; break;
                                        }
                                        ?>
                                        <span class="badge bg-<?= $priorityClass ?>"><?= htmlspecialchars($job['priority']) ?></span>
                                    </td>
                                    <td>
                                        <?php
                                        $statusClass = '';
                                        switch ($job['status']) {
                                            case 'completed': $statusClass = 'success'; break;
                                            case 'ND': $statusClass = 'secondary'; break;
                                            case 'WND': $statusClass = 'dark'; break;
                                            default: $statusClass = 'primary'; break;
                                        }
                                        ?>
                                        <span class="badge bg-<?= $statusClass ?>"><?= htmlspecialchars($job['status']) ?></span>
                                    </td>
                                    <td>
                                        <?php if ($job['due_at'] === null): ?>
                                            <span class="badge bg-primary">Now</span>
                                        <?php else: ?>
                                            <?= date('M j, Y g:i A', strtotime($job['due_at'])) ?>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="btn-group">
                                            <a href="/jobs/list/<?= $job['list_id'] ?>" class="btn btn-sm btn-outline-primary" title="View Job">
                                                <i class="bi bi-eye"></i>
                                            </a>
                                            <form action="/jobs/mark-completed" method="post" class="d-inline">
                                                <input type="hidden" name="job_id" value="<?= $job['id'] ?>">
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