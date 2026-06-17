<?php
require_once __DIR__ . '/../header.php';

function getJobStatusForm($jobId, $listId, $status, $isRecurring, $inboxId = null) {
    if ($isRecurring) {
        $options = [
            'incomplete' => 'Incomplete',
            'completed' => 'Complete',
            'ND' => 'ND (Not Done)'
        ];
    } else {
        $options = [
            'incomplete' => 'Incomplete',
            'completed' => 'Complete',
            'ND' => 'ND (Not Done)',
            'WND' => 'WND (Will Not Do)'
        ];
    }

    $html = '<form action="/jobs/update-status" method="post" class="d-inline">';
    $html .= '<input type="hidden" name="id" value="' . $jobId . '">';
    $html .= '<input type="hidden" name="list_id" value="' . $listId . '">';
    $html .= '<input type="hidden" name="redirect_to" value="dashboard">';
    if ($inboxId) {
        $html .= '<input type="hidden" name="inbox_id" value="' . $inboxId . '">';
    }
    $html .= '<select name="status" class="form-select form-select-sm" onchange="this.form.submit()">';

    foreach ($options as $value => $label) {
        $selected = ($status === $value) ? 'selected' : '';
        $html .= '<option value="' . $value . '" ' . $selected . '>' . $label . '</option>';
    }

    $html .= '</select>';
    $html .= '</form>';

    return $html;
}
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1>Dashboard</h1>
    <div class="d-flex">
        <div class="dropdown me-2">
            <button class="btn btn-outline-secondary dropdown-toggle" type="button" id="groupByDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                Group By
            </button>
            <ul class="dropdown-menu" aria-labelledby="groupByDropdown">
                <li><a class="dropdown-item" href="#" data-group-by="project">Project</a></li>
                <li><a class="dropdown-item" href="#" data-group-by="priority">Priority</a></li>
                <li><a class="dropdown-item" href="#" data-group-by="type">Type</a></li>
                <li><a class="dropdown-item" href="#" data-group-by="status">Status</a></li>
            </ul>
        </div>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createTaskModal">
            <i class="bi bi-plus-lg"></i> New Task
        </button>
    </div>
</div>

<!-- Display Jobs assigned to the user -->
<?php if (!empty($allJobs)): ?>
    <div class="mb-4">
        <h3 class="border-bottom pb-2">Jobs Assigned to You <span class="badge bg-info"><?= count($allJobs) ?></span></h3>
        <div class="row">
            <?php foreach ($allJobs as $job): ?>
                <div class="col-md-12 mb-3">
                    <div class="card">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <h5 class="card-title">
                                        <a href="/jobs/list/<?= $job['list_id'] ?>" class="text-decoration-none">
                                            [<?= htmlspecialchars($job['list_title']) ?>] <?= htmlspecialchars($job['title']) ?>
                                        </a>
                                    </h5>
                                </div>
                                <div class="d-flex align-items-center">
                                    <span class="badge bg-secondary me-2">
                                        <i class="bi bi-calendar-event"></i> 
                                        <?php if ($job['due_at']): ?>
                                            <?= date('M j, Y g:i A', strtotime($job['due_at'])) ?>
                                        <?php else: ?>
                                            Now
                                        <?php endif; ?>
                                    </span>
                                </div>
                            </div>

                            <?php if ($job['description']): ?>
                                <p class="card-text description-preview">
                                    <?= htmlspecialchars(html_entity_decode(strip_tags($job['description']))) ?>
                                </p>
                            <?php endif; ?>

                            <div class="mt-3">
                                <span class="badge <?= getPriorityBadgeClass($job['priority']) ?> me-2">
                                    <?= htmlspecialchars($job['priority']) ?>
                                </span>
                                <?php if ($job['is_one_time'] == 1): ?>
                                    <span class="badge bg-secondary me-2">One-time</span>
                                <?php else: ?>
                                    <span class="badge bg-info me-2">Recurring (<?= ucfirst($job['recurring_period']) ?>)</span>
                                <?php endif; ?>

                                <div class="ms-2 d-inline-block">
                                    <?= getJobStatusForm($job['id'], $job['list_id'], $job['status'], $job['is_one_time'] == 0, $job['inbox_id'] ?? null) ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
<?php endif; ?>

<?php if (empty($groupedTasks)): ?>
    <div class="alert alert-info">
        <h4>No Tasks Assigned</h4>
        <p>You don't have any tasks assigned to you yet. Tasks assigned to you will appear here.</p>
    </div>
<?php else: ?>
    <?php foreach ($groupedTasks as $groupName => $tasks): ?>
        <div class="mb-4">
            <h3 class="border-bottom pb-2"><?= htmlspecialchars($groupName) ?> <span class="badge bg-secondary"><?= count($tasks) ?></span></h3>
            <div class="row">
                <?php foreach ($tasks as $task): ?>
                    <div class="col-md-12 mb-3">
                        <div class="card">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <h5 class="card-title">
                                            <a href="/tasks/<?= $task['id'] ?>" class="text-decoration-none">
                                                [<?= htmlspecialchars($task['project_name']) ?>] <?= htmlspecialchars($task['title']) ?>
                                            </a>
                                        </h5>
                                    </div>
                                    <div class="d-flex align-items-center">
                                        <span class="badge bg-secondary me-2">
                                            <i class="bi bi-chat-dots"></i> <?= $task['comment_count'] ?>
                                        </span>
                                    </div>
                                </div>

                                <?php if ($task['description']): ?>
                                    <p class="card-text description-preview">
                                        <?= htmlspecialchars(html_entity_decode(strip_tags($task['description']))) ?>
                                    </p>
                                <?php endif; ?>

                                <div class="mt-3">
                                    <span class="badge <?= getPriorityBadgeClass($task['priority']) ?> me-2">
                                        <?= htmlspecialchars($task['priority']) ?>
                                    </span>
                                    <span class="badge <?= getTypeBadgeClass($task['type']) ?> me-2">
                                        <?= htmlspecialchars($task['type']) ?>
                                    </span>
                                    <span class="badge <?= getStatusBadgeClass($task['status']) ?>">
                                        <?= htmlspecialchars($task['status']) ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endforeach; ?>
<?php endif; ?>

<!-- Include the create task modal -->
<?php require_once __DIR__ . '/../tasks/create_modal.php'; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const dropdownItems = document.querySelectorAll('#groupByDropdown + .dropdown-menu .dropdown-item');
    const currentGroupBy = new URLSearchParams(window.location.search).get('group_by') || 'project';

    // Highlight the current selection
    dropdownItems.forEach(item => {
        if (item.getAttribute('data-group-by') === currentGroupBy) {
            item.classList.add('active');
        }
    });

    dropdownItems.forEach(item => {
        item.addEventListener('click', function(e) {
            e.preventDefault();

            const groupBy = this.getAttribute('data-group-by');
            const groupByText = this.textContent;

            // Update the dropdown button text to show the selected option
            document.getElementById('groupByDropdown').textContent = groupByText;

            // Remove active class from all items
            dropdownItems.forEach(dropdownItem => {
                dropdownItem.classList.remove('active');
            });

            // Add active class to the clicked item
            this.classList.add('active');

            // Reload the page with the new grouping parameter
            window.location.href = `?group_by=${groupBy}`;
        });
    });
});
</script>

<?php require_once __DIR__ . '/../footer.php'; ?>