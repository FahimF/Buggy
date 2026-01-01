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
    <div class="card-header d-flex justify-content-between align-items-center">
        <div class="btn-group">
            <?php 
            $baseParams = $_GET;
            $hideCompletedParam = isset($hideCompleted) && $hideCompleted ? '&hide_completed=1' : '&hide_completed=0';
            ?>
            <a href="?sort=created_at&dir=DESC<?= $hideCompletedParam ?>" class="btn btn-sm btn-outline-secondary">Newest</a>
            <a href="?sort=created_at&dir=ASC<?= $hideCompletedParam ?>" class="btn btn-sm btn-outline-secondary">Oldest</a>
            <a href="?sort=priority&dir=ASC<?= $hideCompletedParam ?>" class="btn btn-sm btn-outline-secondary">Priority</a>
            <a href="?sort=title&dir=ASC<?= $hideCompletedParam ?>" class="btn btn-sm btn-outline-secondary">Title</a>
            <a href="?sort=status&dir=ASC<?= $hideCompletedParam ?>" class="btn btn-sm btn-outline-secondary">Status</a>
            <a href="?sort=type&dir=ASC<?= $hideCompletedParam ?>" class="btn btn-sm btn-outline-secondary">Type</a>
            <a href="?sort=sort_order&dir=ASC<?= $hideCompletedParam ?>" class="btn btn-sm btn-outline-secondary">Custom Order</a>
        </div>
        <div class="form-check form-switch m-0">
            <input class="form-check-input" type="checkbox" id="hideCompletedCheck" <?= isset($hideCompleted) && $hideCompleted ? 'checked' : '' ?> onchange="window.location.href='?sort=<?= $sort ?>&dir=<?= $dir ?>&hide_completed=' + (this.checked ? '1' : '0')">
            <label class="form-check-label" for="hideCompletedCheck">Hide Completed</label>
        </div>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th style="width: 50px;"></th>
                        <th>Title</th>
                        <th>Priority</th>
                        <th>Type</th>
                        <th>Status</th>
                        <th>Assigned To</th>
                        <th>Author</th>
                        <th>Created</th>
                        <th>Actions</th>
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
                            <div class="description-preview">
                                <?= strip_tags($issue['description']) ?>
                            </div>
                        </td>
                        <td>
                            <?php 
                            $priorityClass = 'bg-secondary';
                            if (($issue['priority'] ?? 'Medium') === 'High') $priorityClass = 'bg-danger';
                            elseif (($issue['priority'] ?? 'Medium') === 'Medium') $priorityClass = 'bg-warning text-dark';
                            elseif (($issue['priority'] ?? 'Medium') === 'Low') $priorityClass = 'bg-success';
                            ?>
                            <span class="badge <?= $priorityClass ?>"><?= htmlspecialchars($issue['priority'] ?? 'Medium') ?></span>
                        </td>
                        <td>
                            <?php if (($issue['type'] ?? 'Bug') === 'Bug'): ?>
                                <span class="badge bg-danger">Bug</span>
                            <?php else: ?>
                                <span class="badge bg-info text-dark">Feature</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="badge <?= getStatusBadgeClass($issue['status']) ?>">
                                <?= htmlspecialchars($issue['status']) ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($issue['assigned_to_name']): ?>
                                <?php 
                                $isCurrentUser = $issue['assigned_to_name'] === Auth::user()['username'];
                                $badgeClass = $isCurrentUser ? 'bg-warning text-dark' : 'bg-light text-dark border';
                                ?>
                                <span class="badge rounded-pill <?= $badgeClass ?>">
                                    <?= htmlspecialchars($issue['assigned_to_name']) ?>
                                </span>
                            <?php else: ?>
                                <span class="text-muted small">Unassigned</span>
                            <?php endif; ?>
                        </td>
                        <td><?= htmlspecialchars($issue['creator_name']) ?></td>
                        <td class="small text-muted"><?= date('M j', strtotime($issue['created_at'])) ?></td>
                        <td>
                            <div class="btn-group btn-group-sm">
                                <a href="/issues/<?= $issue['id'] ?>/edit" class="btn btn-outline-primary" title="Edit">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                <form action="/issues/<?= $issue['id'] ?>/delete" method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this issue?');">
                                    <button type="submit" class="btn btn-outline-danger" title="Delete">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </form>
                            </div>
                        </td>
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
