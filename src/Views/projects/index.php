<?php require __DIR__ . '/../header.php'; ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1>Projects</h1>
    <div>
        <div class="btn-group me-2">
            <button type="button" class="btn btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">
                <i class="bi bi-sort-down"></i> Sort
            </button>
            <ul class="dropdown-menu dropdown-menu-end">
                <li><a class="dropdown-item" href="?sort=created_at">Newest First</a></li>
                <li><a class="dropdown-item" href="?sort=updated">Last Updated</a></li>
                <li><a class="dropdown-item" href="?sort=active_issues">Most Active Issues</a></li>
                <li><a class="dropdown-item" href="?sort=name">Name (A-Z)</a></li>
            </ul>
        </div>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createProjectModal">
            <i class="bi bi-plus-lg"></i> New Project
        </button>
    </div>
</div>

<div class="row">
    <?php foreach ($projects as $project): ?>
    <div class="col-md-4 mb-4">
        <div class="card h-100 shadow-sm">
            <div class="card-header d-flex justify-content-between align-items-center" style="background-color: <?= htmlspecialchars($project['color']) ?>; color: <?= htmlspecialchars($project['text_color'] ?? '#ffffff') ?>;">
                <h5 class="card-title mb-0"><?= htmlspecialchars($project['name']) ?></h5>
                <div class="dropdown">
                    <button class="btn btn-link p-0" style="color: <?= htmlspecialchars($project['text_color'] ?? '#ffffff') ?>;" data-bs-toggle="dropdown">
                        <i class="bi bi-three-dots-vertical"></i>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li>
                            <a href="#" class="dropdown-item add-issue-btn" data-project-id="<?= $project['id'] ?>" data-type="Bug">Add Bug</a>
                        </li>
                        <li>
                            <a href="#" class="dropdown-item add-issue-btn" data-project-id="<?= $project['id'] ?>" data-type="Feature">Add Feature</a>
                        </li>
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <button class="dropdown-item edit-project-btn" 
                                data-id="<?= $project['id'] ?>"
                                data-name="<?= htmlspecialchars($project['name']) ?>"
                                data-color="<?= htmlspecialchars($project['color']) ?>"
                                data-text-color="<?= htmlspecialchars($project['text_color'] ?? '#ffffff') ?>">
                                Edit
                            </button>
                        </li>
                        <li>
                            <form action="/projects/delete" method="post" onsubmit="return confirm('Are you sure?');">
                                <input type="hidden" name="id" value="<?= $project['id'] ?>">
                                <button type="submit" class="dropdown-item text-danger">Delete</button>
                            </form>
                        </li>
                    </ul>
                </div>
            </div>
            <div class="card-body">
                <div class="d-flex justify-content-between mb-3">
                    <span class="badge bg-light text-dark border">
                        <i class="bi bi-list-task"></i> Total: <?= $project['total_issues'] ?>
                    </span>
                    <span class="badge bg-primary">
                        <i class="bi bi-exclamation-circle"></i> Active: <?= $project['active_issues'] ?>
                    </span>
                </div>

                <!-- User Stats -->
                <?php if (isset($projectStats[$project['id']])): ?>
                <div class="mb-3">
                    <small class="text-muted d-block mb-1">Active Tasks by User:</small>
                    <div class="d-flex flex-wrap gap-1">
                        <?php foreach ($projectStats[$project['id']] as $stat): ?>
                            <?php 
                            $isCurrentUser = $stat['username'] === Auth::user()['username'];
                            $badgeClass = $isCurrentUser ? 'bg-warning text-dark' : 'bg-secondary';
                            ?>
                            <span class="badge <?= $badgeClass ?>" style="font-size: 0.75rem;">
                                <?= htmlspecialchars($stat['username']) ?>: <?= $stat['count'] ?>
                            </span>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <p class="card-text mt-3">
                    <small class="text-muted">Owner: <?= htmlspecialchars($project['owner_name']) ?></small><br>
                    <small class="text-muted">Created: <?= date('M j, Y', strtotime($project['created_at'])) ?></small>
                </p>
                <a href="/projects/<?= $project['id'] ?>" class="btn btn-outline-primary w-100">View Issues</a>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- Create Project Modal -->
<div class="modal fade" id="createProjectModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="/projects/create" method="post">
                <div class="modal-header">
                    <h5 class="modal-title">New Project</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label>Project Name</label>
                        <input type="text" name="name" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label>Color</label>
                        <input type="color" name="color" class="form-control form-control-color" value="#007bff">
                    </div>
                    <div class="mb-3">
                        <label>Text Color</label>
                        <input type="color" name="text_color" class="form-control form-control-color" value="#ffffff">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary">Create</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Project Modal -->
<div class="modal fade" id="editProjectModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="/projects/update" method="post">
                <input type="hidden" name="id" id="editProjectId">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Project</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label>Project Name</label>
                        <input type="text" name="name" id="editProjectName" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label>Color</label>
                        <input type="color" name="color" id="editProjectColor" class="form-control form-control-color">
                    </div>
                    <div class="mb-3">
                        <label>Text Color</label>
                        <input type="color" name="text_color" id="editProjectTextColor" class="form-control form-control-color">
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

<!-- Reuse the Create Issue Modal -->
<?php require __DIR__ . '/../issues/create_modal.php'; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Edit Project Modal Logic
    var editModal = new bootstrap.Modal(document.getElementById('editProjectModal'));
    
    document.querySelectorAll('.edit-project-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            var id = this.getAttribute('data-id');
            var name = this.getAttribute('data-name');
            var color = this.getAttribute('data-color');
            var textColor = this.getAttribute('data-text-color');
            
            document.getElementById('editProjectId').value = id;
            document.getElementById('editProjectName').value = name;
            document.getElementById('editProjectColor').value = color;
            document.getElementById('editProjectTextColor').value = textColor;
            
            editModal.show();
        });
    });

    // Add Issue (Bug/Feature) Modal Logic
    var createIssueModal = new bootstrap.Modal(document.getElementById('createIssueModal'));

    document.querySelectorAll('.add-issue-btn').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            var projectId = this.getAttribute('data-project-id');
            var type = this.getAttribute('data-type');

            document.getElementById('createIssueProjectId').value = projectId;
            document.getElementById('createIssueType').value = type;
            
            createIssueModal.show();
        });
    });
});
</script>

<?php require __DIR__ . '/../footer.php'; ?>
