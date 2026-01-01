<?php require __DIR__ . '/../header.php'; ?>

<nav aria-label="breadcrumb">
  <ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="/">Projects</a></li>
    <li class="breadcrumb-item"><a href="/projects/<?= $project['id'] ?>"><?= htmlspecialchars($project['name']) ?></a></li>
    <li class="breadcrumb-item active" aria-current="page">Edit Issue #<?= $issue['id'] ?></li>
  </ol>
</nav>

<div class="card">
    <div class="card-header">
        <h3>Edit Issue</h3>
    </div>
    <div class="card-body">
        <form action="/issues/<?= $issue['id'] ?>/update" method="post" id="editIssueForm">
            <div class="mb-3">
                <label class="form-label">Title</label>
                <input type="text" name="title" class="form-control" value="<?= htmlspecialchars($issue['title']) ?>" required>
            </div>
            
            <div class="row">
                <div class="col-md-4 mb-3">
                    <label class="form-label">Type</label>
                    <select name="type" class="form-select">
                        <option value="Bug" <?= ($issue['type'] ?? 'Bug') === 'Bug' ? 'selected' : '' ?>>Bug</option>
                        <option value="Feature" <?= ($issue['type'] ?? 'Bug') === 'Feature' ? 'selected' : '' ?>>Feature</option>
                    </select>
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select">
                        <?php 
                        $statuses = ['Unassigned', 'In Progress', 'Ready for QA', 'Completed', "Won't Do"];
                        foreach ($statuses as $status): 
                        ?>
                            <option value="<?= $status ?>" <?= $issue['status'] === $status ? 'selected' : '' ?>>
                                <?= $status ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">Assign To</label>
                    <select name="assigned_to" class="form-select">
                        <option value="">-- Unassigned --</option>
                        <?php foreach ($users as $user): ?>
                            <option value="<?= $user['id'] ?>" <?= $issue['assigned_to_id'] == $user['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($user['username']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label">Description</label>
                <div id="editor-container" style="height: 300px;"><?= $issue['description'] ?></div>
                <input type="hidden" name="description" id="descriptionInput">
            </div>

            <div class="d-flex justify-content-between">
                <a href="/projects/<?= $project['id'] ?>" class="btn btn-secondary">Cancel</a>
                <button type="submit" class="btn btn-primary">Update Issue</button>
            </div>
        </form>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        var quill = new Quill('#editor-container', {
            theme: 'snow'
        });
        
        document.getElementById('editIssueForm').onsubmit = function() {
            document.getElementById('descriptionInput').value = quill.root.innerHTML;
        };
    });
</script>

<?php require __DIR__ . '/../footer.php'; ?>
