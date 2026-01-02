<?php require __DIR__ . '/../header.php'; ?>

<nav aria-label="breadcrumb">
  <ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="/">Projects</a></li>
    <li class="breadcrumb-item"><a href="/projects/<?= $issue['project_id'] ?>"><?= htmlspecialchars($issue['project_name']) ?></a></li>
    <li class="breadcrumb-item active" aria-current="page">Issue #<?= $issue['id'] ?></li>
  </ol>
</nav>

<div class="row">
    <div class="col-md-8">
        <div class="card mb-4">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <h2 class="card-title"><?= htmlspecialchars($issue['title']) ?></h2>
                    <div class="btn-group">
                        <a href="/issues/<?= $issue['id'] ?>/edit" class="btn btn-outline-primary">
                            <i class="bi bi-pencil"></i> Edit
                        </a>
                        <form action="/issues/<?= $issue['id'] ?>/delete" method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this issue?');">
                            <button type="submit" class="btn btn-outline-danger">
                                <i class="bi bi-trash"></i> Delete
                            </button>
                        </form>
                    </div>
                </div>
                <div class="mb-3 text-muted small">
                    Created by <strong><?= htmlspecialchars($issue['creator_name']) ?></strong> on <?= date('M j, Y H:i', strtotime($issue['created_at'])) ?>
                </div>
                <hr>
                <div class="card-text">
                    <?= $issue['description'] ?> <!-- Assumed safe HTML from Quill, but normally needs sanitization -->
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
                    <input type="hidden" name="issue_id" value="<?= $issue['id'] ?>">
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
                    if (($issue['priority'] ?? 'Medium') === 'High') $priorityClass = 'bg-danger';
                    elseif (($issue['priority'] ?? 'Medium') === 'Medium') $priorityClass = 'bg-warning text-dark';
                    elseif (($issue['priority'] ?? 'Medium') === 'Low') $priorityClass = 'bg-success';
                    ?>
                    <span class="badge <?= $priorityClass ?>"><?= htmlspecialchars($issue['priority'] ?? 'Medium') ?></span>
                </li>
                <li class="list-group-item">
                    <strong>Type:</strong>
                    <?php if (($issue['type'] ?? 'Bug') === 'Bug'): ?>
                        <span class="badge bg-danger">Bug</span>
                    <?php else: ?>
                        <span class="badge bg-info text-dark">Feature</span>
                    <?php endif; ?>
                </li>
                <li class="list-group-item">
                    <strong>Status:</strong>
                    <span class="badge <?= getStatusBadgeClass($issue['status']) ?>"><?= htmlspecialchars($issue['status']) ?></span>
                </li>
                <li class="list-group-item">
                    <strong>Assigned To:</strong>
                    <?= $issue['assigned_to_name'] ? htmlspecialchars($issue['assigned_to_name']) : '<span class="text-muted">Unassigned</span>' ?>
                </li>
                <li class="list-group-item">
                    <strong>Updated:</strong>
                    <?= date('M j, Y', strtotime($issue['updated_at'])) ?>
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
                // Prevent empty submission if strictly empty
                // But allowing image-only comments
                alert('Please write a comment.');
                return false;
            }
            document.getElementById('commentInput').value = html;
        };
    });
</script>

<?php require __DIR__ . '/../footer.php'; ?>
