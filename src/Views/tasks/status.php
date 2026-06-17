<?php require __DIR__ . '/../header.php'; ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <?php 
        $totalTasks = count($statusData['In Progress']) + count($statusData['Unassigned']);
        ?>
        <h1 class="d-inline"><?= htmlspecialchars($project['name']) ?> (<?= $totalTasks ?>)</h1>
        <span class="badge bg-secondary ms-2">Status View</span>
    </div>
    <div>
        <div class="dropdown d-inline-block me-2">
            <button class="btn btn-outline-secondary dropdown-toggle" type="button" id="viewModeDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                <i class="bi bi-eye"></i> Status View
            </button>
            <ul class="dropdown-menu" aria-labelledby="viewModeDropdown">
                <li><a class="dropdown-item" href="/projects/<?= $project['id'] ?>?view=list">List View</a></li>
                <li><a class="dropdown-item" href="/projects/<?= $project['id'] ?>/kanban">Kanban View</a></li>
                <li><a class="dropdown-item active" href="/projects/<?= $project['id'] ?>/status">Status View</a></li>
            </ul>
        </div>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createTaskModal">
            <i class="bi bi-plus-lg"></i> New Task
        </button>
    </div>
</div>

<style>
.completed-task {
    text-decoration: line-through;
    opacity: 0.6;
}
.status-list {
    min-height: 100px;
    background-color: #f8f9fa;
    border: 1px dashed #dee2e6;
    border-radius: 0.375rem;
    padding: 10px;
}
.status-card {
    cursor: grab;
    transition: transform 0.2s, box-shadow 0.2s;
}
.status-card:active {
    cursor: grabbing;
}
.status-card.sortable-ghost {
    opacity: 0.4;
    background-color: #e9ecef;
}
</style>

<div class="row">
    <!-- In Progress Section -->
    <div class="col-12 mb-4">
        <div class="card border-primary">
            <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="bi bi-play-circle-fill me-2"></i>In Progress</h5>
                <span class="badge bg-white text-primary rounded-pill" id="in-progress-count"><?= count($statusData['In Progress']) ?></span>
            </div>
            <div class="card-body">
                <div class="status-list d-flex flex-column gap-2" id="in-progress-list" data-status="In Progress">
                    <?php foreach ($statusData['In Progress'] as $task): ?>
                        <div class="card status-card shadow-sm" data-id="<?= $task['id'] ?>">
                            <div class="card-body p-3 d-flex align-items-center justify-content-between">
                                <div class="d-flex align-items-center gap-3 flex-grow-1">
                                    <div class="form-check m-0">
                                        <input class="form-check-input task-complete-checkbox" type="checkbox" style="transform: scale(1.2);" data-id="<?= $task['id'] ?>">
                                    </div>
                                    <div class="flex-grow-1">
                                        <h6 class="mb-1 task-title-container">
                                            <a href="/tasks/<?= $task['id'] ?>" class="text-decoration-none text-dark fw-bold task-title-link"><?= htmlspecialchars($task['title']) ?></a>
                                        </h6>
                                        <div class="text-muted small">
                                            <span class="badge <?= getPriorityBadgeClass($task['priority'] ?? 'Medium') ?>"><?= htmlspecialchars($task['priority'] ?? 'Medium') ?></span>
                                            <span class="badge bg-secondary"><?= htmlspecialchars($task['type'] ?? 'Bug') ?></span>
                                            <?php if ($task['assigned_to_name']): ?>
                                                <span class="ms-2"><i class="bi bi-person"></i> <?= htmlspecialchars($task['assigned_to_name']) ?></span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                                <div class="d-flex align-items-center gap-2">
                                    <i class="bi bi-grip-vertical text-muted fs-4"></i>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Unassigned Section -->
    <div class="col-12 mb-4">
        <div class="card border-secondary">
            <div class="card-header bg-secondary text-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="bi bi-question-circle-fill me-2"></i>Unassigned</h5>
                <span class="badge bg-light text-dark rounded-pill" id="unassigned-count"><?= count($statusData['Unassigned']) ?></span>
            </div>
            <div class="card-body">
                <div class="status-list d-flex flex-column gap-2" id="unassigned-list" data-status="Unassigned">
                    <?php foreach ($statusData['Unassigned'] as $task): ?>
                        <div class="card status-card shadow-sm" data-id="<?= $task['id'] ?>">
                            <div class="card-body p-3 d-flex align-items-center justify-content-between">
                                <div class="d-flex align-items-center gap-3 flex-grow-1">
                                    <div class="flex-grow-1">
                                        <h6 class="mb-1">
                                            <a href="/tasks/<?= $task['id'] ?>" class="text-decoration-none text-dark fw-bold"><?= htmlspecialchars($task['title']) ?></a>
                                        </h6>
                                        <div class="text-muted small">
                                            <span class="badge <?= getPriorityBadgeClass($task['priority'] ?? 'Medium') ?>"><?= htmlspecialchars($task['priority'] ?? 'Medium') ?></span>
                                            <span class="badge bg-secondary"><?= htmlspecialchars($task['type'] ?? 'Bug') ?></span>
                                        </div>
                                    </div>
                                </div>
                                <div class="d-flex align-items-center gap-2">
                                    <i class="bi bi-grip-vertical text-muted fs-4"></i>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Create Task Modal -->
