<?php require __DIR__ . '/../header.php'; ?>

<nav aria-label="breadcrumb">
  <ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="/">Projects</a></li>
    <li class="breadcrumb-item"><a href="/projects/<?= $task['project_id'] ?>"><?= htmlspecialchars($task['project_name']) ?></a></li>
    <li class="breadcrumb-item active" aria-current="page">Task #<?= $task['id'] ?></li>
  </ol>
</nav>

<div class="row">
    <div class="col-md-8">
        <div class="card mb-4">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <h2 class="card-title"><?= htmlspecialchars($task['title']) ?></h2>
                    <div class="btn-group">
                        <a href="/tasks/<?= $task['id'] ?>/edit" class="btn btn-outline-primary">
                            <i class="bi bi-pencil"></i> Edit
                        </a>
                        <form action="/tasks/<?= $task['id'] ?>/delete" method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this task?');">
                            <button type="submit" class="btn btn-outline-danger">
                                <i class="bi bi-trash"></i> Delete
                            </button>
                        </form>
                    </div>
                </div>
                <div class="mb-3 text-muted small">
                    Created by <strong><?= htmlspecialchars($task['creator_name']) ?></strong> on <?= date('M j, Y H:i', strtotime($task['created_at'])) ?>
                </div>
                <hr>
                <div class="card-text">
                    <?= $task['description'] ?> <!-- Assumed safe HTML from Quill, but normally needs sanitization -->
                </div>
                
                <hr>
                <div class="mt-4">
                    <h5>Sub-tasks</h5>
                    <div id="taskSubtasksContainer" class="mb-3">
                        <!-- Loaded dynamically -->
                    </div>
                </div>
            </div>
        </div>

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
                        <?php $currentUser = Auth::user(); ?>
                        <?php if ($currentUser && $currentUser['id'] == $comment['user_id']): ?>
                            <a href="/comments/<?= $comment['id'] ?>/edit" class="btn btn-sm btn-outline-secondary py-0 px-1" title="Edit"><i class="bi bi-pencil"></i></a>
                        <?php endif; ?>
                        <?php if ($currentUser && ($currentUser['id'] == $comment['user_id'] || $currentUser['is_admin'])): ?>
                            <form action="/comments/<?= $comment['id'] ?>/delete" method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this comment?');">
                                <button type="submit" class="btn btn-sm btn-outline-danger py-0 px-1" title="Delete"><i class="bi bi-trash"></i></button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="mt-2 ql-editor" style="padding: 0;">
                    <?= $comment['comment'] ?>
                </div>
            </div>
        </div>
        <?php endforeach; ?>

        <div class="card">
            <div class="card-header">Add Comment</div>
            <div class="card-body">
                <form action="/comments/create" method="post" id="addCommentForm">
                    <input type="hidden" name="task_id" value="<?= $task['id'] ?>">
                    <div class="mb-3">
                        <div id="comment-editor" style="height: 150px;"></div>
                        <input type="hidden" name="comment" id="commentInput">
                    </div>
                    <button type="submit" class="btn btn-primary">Post Comment</button>
                </form>
            </div>
        </div>

    </div>
    <div class="col-md-4">
        <div class="card">
            <div class="card-header">Details</div>
            <ul class="list-group list-group-flush">
                <li class="list-group-item">
                    <strong>Priority:</strong>
                    <?php 
                    $priorityClass = 'bg-secondary';
                    if (($task['priority'] ?? 'Medium') === 'High') $priorityClass = 'bg-danger';
                    elseif (($task['priority'] ?? 'Medium') === 'Medium') $priorityClass = 'bg-warning text-dark';
                    elseif (($task['priority'] ?? 'Medium') === 'Low') $priorityClass = 'bg-success';
                    ?>
                    <span class="badge <?= $priorityClass ?>"><?= htmlspecialchars($task['priority'] ?? 'Medium') ?></span>
                </li>
                <li class="list-group-item">
                    <strong>Type:</strong>
                    <?php if (($task['type'] ?? 'Bug') === 'Bug'): ?>
                        <span class="badge bg-danger">Bug</span>
                    <?php else: ?>
                        <span class="badge bg-info text-dark">Feature</span>
                    <?php endif; ?>
                </li>
                <li class="list-group-item">
                    <strong>Status:</strong>
                    <span class="badge <?= getStatusBadgeClass($task['status']) ?>"><?= htmlspecialchars($task['status']) ?></span>
                </li>
                <li class="list-group-item">
                    <strong>Assigned To:</strong>
                    <?= $task['assigned_to_name'] ? htmlspecialchars($task['assigned_to_name']) : '<span class="text-muted">Unassigned</span>' ?>
                </li>
                <li class="list-group-item">
                    <strong>Updated:</strong>
                    <?= date('M j, Y', strtotime($task['updated_at'])) ?>
                </li>
            </ul>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        var quill = new Quill('#comment-editor', {
            theme: 'snow',
            modules: {
                toolbar: [
                    ['bold', 'italic', 'underline', 'strike'],
                    ['blockquote', 'code-block'],
                    [{ 'list': 'ordered'}, { 'list': 'bullet' }],
                    ['image']
                ]
            },
            placeholder: 'Write a comment...'
        });

        document.getElementById('addCommentForm').onsubmit = function() {
            var html = quill.root.innerHTML;
            if (quill.getText().trim().length === 0 && !html.includes('<img')) {
                alert('Please write a comment.');
                return false;
            }
            document.getElementById('commentInput').value = html;
        };

    // Sub-tasks Logic (Read-only)
    const taskId = <?= $task['id'] ?>;

    function loadSubtasks() {
        const container = document.getElementById('taskSubtasksContainer');
        container.innerHTML = '<div class="text-muted small">Loading sub-tasks...</div>';

        fetch('/tasks/sub-tasks?issue_id=' + taskId)
            .then(res => res.json())
            .then(subtasks => {
                container.innerHTML = '';

                if (subtasks.length === 0) {
                    container.innerHTML = '<div class="text-muted small py-1">No sub-tasks.</div>';
                    return;
                }

                subtasks.forEach(st => {
                    const div = document.createElement('div');
                    div.className = 'd-flex align-items-center border-bottom py-2';

                    const isComp = parseInt(st.is_completed) === 1;
                    const icon = document.createElement('i');
                    icon.className = isComp ? 'bi bi-check-square-fill text-success me-2' : 'bi bi-square text-muted me-2';

                    const span = document.createElement('span');
                    if (isComp) {
                        span.innerHTML = '<s>' + escapeHtml(st.description) + '</s>';
                    } else {
                        span.textContent = st.description;
                    }

                    div.appendChild(icon);
                    div.appendChild(span);
                    container.appendChild(div);
                });
            });
    }

    function escapeHtml(text) {
        return text
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#039;");
    }

    loadSubtasks();
    });
</script>

<?php require __DIR__ . '/../footer.php'; ?>
