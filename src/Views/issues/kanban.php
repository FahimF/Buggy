<?php require __DIR__ . '/../header.php'; ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="d-inline"><?= htmlspecialchars($project['name']) ?></h1>
        <span class="badge bg-secondary ms-2">Kanban Board</span>
    </div>
    <div>
        <a href="/projects/<?= $project['id'] ?>" class="btn btn-outline-secondary me-2">
            <i class="bi bi-list-ul"></i> List View
        </a>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createIssueModal">
            <i class="bi bi-plus-lg"></i> New Issue
        </button>
    </div>
</div>

<div class="row flex-nowrap overflow-auto pb-4" style="min-height: 80vh;">
    <?php foreach ($kanbanData as $columnName => $columnIssues): ?>
    <div class="col-md-3" style="min-width: 300px;">
        <div class="card bg-light h-100 border-0">
            <div class="card-header bg-white border-bottom-0 fw-bold sticky-top">
                <?= htmlspecialchars($columnName) ?>
                <span class="badge bg-secondary rounded-pill float-end"><?= count($columnIssues) ?></span>
            </div>
            <div class="card-body p-2 kanban-column" data-status="<?= htmlspecialchars($columnName) ?>" id="col-<?= md5($columnName) ?>">
                <?php foreach ($columnIssues as $issue): ?>
                <div class="card mb-2 shadow-sm kanban-card" data-id="<?= $issue['id'] ?>">
                    <div class="card-body p-3">
                        <div class="mb-2">
                            <?php if (($issue['type'] ?? 'Bug') === 'Bug'): ?>
                                <span class="badge bg-danger rounded-pill" style="font-size: 0.7em;">Bug</span>
                            <?php else: ?>
                                <span class="badge bg-info text-dark rounded-pill" style="font-size: 0.7em;">Feature</span>
                            <?php endif; ?>
                        </div>
                        <h6 class="card-title">
                            <a href="/issues/<?= $issue['id'] ?>" class="text-decoration-none text-dark"><?= htmlspecialchars($issue['title']) ?></a>
                        </h6>
                        <div class="description-preview">
                            <?= strip_tags($issue['description']) ?>
                        </div>
                        <div class="d-flex justify-content-between align-items-center mt-3">
                            <div>
                                <small class="text-muted me-2">#<?= $issue['id'] ?></small>
                                <small class="text-muted"><i class="bi bi-calendar3"></i> <?= date('M j', strtotime($issue['created_at'])) ?></small>
                            </div>
                            <?php if ($issue['assigned_to_name']): ?>
                                <span class="badge rounded-pill bg-white text-dark border" title="Assigned to <?= htmlspecialchars($issue['assigned_to_name']) ?>">
                                    <?= substr(htmlspecialchars($issue['assigned_to_name']), 0, 1) ?>
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- Create Issue Modal -->
<?php require 'create_modal.php'; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var columns = document.querySelectorAll('.kanban-column');
    
    columns.forEach(function(col) {
        new Sortable(col, {
            group: 'kanban',
            animation: 150,
            onEnd: function (evt) {
                var itemEl = evt.item;
                var newStatus = evt.to.getAttribute('data-status');
                var issueId = itemEl.getAttribute('data-id');
                
                // Update status via AJAX
                fetch('/issues/update_status', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ issue_id: issueId, status: newStatus })
                });
            }
        });
    });
});
</script>

<?php require __DIR__ . '/../footer.php'; ?>
