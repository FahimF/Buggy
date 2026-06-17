<?php require __DIR__ . '/../header.php'; ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <?php 
        $totalTasks = array_reduce($kanbanData, function($carry, $column) {
            return $carry + count($column);
        }, 0);
        ?>
        <h1 class="d-inline"><?= htmlspecialchars($project['name']) ?> (<?= $totalTasks ?>)</h1>
        <span class="badge bg-secondary ms-2">Kanban Board</span>
    </div>
    <div>
        <div class="dropdown d-inline-block me-2">
            <button class="btn btn-outline-secondary dropdown-toggle" type="button" id="viewModeDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                <i class="bi bi-eye"></i> Kanban View
            </button>
            <ul class="dropdown-menu" aria-labelledby="viewModeDropdown" style="z-index: 1060;">
                <li><a class="dropdown-item" href="/projects/<?= $project['id'] ?>?view=list">List View</a></li>
                <li><a class="dropdown-item active" href="/projects/<?= $project['id'] ?>/kanban">Kanban View</a></li>
                <li><a class="dropdown-item" href="/projects/<?= $project['id'] ?>/status">Status View</a></li>
            </ul>
        </div>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createTaskModal">
            <i class="bi bi-plus-lg"></i> New Task
        </button>
    </div>
</div>

<div class="row flex-nowrap overflow-auto pb-4" style="min-height: 80vh;">
    <?php foreach ($kanbanData as $columnName => $columnTasks): ?>
    <div class="col-md-3" style="min-width: 300px;">
        <div class="card bg-light h-100 border-0">
            <div class="card-header bg-white border-bottom-0 fw-bold sticky-top">
                <?= htmlspecialchars($columnName) ?>
                <span class="badge <?= getStatusBadgeClass($columnName) ?> rounded-pill float-end"><?= count($columnTasks) ?></span>
            </div>
            <div class="card-body p-2 kanban-column" data-status="<?= htmlspecialchars($columnName) ?>" id="col-<?= md5($columnName) ?>">
                <?php foreach ($columnTasks as $task): ?>
                <div class="card mb-2 shadow-sm kanban-card" data-id="<?= $task['id'] ?>">
                    <div class="card-body p-3">
                        <div class="mb-2">
                            <?php if (($task['type'] ?? 'Bug') === 'Bug'): ?>
                                <span class="badge bg-danger rounded-pill" style="font-size: 0.7em;">Bug</span>
                            <?php else: ?>
                                <span class="badge bg-info text-dark rounded-pill" style="font-size: 0.7em;">Feature</span>
                            <?php endif; ?>
                            <span class="badge <?= getPriorityBadgeClass($task['priority'] ?? 'Medium') ?> rounded-pill" style="font-size: 0.7em;">
                                <?= htmlspecialchars($task['priority'] ?? 'Medium') ?>
                            </span>
                        </div>
                        <h6 class="card-title">
                            <a href="/tasks/<?= $task['id'] ?>" class="text-decoration-none text-dark"><?= htmlspecialchars($task['title']) ?></a>
                        </h6>
                        <div class="description-preview">
                            <?= strip_tags($task['description']) ?>
                        </div>
                        <div class="d-flex justify-content-between align-items-center mt-3">
                            <div>
                                <small class="text-muted me-2">#<?= $task['id'] ?></small>
                                <small class="text-muted"><i class="bi bi-calendar3"></i> <?= date('M j', strtotime($task['created_at'])) ?></small>
                            </div>
                            <?php if ($task['assigned_to_name']): ?>
                                <span class="badge rounded-pill bg-white text-dark border" title="Assigned to <?= htmlspecialchars($task['assigned_to_name']) ?>">
                                    <?= substr(htmlspecialchars($task['assigned_to_name']), 0, 1) ?>
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

<!-- Create Task Modal -->
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
                var taskId = itemEl.getAttribute('data-id');
                
                var order = Array.from(evt.to.children).map(card => card.getAttribute('data-id'));
                // Update status via AJAX
                fetch('/tasks/update_status', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ issue_id: taskId, status: newStatus, order: order })
                })
                .then(res => res.json())
                .then(data => {
                    if (data.error === 'incomplete_subtasks') {
                        if (confirm('This task has ' + data.count + ' incomplete sub-task(s). Would you like to mark all sub-tasks as completed and complete the task?')) {
                            fetch('/tasks/update_status', {
                                 method: 'POST',
                                 headers: { 'Content-Type': 'application/json' },
                                 body: JSON.stringify({ issue_id: taskId, status: newStatus, force_complete_subtasks: true, order: order })
                            })
                            .then(res => res.json())
                            .then(retryData => {
                                if (retryData.success) {
                                    window.location.reload();
                                }
                            });
                        } else {
                            window.location.reload();
                        }
                    } else if (data.success) {
                        // Smooth update
                    }
                });
            }
        });
    });
});
</script>

<?php require __DIR__ . '/../footer.php'; ?>
