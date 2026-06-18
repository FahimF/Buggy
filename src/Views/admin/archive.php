<div class="d-flex justify-content-between align-items-center mb-4">
    <h3>Archived Tasks</h3>
    <?php if ($totalArchived > 0): ?>
        <form action="/admin/archive/delete-all" method="POST" onsubmit="return confirm('Are you sure you want to permanently delete ALL archived tasks? This action cannot be undone.');">
            <button type="submit" class="btn btn-danger">
                <i class="bi bi-trash"></i> Delete All Archived Tasks
            </button>
        </form>
    <?php endif; ?>
</div>

<div class="card mb-4">
    <div class="card-body">
        <h5>Total Archived Tasks: <span class="badge bg-secondary"><?= (int)$totalArchived ?></span></h5>
    </div>
</div>

<div class="card">
    <div class="card-header">Archived Tasks by Project</div>
    <div class="card-body p-0">
        <?php if (empty($projects)): ?>
            <div class="p-4 text-center text-muted">
                No archived tasks found in any project.
            </div>
        <?php else: ?>
            <div class="list-group list-group-flush">
                <?php foreach ($projects as $project): ?>
                    <a href="/admin/archive/project/<?= $project['id'] ?>" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                        <span>
                            <i class="bi bi-folder me-2"></i>
                            <?= htmlspecialchars($project['name']) ?>
                        </span>
                        <span class="badge bg-primary rounded-pill"><?= $project['count'] ?></span>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>
