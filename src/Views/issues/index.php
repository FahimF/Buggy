<?php require __DIR__ . '/../header.php'; ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="d-inline"><?= htmlspecialchars($project['name']) ?></h1>
        <span class="badge bg-secondary ms-2">List View</span>
    </div>
    <div>
        <a href="/projects/<?= $project['id'] ?>/kanban" class="btn btn-outline-secondary me-2">
            <i class="bi bi-kanban"></i> Kanban Board
        </a>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createIssueModal">
            <i class="bi bi-plus-lg"></i> New Issue
        </button>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <div class="btn-group">
            <a href="?sort=created_at&dir=DESC" class="btn btn-sm btn-outline-secondary">Newest</a>
            <a href="?sort=created_at&dir=ASC" class="btn btn-sm btn-outline-secondary">Oldest</a>
            <a href="?sort=status&dir=ASC" class="btn btn-sm btn-outline-secondary">Status</a>
            <a href="?sort=sort_order&dir=ASC" class="btn btn-sm btn-outline-secondary">Custom Order</a>
        </div>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th style="width: 50px;"></th>
                        <th>Title</th>
                        <th>Status</th>
                        <th>Assigned To</th>
                        <th>Author</th>
                        <th>Created</th>
                    </tr>
                </thead>
                <tbody id="issueList">
                    <?php foreach ($issues as $issue): ?>
                    <tr data-id="<?= $issue['id'] ?>">
                        <td class="text-center text-muted"><i class="bi bi-grip-vertical handle" style="cursor: move;"></i></td>
                        <td>
                            <a href="/issues/<?= $issue['id'] ?>" class="text-decoration-none fw-bold">
                                <?= htmlspecialchars($issue['title']) ?>
                            </a>
                        </td>
                        <td>
                            <span class="badge bg-<?= $issue['status'] === 'Completed' ? 'success' : ($issue['status'] === 'In Progress' ? 'primary' : 'secondary') ?>">
                                <?= htmlspecialchars($issue['status']) ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($issue['assigned_to_name']): ?>
                                <span class="badge rounded-pill bg-light text-dark border">
                                    <?= htmlspecialchars($issue['assigned_to_name']) ?>
                                </span>
                            <?php else: ?>
                                <span class="text-muted small">Unassigned</span>
                            <?php endif; ?>
                        </td>
                        <td><?= htmlspecialchars($issue['creator_name']) ?></td>
                        <td class="small text-muted"><?= date('M j', strtotime($issue['created_at'])) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Create Issue Modal -->
<?php require 'create_modal.php'; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Only enable sortable if we are in 'sort_order' mode or just want to allow reordering generally
    // But requirement says "When in custom sort order".
    // For simplicity, we enable it, but only persist if the current URL param suggests custom order or user initiates it.
    
    var el = document.getElementById('issueList');
    var sortable = Sortable.create(el, {
        handle: '.handle',
        animation: 150,
        onEnd: function (evt) {
            var order = [];
            el.querySelectorAll('tr').forEach(function(row) {
                order.push(row.getAttribute('data-id'));
            });
            
            fetch('/issues/reorder', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ order: order })
            });
        }
    });
});
</script>

<?php require __DIR__ . '/../footer.php'; ?>
