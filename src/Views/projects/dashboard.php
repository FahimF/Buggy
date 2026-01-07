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
        <a href="/projects" class="btn btn-outline-primary me-2">All Projects</a>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createIssueModal">
            <i class="bi bi-plus-lg"></i> New Issue
        </button>
    </div>
</div>

<?php if (empty($issues)): ?>
    <div class="alert alert-info">
        <h4>No Issues Assigned</h4>
        <p>You don't have any issues assigned to you yet. Issues assigned to you will appear here.</p>
    </div>
<?php else: ?>
    <div class="row">
        <?php foreach ($issues as $issue): ?>
            <div class="col-md-12 mb-3">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <h5 class="card-title">
                                    <a href="/issues/<?= $issue['id'] ?>" class="text-decoration-none">
                                        <?= htmlspecialchars($issue['title']) ?>
                                    </a>
                                </h5>
                                <p class="card-subtitle text-muted mb-2">
                                    <small>
                                        Project: <strong><?= htmlspecialchars($issue['project_name']) ?></strong>
                                    </small>
                                </p>
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
<?php endif; ?>

<!-- Include the create issue modal -->
<?php require_once __DIR__ . '/../issues/create_modal.php'; ?>

<?php require_once __DIR__ . '/../footer.php'; ?>