<?php require 'create_modal.php'; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Checkbox logic for In Progress tasks
    document.querySelectorAll('.task-complete-checkbox').forEach(function(checkbox) {
        checkbox.addEventListener('change', function() {
            if (this.checked) {
                var taskId = this.getAttribute('data-id');
                var card = this.closest('.status-card');
                var titleLink = card.querySelector('.task-title-link');
                
                // Add strikethrough styling immediately
                titleLink.classList.add('completed-task');
                this.disabled = true; // Disable to prevent double requests
                
                // Update status via AJAX
                fetch('/tasks/update_status', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ issue_id: taskId, status: 'Completed' })
                })
                .then(res => res.json())
                .then(data => {
                    if (data.error === 'incomplete_subtasks') {
                        if (confirm('This task has ' + data.count + ' incomplete sub-task(s). Would you like to mark all sub-tasks as completed and complete the task?')) {
                            fetch('/tasks/update_status', {
                                 method: 'POST',
                                 headers: { 'Content-Type': 'application/json' },
                                 body: JSON.stringify({ issue_id: taskId, status: 'Completed', force_complete_subtasks: true })
                            })
                            .then(res => res.json())
                            .then(retryData => {
                                if (!retryData.success) {
                                    // Revert on error
                                    titleLink.classList.remove('completed-task');
                                    this.disabled = false;
                                    this.checked = false;
                                } else {
                                    // Update header counts
                                    updateCounts();
                                }
                            });
                        } else {
                            // Revert checkbox state
                            titleLink.classList.remove('completed-task');
                            this.disabled = false;
                            this.checked = false;
                        }
                    } else if (!data.success) {
                        // Revert on error
                        titleLink.classList.remove('completed-task');
                        this.disabled = false;
                        this.checked = false;
                    } else {
                        // Success - update header counts
                        updateCounts();
                    }
                })
                .catch(err => {
                    titleLink.classList.remove('completed-task');
                    this.disabled = false;
                    this.checked = false;
                });
            }
        });
    });

    // Helper to update badges & counts dynamically
    function updateCounts() {
        var ipCount = document.querySelectorAll('#in-progress-list .status-card:not(.completed-task)').length;
        // Adjust for any completed tasks still on screen
        var completedOnScreen = document.querySelectorAll('#in-progress-list .completed-task').length;
        document.getElementById('in-progress-count').textContent = document.querySelectorAll('#in-progress-list .status-card').length - completedOnScreen;
        document.getElementById('unassigned-count').textContent = document.querySelectorAll('#unassigned-list .status-card').length;
    }

    // Drag and drop sorting between In Progress and Unassigned lists
    var ipList = document.getElementById('in-progress-list');
    var uaList = document.getElementById('unassigned-list');

    [ipList, uaList].forEach(function(listEl) {
        new Sortable(listEl, {
            group: 'status-group',
            animation: 150,
            handle: '.bi-grip-vertical',
            onEnd: function (evt) {
                var itemEl = evt.item;
                var newStatus = evt.to.getAttribute('data-status');
                var taskId = itemEl.getAttribute('data-id');
                
                // If dragged, status changes.
                // Call status update API
                fetch('/tasks/update_status', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ issue_id: taskId, status: newStatus })
                })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        // Refresh to reflect proper assignee/checkbox adjustments from server logic dynamically if necessary,
                        // or just reload to ensure correct layout template rendering.
                        window.location.reload();
                    } else {
                        window.location.reload();
                    }
                })
                .catch(() => {
                    window.location.reload();
                });
            }
        });
    });
});
</script>

<?php require __DIR__ . '/../footer.php'; ?>
