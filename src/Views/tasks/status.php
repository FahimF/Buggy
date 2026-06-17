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
.completed-task-card {
    opacity: 0.7;
    background-color: #f8f9fa;
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
                                        <h6 class="m-0 task-title-container">
                                            <a href="/tasks/<?= $task['id'] ?>" class="text-decoration-none text-dark fw-bold task-title-link"><?= htmlspecialchars($task['title']) ?></a>
                                        </h6>
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
                                        <h6 class="m-0">
                                            <a href="/tasks/<?= $task['id'] ?>" class="text-decoration-none text-dark fw-bold"><?= htmlspecialchars($task['title']) ?></a>
                                        </h6>
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
                card.classList.add('completed-task-card'); // CSS overlay or opacity hint
                this.disabled = true;
                
                function completeTaskSuccess() {
                    // Update header counts
                    updateCounts();
                    // Fade out and remove task card after 3 seconds
                    setTimeout(function() {
                        card.style.transition = 'all 0.5s ease';
                        card.style.opacity = '0';
                        card.style.transform = 'translateY(10px)';
                        setTimeout(function() {
                            card.remove();
                            updateCounts();
                        }, 500);
                    }, 3000);
                }

                function revertCompleteTask(chkEl) {
                    titleLink.classList.remove('completed-task');
                    card.classList.remove('completed-task-card');
                    chkEl.disabled = false;
                    chkEl.checked = false;
                }

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
                                    revertCompleteTask(this);
                                } else {
                                    completeTaskSuccess();
                                }
                            });
                        } else {
                            revertCompleteTask(this);
                        }
                    } else if (!data.success) {
                        revertCompleteTask(this);
                    } else {
                        completeTaskSuccess();
                    }
                })
                .catch(err => {
                    revertCompleteTask(this);
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

    // Track currently hovered status card during drag
    var hoveredCard = null;

    // We can use standard HTML5 drag and drop events on card elements for nesting,
    // while SortableJS uses the handle (bi-grip-vertical) for reordering / status moves.
    document.querySelectorAll('.status-card').forEach(function(card) {
        // Set card draggable for nesting (independent of Sortable's handle drag)
        card.setAttribute('draggable', 'true');
        
        card.addEventListener('dragstart', function(e) {
            // Only initiate nesting drag if we are NOT dragging by the handle
            if (e.target.closest('.bi-grip-vertical')) {
                // Let SortableJS handle it, cancel standard drag data
                return;
            }
            e.dataTransfer.setData('text/plain', this.getAttribute('data-id'));
            e.dataTransfer.effectAllowed = 'copyMove';
            this.classList.add('dragging-for-nest');
        });

        card.addEventListener('dragend', function(e) {
            this.classList.remove('dragging-for-nest');
            document.querySelectorAll('.status-card').forEach(c => c.style.border = '');
            hoveredCard = null;
        });

        card.addEventListener('dragover', function(e) {
            var draggingEl = document.querySelector('.dragging-for-nest');
            if (draggingEl && draggingEl !== this) {
                e.preventDefault(); // Allow drop
                this.style.border = '2px dashed #0d6efd';
                hoveredCard = this;
            }
        });

        card.addEventListener('dragleave', function(e) {
            this.style.border = '';
            if (hoveredCard === this) {
                hoveredCard = null;
            }
        });

        card.addEventListener('drop', function(e) {
            e.preventDefault();
            this.style.border = '';
            var sourceId = e.dataTransfer.getData('text/plain');
            var destId = this.getAttribute('data-id');
            
            if (sourceId && destId && sourceId !== destId) {
                if (confirm('Are you sure you want to make this task a sub-task of "' + this.querySelector('a').textContent.trim() + '"?')) {
                    fetch('/tasks/nest', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ source_id: sourceId, dest_id: destId })
                    })
                    .then(res => res.json())
                    .then(data => {
                        if (data.success) {
                            window.location.reload();
                        } else {
                            alert(data.error || 'Failed to make task a sub-task.');
                            window.location.reload();
                        }
                    })
                    .catch(() => {
                        window.location.reload();
                    });
                }
            }
        });
    });

    [ipList, uaList].forEach(function(listEl) {
        new Sortable(listEl, {
            group: 'status-group',
            animation: 150,
            handle: '.bi-grip-vertical',
            onEnd: function (evt) {
                // Remove highlight style on end
                document.querySelectorAll('.status-card').forEach(c => c.style.border = '');

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
