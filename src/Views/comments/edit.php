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
                <form action="/comments/<?= $comment['id'] ?>/update" method="post" id="editCommentForm">
                    <div class="mb-3">
                        <div id="editor-container" style="height: 300px;"><?= $comment['comment'] ?></div>
                        <input type="hidden" name="comment" id="commentInput">
                    </div>
                    <button type="submit" class="btn btn-primary">Update Comment</button>
                    <a href="/issues/<?= $comment['issue_id'] ?>" class="btn btn-secondary">Cancel</a>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        var quill = new Quill('#editor-container', {
            theme: 'snow',
            modules: {
                toolbar: [
                    ['bold', 'italic', 'underline', 'strike'],
                    ['blockquote', 'code-block'],
                    [{ 'list': 'ordered'}, { 'list': 'bullet' }],
                    ['image']
                ]
            }
        });
        
        document.getElementById('editCommentForm').onsubmit = function() {
            document.getElementById('commentInput').value = quill.root.innerHTML;
        };
    });
</script>

<?php require __DIR__ . '/../footer.php'; ?>
