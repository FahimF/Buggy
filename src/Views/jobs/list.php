<?php require __DIR__ . '/../header.php'; ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1><?= htmlspecialchars($jobList['title']) ?></h1>
    <div class="d-flex gap-2">
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createJobModal">
            <i class="bi bi-plus-lg"></i> New Job
        </button>
    </div>
</div>

<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-body">
                <?php if (empty($jobs)): ?>
                    <div class="text-center py-5">
                        <i class="bi bi-clipboard fs-1 text-muted"></i>
                        <p class="text-muted">No jobs yet. Create your first job!</p>
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
                                    <th>Next Due</th>
                                    <th>Created</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($jobs as $job): ?>
                                <tr>
                                    <td>
                                        <?php if ($job['status'] !== 'incomplete'): ?>
                                            <s><strong><?= htmlspecialchars($job['title']) ?></strong></s>
                                        <?php else: ?>
                                            <strong><?= htmlspecialchars($job['title']) ?></strong>
                                        <?php endif; ?>
                                        <?php if ($job['is_one_time'] == 0): ?>
                                            <span class="badge bg-info ms-1">Recurring</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= htmlspecialchars(substr($job['description'], 0, 50)) . (strlen($job['description']) > 50 ? '...' : '') ?></td>
                                    <td>
                                        <?= $job['assigned_to_name'] ? htmlspecialchars($job['assigned_to_name']) : 'Unassigned' ?>
                                    </td>
                                    <td>
                                        <?php
                                        $priorityClass = '';
                                        switch ($job['priority']) {
                                            case 'High': $priorityClass = 'danger'; break;
                                            case 'Medium': $priorityClass = 'warning'; break;
                                            case 'Low': $priorityClass = 'success'; break;
                                        }
                                        ?>
                                        <span class="badge bg-<?= $priorityClass ?>"><?= htmlspecialchars($job['priority']) ?></span>
                                    </td>
                                    <td>
                                        <?php if ($job['is_one_time'] == 1): ?>
                                            <span class="badge bg-secondary">One-time</span>
                                        <?php else: ?>
                                            <span class="badge bg-info">Recurring (Every <?= $job['recurring_value'] ?? 1 ?> <?= ucfirst($job['recurring_period']) ?>(s))</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php
                                        $statusClass = '';
                                        switch ($job['status']) {
                                            case 'completed': $statusClass = 'success'; break;
                                            case 'ND': $statusClass = 'secondary'; break;
                                            case 'WND': $statusClass = 'dark'; break;
                                            default: $statusClass = 'primary'; break;
                                        }
                                        ?>
                                        <span class="badge bg-<?= $statusClass ?>"><?= htmlspecialchars($job['status']) ?></span>
                                    </td>
                                    <td>
                                        <?= htmlspecialchars($job['next_occurrence']) ?>
                                    </td>
                                    <td><?= date('M j, Y', strtotime($job['created_at'])) ?></td>
                                    <td>
                                        <div class="btn-group">
                                            <?php if ($job['status'] !== 'incomplete' && $job['is_one_time'] == 1): ?>
                                                <!-- Reactivate button for completed/ND/WND one-time jobs -->
                                                <form action="/jobs/update-status" method="post" class="d-inline">
                                                    <input type="hidden" name="id" value="<?= $job['id'] ?>">
                                                    <input type="hidden" name="list_id" value="<?= $jobList['id'] ?>">
                                                    <input type="hidden" name="status" value="incomplete">
                                                    <button type="submit" class="btn btn-sm btn-outline-info" title="Mark Active">
                                                        <i class="bi bi-arrow-repeat"></i>
                                                    </button>
                                                </form>
                                            <?php else: ?>
                                                <button class="btn btn-sm btn-outline-primary edit-job-btn"
                                                    data-id="<?= $job['id'] ?>"
                                                    data-title="<?= htmlspecialchars($job['title'], ENT_QUOTES, 'UTF-8') ?>"
                                                    data-description="<?= htmlspecialchars($job['description'], ENT_QUOTES, 'UTF-8') ?>"
                                                    data-assigned-to-id="<?= $job['assigned_to_id'] ?>"
                                                    data-priority="<?= $job['priority'] ?>"
                                                    data-is-one-time="<?= $job['is_one_time'] ?>"
                                                    data-recurring-period="<?= $job['recurring_period'] ?>"
                                                    data-recurring-value="<?= $job['recurring_value'] ?? 1 ?>"
                                                    data-start-date="<?= $job['start_date'] ?>"
                                                    data-status="<?= $job['status'] ?>"
                                                    title="Edit">
                                                    <i class="bi bi-pencil"></i>
                                                </button>
                                            <?php endif; ?>
                                            <form action="/jobs/delete-task" method="post" class="d-inline" onsubmit="return confirm('Are you sure?');">
                                                <input type="hidden" name="id" value="<?= $job['id'] ?>">
                                                <input type="hidden" name="list_id" value="<?= $jobList['id'] ?>">
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

