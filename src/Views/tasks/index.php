<?php require __DIR__ . '/../header.php'; ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="d-inline"><?= htmlspecialchars($project['name']) ?> (<?= count($tasks) ?>)</h1>
        <span class="badge bg-secondary ms-2">List View</span>
    </div>
    <div>
        <div class="dropdown d-inline-block me-2">
            <button class="btn btn-outline-secondary dropdown-toggle" type="button" id="viewModeDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                <i class="bi bi-eye"></i> List View
            </button>
            <ul class="dropdown-menu" aria-labelledby="viewModeDropdown">
                <li><a class="dropdown-item active" href="/projects/<?= $project['id'] ?>?view=list">List View</a></li>
                <li><a class="dropdown-item" href="/projects/<?= $project['id'] ?>/kanban">Kanban View</a></li>
                <li><a class="dropdown-item" href="/projects/<?= $project['id'] ?>/status">Status View</a></li>
            </ul>
        </div>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createTaskModal">
            <i class="bi bi-plus-lg"></i> New Task
        </button>
    </div>
</div><!-- Filters Form -->
<div class="card mb-3">
    <div class="card-body py-2">
        <form method="GET" class="row g-2 align-items-end">
            <!-- Maintain sorting parameters -->
            <?php if (isset($sort)): ?><input type="hidden" name="sort" value="<?= htmlspecialchars($sort) ?>"><?php endif; ?>
            <?php if (isset($dir)): ?><input type="hidden" name="dir" value="<?= htmlspecialchars($dir) ?>"><?php endif; ?>
            <?php if (isset($_GET['hide_completed'])): ?><input type="hidden" name="hide_completed" value="<?= htmlspecialchars($_GET['hide_completed']) ?>"><?php endif; ?>
            
            <div class="col-md-2">
                <label class="form-label small fw-bold mb-1">Filter by Status</label>
                <select name="status_filter" class="form-select form-select-sm">
                    <option value="">-- All Statuses --</option>
                    <?php 
                    $statuses = ['Unassigned', 'In Progress', 'Ready for QA', 'Completed', 'WND'];
                    foreach ($statuses as $statusOpt): 
                    ?>
                        <option value="<?= $statusOpt ?>" <?= (isset($statusFilter) && $statusFilter === $statusOpt) ? 'selected' : '' ?>><?= $statusOpt ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-2">
                <label class="form-label small fw-bold mb-1">Filter by Priority</label>
                <select name="priority_filter" class="form-select form-select-sm">
                    <option value="">-- All Priorities --</option>
                    <?php 
                    $priorities = ['High', 'Medium', 'Low'];
                    foreach ($priorities as $priorityOpt): 
                    ?>
                        <option value="<?= $priorityOpt ?>" <?= (isset($priorityFilter) && $priorityFilter === $priorityOpt) ? 'selected' : '' ?>><?= $priorityOpt ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-2">
                <label class="form-label small fw-bold mb-1">Filter by Type</label>
                <select name="type_filter" class="form-select form-select-sm">
                    <option value="">-- All Types --</option>
                    <?php 
                    $types = ['Bug', 'Feature', 'Improvement', 'Task'];
                    foreach ($types as $typeOpt): 
                    ?>
                        <option value="<?= $typeOpt ?>" <?= (isset($typeFilter) && $typeFilter === $typeOpt) ? 'selected' : '' ?>><?= $typeOpt ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="col-md-2">
                <label class="form-label small fw-bold mb-1">Filter by Author</label>
                <select name="author_filter" class="form-select form-select-sm">
                    <option value="">-- All Authors --</option>
                    <?php foreach ($authors as $authorOpt): ?>
                        <option value="<?= $authorOpt['id'] ?>" <?= (isset($authorFilter) && $authorFilter == $authorOpt['id']) ? 'selected' : '' ?>><?= htmlspecialchars($authorOpt['username']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="col-md-2">
                <label class="form-label small fw-bold mb-1">Filter by Assignee</label>
                <select name="assignee_filter" class="form-select form-select-sm">
                    <option value="">-- All Assignees --</option>
                    <option value="unassigned" <?= (isset($assigneeFilter) && $assigneeFilter === 'unassigned') ? 'selected' : '' ?>>-- Unassigned --</option>
                    <?php foreach ($assignees as $assigneeOpt): ?>
                        <option value="<?= $assigneeOpt['id'] ?>" <?= (isset($assigneeFilter) && $assigneeFilter == $assigneeOpt['id']) ? 'selected' : '' ?>><?= htmlspecialchars($assigneeOpt['username']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="col-md-2 d-flex gap-2">
                <button type="submit" class="btn btn-sm btn-primary flex-grow-1"><i class="bi bi-filter"></i> Filter</button>
                <a href="/projects/<?= $project['id'] ?>" class="btn btn-sm btn-outline-secondary">Reset</a>
            </div>
        </form>
    </div>
</div>

<form action="/tasks/batch-action" method="POST" id="batchForm">
    <input type="hidden" name="project_id" value="<?= $project['id'] ?>">
    <input type="hidden" name="batch_action" id="batchActionInput" value="">
    <input type="hidden" name="status_value" id="batchStatusValueInput" value="">
    
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <div class="d-flex align-items-center gap-2">
                <div class="btn-group">
                    <?php 
                    $baseParams = $_GET;
                    $hideCompletedParam = isset($hideCompleted) && $hideCompleted ? '&hide_completed=1' : '&hide_completed=0';
                    
                    // Maintain current filters in list sorting links
                    $filtersParam = '';
                    if (!empty($statusFilter)) $filtersParam .= '&status_filter=' . urlencode($statusFilter);
                    if (!empty($authorFilter)) $filtersParam .= '&author_filter=' . urlencode($authorFilter);
                    if (!empty($assigneeFilter)) $filtersParam .= '&assignee_filter=' . urlencode($assigneeFilter);
                    if (!empty($priorityFilter)) $filtersParam .= '&priority_filter=' . urlencode($priorityFilter);
                    if (!empty($typeFilter)) $filtersParam .= '&type_filter=' . urlencode($typeFilter);

                    function sortLink($col, $label, $currentSort, $currentDir, $hideCompletedParam, $filtersParam) {
                        $isActive = $currentSort === $col;
                        $newDir = ($isActive && $currentDir === 'ASC') ? 'DESC' : 'ASC';
                        $icon = '';
                        if ($isActive) {
                            $icon = $currentDir === 'ASC' ? ' <i class="bi bi-caret-up-fill"></i>' : ' <i class="bi bi-caret-down-fill"></i>';
                        }
                        return '<a href="?sort=' . $col . '&dir=' . $newDir . $hideCompletedParam . $filtersParam . '" class="text-decoration-none text-dark">' . $label . $icon . '</a>';
                    }
                    ?>
                    <a href="?sort=sort_order&dir=ASC<?= $hideCompletedParam . $filtersParam ?>" class="btn btn-sm btn-outline-secondary">Custom Order</a>
                </div>

                <div class="d-flex gap-1 align-items-center ms-2">
                    <?php 
                    $statusCounts = [];
                    foreach ($tasks as $t) {
                        $status = $t['status'];
                        $statusCounts[$status] = ($statusCounts[$status] ?? 0) + 1;
                    }
                    $statusOrder = ['Unassigned', 'In Progress', 'Ready for QA', 'Completed', 'WND'];
                    foreach ($statusOrder as $st): 
                        if (isset($statusCounts[$st]) && $statusCounts[$st] > 0): 
                    ?>
                        <span class="badge <?= getStatusBadgeClass($st) ?> rounded-pill" style="font-size: 0.8rem; padding: 0.35em 0.65em;">
                            <?= $st ?>: <?= $statusCounts[$st] ?>
                        </span>
                    <?php 
                        endif;
                    endforeach; 
                    ?>
                </div>
                
                <div class="dropdown ms-2" id="batchActionDropdown" style="display: none;">
                    <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" id="batchActionBtn" data-bs-toggle="dropdown" aria-expanded="false">
                        Batch Actions
                    </button>
                    <ul class="dropdown-menu" aria-labelledby="batchActionBtn">
                        <li><button type="button" class="dropdown-item" onclick="openBatchStatusModal()"><i class="bi bi-arrow-repeat"></i> Change Status</button></li>
                        <li><button type="button" class="dropdown-item" onclick="submitBatch('archive')"><i class="bi bi-archive"></i> Archive Selected</button></li>
                        <li><button type="button" class="dropdown-item text-danger" onclick="submitBatch('delete')"><i class="bi bi-trash"></i> Delete Selected</button></li>
                    </ul>
                </div>
            </div>
            
            <div class="form-check form-switch m-0">
                <input class="form-check-input" type="checkbox" id="hideCompletedCheck" <?= isset($hideCompleted) && $hideCompleted ? 'checked' : '' ?> onchange="window.location.href='?sort=<?= $sort ?>&dir=<?= $dir ?>&hide_completed=' + (this.checked ? '1' : '0') + '<?= $filtersParam ?>'">
                <label class="form-check-label" for="hideCompletedCheck">Hide Completed</label>
            </div>
        </div>
        
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th style="width: 40px;" class="text-center"><input type="checkbox" id="selectAllCheckbox"></th>
                            <th style="width: 40px;"></th>
                            <th><?= sortLink('title', 'Title', $sort, $dir, $hideCompletedParam, $filtersParam) ?></th>
                            <th><?= sortLink('priority', 'Priority', $sort, $dir, $hideCompletedParam, $filtersParam) ?></th>
                            <th><?= sortLink('type', 'Type', $sort, $dir, $hideCompletedParam, $filtersParam) ?></th>
                            <th><?= sortLink('status', 'Status', $sort, $dir, $hideCompletedParam, $filtersParam) ?></th>
                            <th>Comments</th>
                            <th><?= sortLink('created_at', 'Created', $sort, $dir, $hideCompletedParam, $filtersParam) ?></th>
                            <th class="text-end" style="width: 100px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="taskList">
                        <?php foreach ($tasks as $task): ?>
                        <tr data-id="<?= $task['id'] ?>">
                            <td class="text-center">
                                <input type="checkbox" name="task_ids[]" value="<?= $task['id'] ?>" class="task-checkbox">
                            </td>
                            <td class="text-center">
                                <i class="bi bi-grip-vertical handle d-block text-muted" style="cursor: move;"></i>
                            </td>
                            <td>
                                <a href="/tasks/<?= $task['id'] ?>" class="text-decoration-none fw-bold">
                                    <?= htmlspecialchars($task['title']) ?>
                                </a>
                                <div class="description-preview">
                                    <?= strip_tags($task['description']) ?>
                                </div>
                            </td>
                            <td>
                                <span class="badge <?= getPriorityBadgeClass($task['priority'] ?? 'Medium') ?>"><?= htmlspecialchars($task['priority'] ?? 'Medium') ?></span>
                            </td>
                            <td>
                                <?php if (($task['type'] ?? 'Bug') === 'Bug'): ?>
                                    <span class="badge bg-danger">Bug</span>
                                <?php else: ?>
                                    <span class="badge bg-info text-dark">Feature</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <select class="form-select form-select-sm inline-status-select fw-bold py-0 px-2 text-center text-white" 
                                        data-task-id="<?= $task['id'] ?>"
                                        style="width: auto; display: inline-block; font-size: 0.85rem; border-radius: 5px;">
                                    <?php 
                                    $statuses = ['Unassigned', 'In Progress', 'Ready for QA', 'Completed', 'WND'];
                                    foreach ($statuses as $st): 
                                    ?>
                                        <option value="<?= $st ?>" <?= $task['status'] === $st ? 'selected' : '' ?>><?= $st ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <td>
                                <?php if ($task['comment_count'] > 0): ?>
                                    <span class="badge bg-secondary rounded-pill"><?= $task['comment_count'] ?></span>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                            <td class="small text-muted"><?= date('M j', strtotime($task['created_at'])) ?></td>
                            <td class="text-end">
                                <div class="d-flex justify-content-end gap-1">
                                    <a href="/tasks/<?= $task['id'] ?>/edit" class="btn btn-sm btn-outline-primary py-1 px-2" title="Edit">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                    <form action="/tasks/<?= $task['id'] ?>/delete" method="POST" class="d-inline mb-0" onsubmit="return confirm('Are you sure you want to delete this task?');">
                                        <button type="submit" class="btn btn-sm btn-outline-danger py-1 px-2" title="Delete">
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
</form>

<!-- Batch Change Status Modal -->
<div class="modal fade" id="batchStatusModal" tabindex="-1" aria-labelledby="batchStatusModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content shadow-lg border-0">
            <div class="modal-header bg-dark text-white">
                <h5 class="modal-title" id="batchStatusModalLabel">Change Status</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label fw-bold">Select New Status</label>
                    <select id="batchStatusSelect" class="form-select">
                        <option value="Unassigned">Unassigned</option>
                        <option value="In Progress">In Progress</option>
                        <option value="Ready for QA">Ready for QA</option>
                        <option value="Completed">Completed</option>
                        <option value="WND">WND</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary btn-sm" onclick="executeBatchStatus()">Apply</button>
            </div>
        </div>
    </div>
</div>

<!-- Create Task Modal -->
<?php require 'create_modal.php'; ?>

<script>
function openBatchStatusModal() {
    var myModal = new bootstrap.Modal(document.getElementById('batchStatusModal'));
    myModal.show();
}

function executeBatchStatus() {
    const selectedStatus = document.getElementById('batchStatusSelect').value;
    const checkedCount = document.querySelectorAll('.task-checkbox:checked').length;
    if (confirm(`Are you sure you want to change the status of ${checkedCount} task(s) to "${selectedStatus}"?`)) {
        document.getElementById('batchActionInput').value = 'status';
        document.getElementById('batchStatusValueInput').value = selectedStatus;
        document.getElementById('batchForm').submit();
    }
}

function submitBatch(action) {
    const checkedCount = document.querySelectorAll('.task-checkbox:checked').length;
    if (checkedCount === 0) return;

    let confirmMsg = '';
    if (action === 'delete') {
        confirmMsg = `Are you sure you want to permanently delete ${checkedCount} selected task(s)? This action cannot be undone.`;
    } else if (action === 'archive') {
        confirmMsg = `Are you sure you want to archive ${checkedCount} selected task(s)?`;
    } else {
        confirmMsg = `Are you sure you want to perform this action on ${checkedCount} selected task(s)?`;
    }

    if (!confirm(confirmMsg)) {
        return;
    }
    document.getElementById('batchActionInput').value = action;
    document.getElementById('batchForm').submit();
}

document.addEventListener('DOMContentLoaded', function() {
    const selectAll = document.getElementById('selectAllCheckbox');
    const checkboxes = document.querySelectorAll('.task-checkbox');
    const batchDropdown = document.getElementById('batchActionDropdown');

    function toggleBatchButton() {
        const checkedCount = document.querySelectorAll('.task-checkbox:checked').length;
        batchDropdown.style.display = checkedCount > 0 ? 'inline-block' : 'none';
    }

    if (selectAll) {
        selectAll.addEventListener('change', function() {
            checkboxes.forEach(cb => {
                cb.checked = selectAll.checked;
            });
            toggleBatchButton();
        });
    }

    checkboxes.forEach(cb => {
        cb.addEventListener('change', toggleBatchButton);
    });

    // Inline Status Change logic
    document.querySelectorAll('.inline-status-select').forEach(select => {
        select.addEventListener('change', function() {
            const taskId = this.getAttribute('data-task-id');
            const newStatus = this.value;
            const selectEl = this;
            const originalValue = selectEl.getAttribute('data-original-val') || selectEl.value;

            function update(force) {
                fetch('/tasks/update_status', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ issue_id: taskId, status: newStatus, force_complete_subtasks: force })
                })
                .then(res => res.json())
                .then(data => {
                    if (data.error === 'incomplete_subtasks') {
                        if (confirm('This task has ' + data.count + ' incomplete sub-task(s). Would you like to mark all sub-tasks as completed and complete the task?')) {
                            update(true);
                        } else {
                            selectEl.value = originalValue;
                            updateInlineSelectClass(selectEl, originalValue);
                        }
                    } else if (data.success) {
                        selectEl.setAttribute('data-original-val', newStatus);
                        updateInlineSelectClass(selectEl, newStatus);
                    } else {
                        alert('Failed to update status');
                        selectEl.value = originalValue;
                        updateInlineSelectClass(selectEl, originalValue);
                    }
                })
                .catch(err => {
                    alert('An error occurred');
                    selectEl.value = originalValue;
                    updateInlineSelectClass(selectEl, originalValue);
                });
            }
            update(false);
        });
    });

    // Helper to dynamically color the select background based on status
    function updateInlineSelectClass(el, status) {
        el.className = 'form-select form-select-sm inline-status-select fw-bold py-0 px-2 text-center text-white';
        if (status === 'Unassigned') {
            el.classList.add('bg-secondary');
        } else if (status === 'In Progress') {
            el.classList.add('bg-warning', 'text-dark');
            el.classList.remove('text-white');
        } else if (status === 'Ready for QA') {
            el.classList.add('bg-orange');
        } else if (status === 'Completed') {
            el.classList.add('bg-success');
        } else if (status === 'WND') {
            el.classList.add('bg-danger');
        } else {
            el.classList.add('bg-secondary');
        }
    }
    
    // Initial coloring of all inline status selectors
    document.querySelectorAll('.inline-status-select').forEach(select => {
        updateInlineSelectClass(select, select.value);
        select.setAttribute('data-original-val', select.value);
    });

    var el = document.getElementById('taskList');
    var sortable = Sortable.create(el, {
        handle: '.handle',
        animation: 150,
        onEnd: function (evt) {
            var order = [];
            el.querySelectorAll('tr').forEach(function(row) {
                order.push(row.getAttribute('data-id'));
            });
            
            fetch('/tasks/reorder', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ order: order })
            });
        }
    });
});
</script>

<?php require __DIR__ . '/../footer.php'; ?>
