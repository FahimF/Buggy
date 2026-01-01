<?php require __DIR__ . '/../header.php'; ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1>Projects</h1>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createProjectModal">
        <i class="bi bi-plus-lg"></i> New Project
    </button>
</div>

<div class="row">
    <?php foreach ($projects as $project): ?>
    <div class="col-md-4 mb-4">
        <div class="card h-100 shadow-sm">
            <div class="card-header d-flex justify-content-between align-items-center" style="background-color: <?= htmlspecialchars($project['color']) ?>; color: white;">
                <h5 class="card-title mb-0"><?= htmlspecialchars($project['name']) ?></h5>
                <div class="dropdown">
                    <button class="btn btn-link text-white p-0" data-bs-toggle="dropdown">
                        <i class="bi bi-three-dots-vertical"></i>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
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
                <p class="card-text">
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
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary">Create</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../footer.php'; ?>