<!-- Create Job Modal -->
<div class="modal fade" id="createJobModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form action="/jobs/create-task" method="post">
                <input type="hidden" name="list_id" value="<?= $jobList['id'] ?>">
                <div class="modal-header">
                    <h5 class="modal-title">New Job</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label>Job Title</label>
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
                                        One-time job (uncheck for recurring)
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
                                <label>Recurring Value</label>
                                <input type="number" name="recurring_value" class="form-control" value="1" min="1">
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
                    <button type="submit" class="btn btn-primary">Create Job</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Job Modal -->
<div class="modal fade" id="editJobModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form action="/jobs/update-task" method="post">
                <input type="hidden" name="id" id="editJobId">
                <input type="hidden" name="list_id" id="editJobListId">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Job</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label>Job Title</label>
                                <input type="text" name="title" id="editJobTitle" class="form-control" required>
                            </div>
                            
                            <div class="mb-3">
                                <label>Description</label>
                                <textarea name="description" id="editJobDescription" class="form-control" rows="3"></textarea>
                            </div>
                            
                            <div class="mb-3">
                                <label>Assigned To</label>
                                <select name="assigned_to_id" id="editJobAssignedToId" class="form-select">
                                    <option value="">Select User...</option>
                                    <?php foreach ($users as $user): ?>
                                        <option value="<?= $user['id'] ?>"><?= htmlspecialchars($user['username']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <label>Priority</label>
                                <select name="priority" id="editJobPriority" class="form-select">
                                    <option value="Low">Low</option>
                                    <option value="Medium">Medium</option>
                                    <option value="High">High</option>
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <label>Status</label>
                                <select name="status" id="editJobStatus" class="form-select">
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
                                        One-time job (uncheck for recurring)
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
                                <label>Recurring Value</label>
                                <input type="number" name="recurring_value" id="editRecurringValue" class="form-control" min="1">
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

    // Edit Job Modal Logic
    var editModal = new bootstrap.Modal(document.getElementById('editJobModal'));

    document.querySelectorAll('.edit-job-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            var id = this.getAttribute('data-id');
            var title = this.getAttribute('data-title');
            var description = this.getAttribute('data-description');
            var assignedToId = this.getAttribute('data-assigned-to-id');
            var priority = this.getAttribute('data-priority');
            var isOneTime = this.getAttribute('data-is-one-time');
            var recurringPeriod = this.getAttribute('data-recurring-period');
            var recurringValue = this.getAttribute('data-recurring-value');
            var startDate = this.getAttribute('data-start-date');
            var status = this.getAttribute('data-status');

            document.getElementById('editJobId').value = id;
            document.getElementById('editJobListId').value = <?= $jobList['id'] ?>;
            document.getElementById('editJobTitle').value = title;
            document.getElementById('editJobDescription').value = description;
            document.getElementById('editJobAssignedToId').value = assignedToId || '';
            document.getElementById('editJobPriority').value = priority;
            document.getElementById('editIsOneTime').checked = (isOneTime == 1);
            document.getElementById('editJobStatus').value = status;

            // Handle recurring options visibility
            const recurringOptsEdit = document.querySelectorAll('.recurring-options-edit');
            recurringOptsEdit.forEach(element => {
                element.style.display = (isOneTime == 1) ? 'none' : 'block';
            });

            document.getElementById('editRecurringPeriod').value = recurringPeriod || 'daily';
            document.getElementById('editRecurringValue').value = recurringValue || 1;
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