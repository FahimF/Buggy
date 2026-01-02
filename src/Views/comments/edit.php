<?php require __DIR__ . '/../header.php'; ?>

<nav aria-label="breadcrumb">
  <ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="/">Projects</a></li>
    <li class="breadcrumb-item"><a href="/issues/<?= $comment['issue_id'] ?>">Back to Issue</a></li>
    <li class="breadcrumb-item active" aria-current="page">Edit Comment</li>
  </ol>
</nav>

<div class="row justify-content-center">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header">Edit Comment</div>
            <div class="card-body">
                <form action="/comments/<?= $comment['id'] ?>/update" method="post">
                    <div class="mb-3">
                        <textarea name="comment" class="form-control" rows="5" required><?= htmlspecialchars($comment['comment']) ?></textarea>
                    </div>
                    <button type="submit" class="btn btn-primary">Update Comment</button>
                    <a href="/issues/<?= $comment['issue_id'] ?>" class="btn btn-secondary">Cancel</a>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../footer.php'; ?>
