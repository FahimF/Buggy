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
        <div class="form-check form-switch d-inline-block align-middle me-3 mb-0">
            <input class="form-check-input" type="checkbox" id="hideDescriptionsCheck">
            <label class="form-check-label" for="hideDescriptionsCheck">Hide Descriptions</label>
        </div>
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
                <?php if (in_array($columnName, ['Completed', 'WND']) && count($columnTasks) > 0): ?>
                    <form action="/projects/<?= $project['id'] ?>/archive-status" method="POST" class="d-inline float-end me-2" onsubmit="return confirm('Are you sure you want to archive all tasks in the <?= htmlspecialchars($columnName) ?> column?');">
                        <input type="hidden" name="status" value="<?= htmlspecialchars($columnName) ?>">
                        <button type="submit" class="btn btn-xs btn-outline-secondary py-0 px-1" style="font-size: 0.75rem;">
                            <i class="bi bi-archive"></i> Archive All
                        </button>
                    </form>
                <?php endif; ?>
            </div>
            <div class="card-body p-2 kanban-column" data-status="<?= htmlspecialchars($columnName) ?>" id="col-<?= md5($columnName) ?>">
                <?php foreach ($columnTasks as $task): ?>
                <div class="card mb-2 shadow-sm kanban-card" data-id="<?= $task['id'] ?>">
                    <div class="card-body p-3">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <div>
                                <?php if (($task['type'] ?? 'Bug') === 'Bug'): ?>
                                    <span class="badge bg-danger rounded-pill" style="font-size: 0.7em;">Bug</span>
                                <?php else: ?>
                                    <span class="badge bg-info text-dark rounded-pill" style="font-size: 0.7em;">Feature</span>
                                <?php endif; ?>
                                <span class="badge <?= getPriorityBadgeClass($task['priority'] ?? 'Medium') ?> rounded-pill" style="font-size: 0.7em;">
                                    <?= htmlspecialchars($task['priority'] ?? 'Medium') ?>
                                </span>
                            </div>
                            <?php if (in_array($task['status'], ['Completed', 'WND'])): ?>
                                <form action="/tasks/<?= $task['id'] ?>/archive" method="POST" class="d-inline mb-0" onsubmit="return confirm('Are you sure you want to archive this task?');">
                                    <input type="hidden" name="redirect_to" value="/projects/<?= $project['id'] ?>/kanban">
                                    <button type="submit" class="btn btn-link text-muted p-0 border-0 lh-1" title="Archive Task">
                                        <i class="bi bi-archive" style="font-size: 0.85rem;"></i>
                                    </button>
                                </form>
                            <?php endif; ?>
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

    // Hide Descriptions toggle functionality
    const hideDescCheck = document.getElementById('hideDescriptionsCheck');
    const updateDescriptionVisibility = () => {
        const hide = hideDescCheck.checked;
        document.querySelectorAll('.description-preview').forEach(desc => {
            desc.style.display = hide ? 'none' : 'block';
        });
    };

    if (hideDescCheck) {
        const hideDescriptionsVal = localStorage.getItem('kanban_hide_descriptions') === 'true';
        hideDescCheck.checked = hideDescriptionsVal;
        updateDescriptionVisibility();

        hideDescCheck.addEventListener('change', function() {
            localStorage.setItem('kanban_hide_descriptions', this.checked);
            updateDescriptionVisibility();
        });
    }
});
</script>

<?php require __DIR__ . '/../footer.php'; ?>
