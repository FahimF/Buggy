<?php require __DIR__ . '/../header.php'; ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1>Job Lists</h1>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createJobListModal">
        <i class="bi bi-plus-lg"></i> New Job List
    </button>
</div>

<div class="row">
    <?php foreach ($jobLists as $list): ?>
    <div class="col-md-4 mb-4">
        <div class="card h-100 shadow-sm">
            <div class="card-header d-flex justify-content-between align-items-center bg-primary text-white">
                <h5 class="card-title mb-0"><?= htmlspecialchars($list['title']) ?></h5>
                <div class="dropdown">
                    <button class="btn btn-link p-0 text-white" data-bs-toggle="dropdown">
                        <i class="bi bi-three-dots-vertical"></i>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li>
                            <a class="dropdown-item" href="/jobs/list/<?= $list['id'] ?>">View Jobs</a>
                        </li>
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <button class="dropdown-item edit-list-btn"
                                data-id="<?= $list['id'] ?>"
                                data-title="<?= htmlspecialchars($list['title']) ?>">
                                Edit
                            </button>
                        </li>
                        <li>
                            <form action="/jobs/delete-list" method="post" onsubmit="return confirm('Are you sure?');">
                                <input type="hidden" name="id" value="<?= $list['id'] ?>">
                                <button type="submit" class="dropdown-item text-danger">Delete</button>
                            </form>
                        </li>
                    </ul>
                </div>
            </div>
            <div class="card-body">
                <div class="d-flex justify-content-between mb-3">
                    <span class="badge bg-light text-dark border">
                        <i class="bi bi-list-task"></i> Total: <?= $list['total_jobs'] ?>
                    </span>
                    <span class="badge bg-warning text-dark">
                        <i class="bi bi-exclamation-circle"></i> Incomplete: <?= $list['incomplete_jobs'] ?>
                    </span>
                </div>
                
                <p class="card-text mt-3">
                    <small class="text-muted">Owner: <?= htmlspecialchars($list['owner_name']) ?></small><br>
                    <small class="text-muted">Created: <?= date('M j, Y', strtotime($list['created_at'])) ?></small>
                </p>
                <a href="/jobs/list/<?= $list['id'] ?>" class="btn btn-outline-primary w-100">View Jobs</a>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- Create Job List Modal -->
<div class="modal fade" id="createJobListModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="/jobs/create-list" method="post">
                <div class="modal-header">
                    <h5 class="modal-title">New Job List</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label>Job List Name</label>
                        <input type="text" name="title" class="form-control" required>
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

<!-- Edit Job List Modal -->
<div class="modal fade" id="editJobListModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="/jobs/update-list" method="post">
                <input type="hidden" name="id" id="editListId">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Job List</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label>Job List Name</label>
                        <input type="text" name="title" id="editListTitle" class="form-control" required>
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
    // Edit Job List Modal Logic
    var editModal = new bootstrap.Modal(document.getElementById('editJobListModal'));

    document.querySelectorAll('.edit-list-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            var id = this.getAttribute('data-id');
            var title = this.getAttribute('data-title');

            document.getElementById('editListId').value = id;
            document.getElementById('editListTitle').value = title;

            editModal.show();
        });
    });
});
</script>

<?php require __DIR__ . '/../footer.php'; ?>