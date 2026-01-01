<div class="modal fade" id="createIssueModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form action="/issues/create" method="post" id="createIssueForm">
                <input type="hidden" name="project_id" id="createIssueProjectId" value="<?= isset($project) ? $project['id'] : '' ?>">
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
                        <div class="col-md-3 mb-3">
                            <label>Type</label>
                            <select name="type" id="createIssueType" class="form-select">
                                <option value="Bug">Bug</option>
                                <option value="Feature">Feature</option>
                            </select>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label>Priority</label>
                            <select name="priority" class="form-select">
                                <option value="High">High</option>
                                <option value="Medium" selected>Medium</option>
                                <option value="Low">Low</option>
                            </select>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label>Status</label>
                            <select name="status" class="form-select">
                                <option value="Unassigned">Unassigned</option>
                                <option value="In Progress">In Progress</option>
                                <option value="Ready for QA">Ready for QA</option>
                                <option value="Completed">Completed</option>
                                <option value="WND">WND</option>
                            </select>
                        </div>
                        <div class="col-md-3 mb-3">
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
        
        document.getElementById('createIssueForm').onsubmit = function() {
            document.getElementById('descriptionInput').value = quill.root.innerHTML;
        };
    });
</script>
