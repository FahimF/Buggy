<div class="modal fade" id="createIssueModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form action="/issues/create" method="post" id="createIssueForm">
                <input type="hidden" name="project_id" value="<?= $project['id'] ?>">
                <div class="modal-header">
                    <h5 class="modal-title">New Issue</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label>Title</label>
                        <input type="text" name="title" class="form-control" required>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label>Status</label>
                            <select name="status" class="form-select">
                                <option value="Unassigned">Unassigned</option>
                                <option value="In Progress">In Progress</option>
                                <option value="Ready for QA">Ready for QA</option>
                                <option value="Completed">Completed</option>
                                <option value="Won't Do">Won't Do</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label>Assign To</label>
                            <select name="assigned_to" class="form-select">
                                <option value="">-- Unassigned --</option>
                                <?php foreach ($users as $user): ?>
                                    <option value="<?= $user['id'] ?>"><?= htmlspecialchars($user['username']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label>Description</label>
                        <div id="editor-container" style="height: 200px;"></div>
                        <input type="hidden" name="description" id="descriptionInput">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary">Create Issue</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    var quill = new Quill('#editor-container', {
        theme: 'snow'
    });
    
    document.getElementById('createIssueForm').onsubmit = function() {
        document.getElementById('descriptionInput').value = quill.root.innerHTML;
    };
</script>
