<?php require __DIR__ . '/../header.php'; ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1><?= htmlspecialchars($taskList['title']) ?></h1>
    <div class="d-flex gap-2">
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createTaskModal">
            <i class="bi bi-plus-lg"></i> New Task
        </button>
    </div>
</div>

<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-body">
                <?php if (empty($tasks)): ?>
                    <div class="text-center py-5">
                        <i class="bi bi-clipboard fs-1 text-muted"></i>
                        <p class="text-muted">No tasks yet. Create your first task!</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Title</th>
                                    <th>Description</th>
                                    <th>Assigned To</th>
                                    <th>Priority</th>
                                    <th>Type</th>
                                    <th>Status</th>
                                    <th>Created</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($tasks as $task): ?>
                                <tr>
                                    <td>
                                        <?php if ($task['status'] !== 'incomplete'): ?>
                                            <s><strong><?= htmlspecialchars($task['title']) ?></strong></s>
                                        <?php else: ?>
                                            <strong><?= htmlspecialchars($task['title']) ?></strong>
                                        <?php endif; ?>
                                        <?php if ($task['is_one_time'] == 0): ?>
                                            <span class="badge bg-info ms-1">Recurring</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= htmlspecialchars(substr($task['description'], 0, 50)) . (strlen($task['description']) > 50 ? '...' : '') ?></td>
                                    <td>
                                        <?= $task['assigned_to_name'] ? htmlspecialchars($task['assigned_to_name']) : 'Unassigned' ?>
                                    </td>
                                    <td>
                                        <?php
                                        $priorityClass = '';
                                        switch ($task['priority']) {
                                            case 'High': $priorityClass = 'danger'; break;
                                            case 'Medium': $priorityClass = 'warning'; break;
                                            case 'Low': $priorityClass = 'success'; break;
                                        }
                                        ?>
                                        <span class="badge bg-<?= $priorityClass ?>"><?= htmlspecialchars($task['priority']) ?></span>
                                    </td>
                                    <td>
                                        <?php if ($task['is_one_time'] == 1): ?>
                                            <span class="badge bg-secondary">One-time</span>
                                        <?php else: ?>
                                            <span class="badge bg-info">Recurring (<?= ucfirst($task['recurring_period']) ?>)</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php
                                        $statusClass = '';
                                        switch ($task['status']) {
                                            case 'completed': $statusClass = 'success'; break;
                                            case 'ND': $statusClass = 'secondary'; break;
                                            case 'WND': $statusClass = 'dark'; break;
                                            default: $statusClass = 'primary'; break;
                                        }
                                        ?>
                                        <span class="badge bg-<?= $statusClass ?>"><?= htmlspecialchars($task['status']) ?></span>
                                    </td>
                                    <td><?= date('M j, Y', strtotime($task['created_at'])) ?></td>
                                    <td>
                                        <div class="btn-group">
                                            <?php if ($task['status'] !== 'incomplete' && $task['is_one_time'] == 1): ?>
                                                <!-- Reactivate button for completed/ND/WND one-time tasks -->
                                                <form action="/tasks/update-status" method="post" class="d-inline">
                                                    <input type="hidden" name="id" value="<?= $task['id'] ?>">
                                                    <input type="hidden" name="list_id" value="<?= $taskList['id'] ?>">
                                                    <input type="hidden" name="status" value="incomplete">
                                                    <button type="submit" class="btn btn-sm btn-outline-info" title="Mark Active">
                                                        <i class="bi bi-arrow-repeat"></i>
                                                    </button>
                                                </form>
                                            <?php else: ?>
                                                <button class="btn btn-sm btn-outline-primary edit-task-btn"
                                                    data-id="<?= $task['id'] ?>"
                                                    data-title="<?= htmlspecialchars($task['title']) ?>"
                                                    data-description="<?= htmlspecialchars($task['description']) ?>"
                                                    data-assigned-to-id="<?= $task['assigned_to_id'] ?>"
                                                    data-priority="<?= $task['priority'] ?>"
                                                    data-is-one-time="<?= $task['is_one_time'] ?>"
                                                    data-recurring-period="<?= $task['recurring_period'] ?>"
                                                    data-start-date="<?= $task['start_date'] ?>"
                                                    data-status="<?= $task['status'] ?>"
                                                    title="Edit">
                                                    <i class="bi bi-pencil"></i>
                                                </button>
                                            <?php endif; ?>
                                            <form action="/tasks/delete-task" method="post" class="d-inline" onsubmit="return confirm('Are you sure?');">
                                                <input type="hidden" name="id" value="<?= $task['id'] ?>">
                                                <input type="hidden" name="list_id" value="<?= $taskList['id'] ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete">
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
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Create Task Modal -->
<div class="modal fade" id="createTaskModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form action="/tasks/create-task" method="post">
                <input type="hidden" name="list_id" value="<?= $taskList['id'] ?>">
                <div class="modal-header">
                    <h5 class="modal-title">New Task</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label>Task Title</label>
                                <input type="text" name="title" class="form-control" required>
                            </div>
                            
                            <div class="mb-3">
                                <label>Description</label>
                                <textarea name="description" class="form-control" rows="3"></textarea>
                            </div>
                            
                            <div class="mb-3">
                                <label>Assigned To</label>
                                <select name="assigned_to_id" class="form-select">
                                    <option value="">Select User...</option>
                                    <?php foreach ($users as $user): ?>
                                        <option value="<?= $user['id'] ?>" <?= $user['id'] == Auth::user()['id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($user['username']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <label>Priority</label>
                                <select name="priority" class="form-select">
                                    <option value="Low">Low</option>
                                    <option value="Medium" selected>Medium</option>
                                    <option value="High">High</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="mb-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="is_one_time" id="isOneTime" checked>
                                    <label class="form-check-label" for="isOneTime">
                                        One-time task (uncheck for recurring)
                                    </label>
                                </div>
                            </div>
                            
                            <div class="mb-3 recurring-options" style="display: none;">
                                <label>Recurring Period</label>
                                <select name="recurring_period" class="form-select">
                                    <option value="daily">Daily</option>
                                    <option value="weekly">Weekly</option>
                                    <option value="monthly">Monthly</option>
                                    <option value="yearly">Yearly</option>
                                </select>
                            </div>
                            
                            <div class="mb-3 recurring-options" style="display: none;">
                                <label>Start Date/Time</label>
                                <input type="datetime-local" name="start_date" class="form-control">
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary">Create Task</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Task Modal -->
<div class="modal fade" id="editTaskModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form action="/tasks/update-task" method="post">
                <input type="hidden" name="id" id="editTaskId">
                <input type="hidden" name="list_id" id="editTaskListId">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Task</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label>Task Title</label>
                                <input type="text" name="title" id="editTaskTitle" class="form-control" required>
                            </div>
                            
                            <div class="mb-3">
                                <label>Description</label>
                                <textarea name="description" id="editTaskDescription" class="form-control" rows="3"></textarea>
                            </div>
                            
                            <div class="mb-3">
                                <label>Assigned To</label>
                                <select name="assigned_to_id" id="editTaskAssignedToId" class="form-select">
                                    <option value="">Select User...</option>
                                    <?php foreach ($users as $user): ?>
                                        <option value="<?= $user['id'] ?>"><?= htmlspecialchars($user['username']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <label>Priority</label>
                                <select name="priority" id="editTaskPriority" class="form-select">
                                    <option value="Low">Low</option>
                                    <option value="Medium">Medium</option>
                                    <option value="High">High</option>
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <label>Status</label>
                                <select name="status" id="editTaskStatus" class="form-select">
                                    <option value="incomplete">Incomplete</option>
                                    <option value="completed">Completed</option>
                                    <option value="ND">ND (Not Done)</option>
                                    <option value="WND">WND (Will Not Do)</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="mb-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="is_one_time" id="editIsOneTime">
                                    <label class="form-check-label" for="editIsOneTime">
                                        One-time task (uncheck for recurring)
                                    </label>
                                </div>
                            </div>
                            
                            <div class="mb-3 recurring-options-edit">
                                <label>Recurring Period</label>
                                <select name="recurring_period" id="editRecurringPeriod" class="form-select">
                                    <option value="daily">Daily</option>
                                    <option value="weekly">Weekly</option>
                                    <option value="monthly">Monthly</option>
                                    <option value="yearly">Yearly</option>
                                </select>
                            </div>
                            
                            <div class="mb-3 recurring-options-edit">
                                <label>Start Date/Time</label>
                                <input type="datetime-local" name="start_date" id="editStartDate" class="form-control">
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Toggle recurring options based on checkbox
    const isOneTimeCheckbox = document.getElementById('isOneTime');
    const recurringOptions = document.querySelectorAll('.recurring-options');
    
    function toggleRecurringOptions() {
        const isChecked = isOneTimeCheckbox.checked;
        recurringOptions.forEach(element => {
            element.style.display = isChecked ? 'none' : 'block';
        });
    }
    
    isOneTimeCheckbox.addEventListener('change', toggleRecurringOptions);
    toggleRecurringOptions(); // Initialize
    
    // Edit Task Modal Logic
    var editModal = new bootstrap.Modal(document.getElementById('editTaskModal'));

    document.querySelectorAll('.edit-task-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            var id = this.getAttribute('data-id');
            var title = this.getAttribute('data-title');
            var description = this.getAttribute('data-description');
            var assignedToId = this.getAttribute('data-assigned-to-id');
            var priority = this.getAttribute('data-priority');
            var isOneTime = this.getAttribute('data-is-one-time');
            var recurringPeriod = this.getAttribute('data-recurring-period');
            var startDate = this.getAttribute('data-start-date');
            var status = this.getAttribute('data-status');

            document.getElementById('editTaskId').value = id;
            document.getElementById('editTaskListId').value = <?= $taskList['id'] ?>;
            document.getElementById('editTaskTitle').value = title;
            document.getElementById('editTaskDescription').value = description;
            document.getElementById('editTaskAssignedToId').value = assignedToId || '';
            document.getElementById('editTaskPriority').value = priority;
            document.getElementById('editIsOneTime').checked = (isOneTime == 1);
            document.getElementById('editTaskStatus').value = status;

            // Handle recurring options visibility
            const recurringOptsEdit = document.querySelectorAll('.recurring-options-edit');
            recurringOptsEdit.forEach(element => {
                element.style.display = (isOneTime == 1) ? 'none' : 'block';
            });

            document.getElementById('editRecurringPeriod').value = recurringPeriod || 'daily';
            document.getElementById('editStartDate').value = startDate || '';

            // Show the modal
            editModal.show();
        });
    });
    
    // Toggle recurring options for edit modal too
    const editIsOneTimeCheckbox = document.getElementById('editIsOneTime');
    const recurringOptionsEdit = document.querySelectorAll('.recurring-options-edit');
    
    function toggleRecurringOptionsEdit() {
        const isChecked = editIsOneTimeCheckbox.checked;
        recurringOptionsEdit.forEach(element => {
            element.style.display = isChecked ? 'none' : 'block';
        });
    }
    
    editIsOneTimeCheckbox.addEventListener('change', toggleRecurringOptionsEdit);
});
</script>

<?php require __DIR__ . '/../footer.php'; ?>