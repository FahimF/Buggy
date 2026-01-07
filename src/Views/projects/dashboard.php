<?php
require_once __DIR__ . '/../header.php';

function getPriorityBadgeClass($priority) {
    switch ($priority) {
        case 'High':
            return 'bg-danger';
        case 'Medium':
            return 'bg-warning text-dark';
        case 'Low':
            return 'bg-success';
        default:
            return 'bg-secondary';
    }
}

function getTypeBadgeClass($type) {
    switch ($type) {
        case 'Bug':
            return 'bg-danger';
        case 'Feature':
            return 'bg-primary';
        case 'Task':
            return 'bg-info';
        case 'Improvement':
            return 'bg-success';
        default:
            return 'bg-secondary';
    }
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
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createIssueModal">
            <i class="bi bi-plus-lg"></i> New Issue
        </button>
    </div>
</div>

<?php if (empty($groupedIssues)): ?>
    <div class="alert alert-info">
        <h4>No Issues Assigned</h4>
        <p>You don't have any issues assigned to you yet. Issues assigned to you will appear here.</p>
    </div>
<?php else: ?>
    <?php foreach ($groupedIssues as $groupName => $issues): ?>
        <div class="mb-4">
            <h3 class="border-bottom pb-2"><?= htmlspecialchars($groupName) ?> <span class="badge bg-secondary"><?= count($issues) ?></span></h3>
            <div class="row">
                <?php foreach ($issues as $issue): ?>
                    <div class="col-md-12 mb-3">
                        <div class="card">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <h5 class="card-title">
                                            <a href="/issues/<?= $issue['id'] ?>/edit" class="text-decoration-none">
                                                [<?= htmlspecialchars($issue['project_name']) ?>] <?= htmlspecialchars($issue['title']) ?>
                                            </a>
                                        </h5>
                                    </div>
                                    <div class="d-flex align-items-center">
                                        <span class="badge bg-secondary me-2">
                                            <i class="bi bi-chat-dots"></i> <?= $issue['comment_count'] ?>
                                        </span>
                                    </div>
                                </div>

                                <?php if ($issue['description']): ?>
                                    <p class="card-text description-preview">
                                        <?= htmlspecialchars(strip_tags($issue['description'])) ?>
                                    </p>
                                <?php endif; ?>

                                <div class="mt-3">
                                    <span class="badge <?= getPriorityBadgeClass($issue['priority']) ?> me-2">
                                        <?= htmlspecialchars($issue['priority']) ?>
                                    </span>
                                    <span class="badge <?= getTypeBadgeClass($issue['type']) ?> me-2">
                                        <?= htmlspecialchars($issue['type']) ?>
                                    </span>
                                    <span class="badge <?= getStatusBadgeClass($issue['status']) ?>">
                                        <?= htmlspecialchars($issue['status']) ?>
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

<!-- Include the create issue modal -->
<?php require_once __DIR__ . '/../issues/create_modal.php'; ?>

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