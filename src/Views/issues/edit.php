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
                <div class="col-md-3 mb-3">
                    <label class="form-label">Type</label>
                    <select name="type" class="form-select">
                        <option value="Bug" <?= ($issue['type'] ?? 'Bug') === 'Bug' ? 'selected' : '' ?>>Bug</option>
                        <option value="Feature" <?= ($issue['type'] ?? 'Bug') === 'Feature' ? 'selected' : '' ?>>Feature</option>
                    </select>
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label">Priority</label>
                    <select name="priority" class="form-select">
                        <option value="High" <?= ($issue['priority'] ?? 'Medium') === 'High' ? 'selected' : '' ?>>High</option>
                        <option value="Medium" <?= ($issue['priority'] ?? 'Medium') === 'Medium' ? 'selected' : '' ?>>Medium</option>
                        <option value="Low" <?= ($issue['priority'] ?? 'Medium') === 'Low' ? 'selected' : '' ?>>Low</option>
                    </select>
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select">
                        <?php 
                        $statuses = ['Unassigned', 'In Progress', 'Ready for QA', 'Completed', "WND"];
                        foreach ($statuses as $status): 
                        ?>
                            <option value="<?= $status ?>" <?= $issue['status'] === $status ? 'selected' : '' ?>>
                                <?= $status ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3 mb-3">
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

<div class="mt-4">
    <div class="d-flex align-items-center mb-3">
        <h4 class="mb-0">Comments</h4>
        <span class="badge bg-secondary ms-2"><?= count($comments) ?></span>
    </div>

    <?php foreach ($comments as $comment): ?>
    <div class="card mb-3">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center">
                <strong><?= htmlspecialchars($comment['username']) ?></strong>
                <div class="d-flex align-items-center gap-2">
                    <small class="text-muted"><?= date('M j, Y H:i', strtotime($comment['created_at'])) ?></small>
                </div>
            </div>
            <div class="mt-2">
                <?= nl2br(htmlspecialchars($comment['comment'])) ?>
            </div>
            
            <!-- Attachments for comment -->
            <?php
                $db = Database::connect();
                $stmtAtt = $db->prepare("SELECT * FROM attachments WHERE parent_type = 'comment' AND parent_id = ?");
                $stmtAtt->execute([$comment['id']]);
                $attachments = $stmtAtt->fetchAll();
            ?>
            <?php if ($attachments): ?>
            <div class="mt-3">
                <strong>Attachments:</strong>
                <div class="d-flex flex-wrap gap-2">
                <?php foreach ($attachments as $att): ?>
                    <a href="/uploads/<?= $att['file_path'] ?>" target="_blank">
                        <img src="/uploads/<?= $att['file_path'] ?>" class="img-thumbnail" style="height: 100px;">
                    </a>
                <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        var quill = new Quill('#editor-container', {
            theme: 'snow',
            modules: {
                toolbar: [
                    [{ 'header': [1, 2, 3, false] }],
                    ['bold', 'italic', 'underline', 'strike'],
                    [{ 'color': [] }, { 'background': [] }],
                    [{ 'list': 'ordered'}, { 'list': 'bullet' }, { 'list': 'check' }],
                    ['image', 'code-block']
                ]
            }
        });

        // Add tooltips
        var tooltipMap = {
            'bold': 'Bold',
            'italic': 'Italic',
            'underline': 'Underline',
            'strike': 'Strikethrough',
            'list': {
                'ordered': 'Ordered List',
                'bullet': 'Bullet List',
                'check': 'Checklist'
            },
            'image': 'Insert Image',
            'code-block': 'Code Block',
            'color': 'Text Color',
            'background': 'Background Color',
            'header': 'Header Style'
        };

        Object.keys(tooltipMap).forEach(function(className) {
            var elements = document.querySelectorAll('.ql-' + className);
            elements.forEach(function(el) {
                var value = tooltipMap[className];
                if (typeof value === 'object') {
                    // Handle buttons with specific values (like lists)
                    var val = el.value || ''; 
                    if (value[val]) {
                        el.setAttribute('title', value[val]);
                    }
                } else {
                    el.setAttribute('title', value);
                }
            });
        });
        
        document.getElementById('editIssueForm').onsubmit = function() {
            document.getElementById('descriptionInput').value = quill.root.innerHTML;
        };
    });
</script>

<?php require __DIR__ . '/../footer.php'; ?>
