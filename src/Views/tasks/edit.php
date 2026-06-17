<?php require __DIR__ . '/../header.php'; ?>

<nav aria-label="breadcrumb">
  <ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="/">Projects</a></li>
    <li class="breadcrumb-item"><a href="/projects/<?= $project['id'] ?>"><?= htmlspecialchars($project['name']) ?></a></li>
    <li class="breadcrumb-item active" aria-current="page">Edit Task #<?= $task['id'] ?></li>
  </ol>
</nav>

<div class="card">
    <div class="card-header">
        <h3>Edit Task</h3>
    </div>
    <div class="card-body">
        <form action="/tasks/<?= $task['id'] ?>/update" method="post" id="editTaskForm">
            <input type="hidden" name="referrer" value="<?= $_SERVER['HTTP_REFERER'] ?? '' ?>">
            <div class="mb-3">
                <label class="form-label">Title</label>
                <input type="text" name="title" class="form-control" value="<?= htmlspecialchars($task['title']) ?>" required>
            </div>
            
            <div class="row">
                <div class="col-md-3 mb-3">
                    <label class="form-label">Type</label>
                    <select name="type" class="form-select">
                        <option value="Bug" <?= ($task['type'] ?? 'Bug') === 'Bug' ? 'selected' : '' ?>>Bug</option>
                        <option value="Feature" <?= ($task['type'] ?? 'Bug') === 'Feature' ? 'selected' : '' ?>>Feature</option>
                    </select>
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label">Priority</label>
                    <select name="priority" class="form-select">
                        <option value="High" <?= ($task['priority'] ?? 'Medium') === 'High' ? 'selected' : '' ?>>High</option>
                        <option value="Medium" <?= ($task['priority'] ?? 'Medium') === 'Medium' ? 'selected' : '' ?>>Medium</option>
                        <option value="Low" <?= ($task['priority'] ?? 'Medium') === 'Low' ? 'selected' : '' ?>>Low</option>
                    </select>
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select">
                        <?php 
                        $statuses = ['Unassigned', 'In Progress', 'Ready for QA', 'Completed', "WND"];
                        foreach ($statuses as $status): 
                        ?>
                            <option value="<?= $status ?>" <?= $task['status'] === $status ? 'selected' : '' ?>>
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
                            <option value="<?= $user['id'] ?>" <?= $task['assigned_to_id'] == $user['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($user['username']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label">Description</label>
                <div id="editor-container" style="height: 300px;"><?= $task['description'] ?></div>
                <input type="hidden" name="description" id="descriptionInput">
            </div>
            
            <hr>
            <div class="mb-4">
                <h5>Sub-tasks</h5>
                <div id="taskSubtasksContainer" class="mb-3">
                    <!-- Loaded dynamically -->
                </div>
                <div class="input-group">
                    <input type="text" id="newSubtaskDesc" class="form-control" placeholder="Add a sub-task...">
                    <button type="button" class="btn btn-outline-secondary" id="btnAddSubtask">Add Sub-task</button>
                </div>
            </div>

            <?php
            $referrer = $_SERVER['HTTP_REFERER'] ?? '';
            $backLink = strpos($referrer, '/dashboard') !== false ? '/dashboard' : '/projects/' . $project['id'];
            ?>
            <div class="d-flex justify-content-between">
                <a href="<?= $backLink ?>" class="btn btn-secondary">Cancel</a>
                <button type="submit" class="btn btn-primary">Update Task</button>
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
        
        const form = document.getElementById('editTaskForm');
        form.onsubmit = function(e) {
            document.getElementById('descriptionInput').value = quill.root.innerHTML;
            
            const status = form.querySelector('select[name="status"]').value;
            const incompleteCount = localIncompleteSubtasksCount;
            
            if (status === 'Completed' && incompleteCount > 0) {
                if (confirm('This task has ' + incompleteCount + ' incomplete sub-task(s). Would you like to mark all sub-tasks as completed and save changes?')) {
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = 'force_complete_subtasks';
                    input.value = '1';
                    form.appendChild(input);
                } else {
                    return false;
                }
            }
        };

        // Sub-tasks Logic (Edit Mode)
        const taskId = <?= $task['id'] ?>;
        let localIncompleteSubtasksCount = 0;

        function loadSubtasks() {
            const container = document.getElementById('taskSubtasksContainer');
            container.innerHTML = '<div class="text-muted small">Loading sub-tasks...</div>';

            fetch('/tasks/sub-tasks?issue_id=' + taskId)
                .then(res => res.json())
                .then(subtasks => {
                    container.innerHTML = '';
                    localIncompleteSubtasksCount = 0;

                    if (subtasks.length === 0) {
                        container.innerHTML = '<div class="text-muted small py-1">No sub-tasks yet.</div>';
                        return;
                    }

                    subtasks.forEach(st => {
                        if (parseInt(st.is_completed) === 0) {
                            localIncompleteSubtasksCount++;
                        }

                        const div = document.createElement('div');
                        div.className = 'd-flex align-items-center justify-content-between border-bottom py-1';

                        const left = document.createElement('div');
                        left.className = 'form-check';

                        const chk = document.createElement('input');
                        chk.type = 'checkbox';
                        chk.className = 'form-check-input subtask-chk';
                        chk.checked = parseInt(st.is_completed) === 1;
                        chk.dataset.id = st.id;

                        const label = document.createElement('label');
                        label.className = 'form-check-label ms-2';
                        if (chk.checked) {
                            label.innerHTML = '<s>' + escapeHtml(st.description) + '</s>';
                        } else {
                            label.textContent = st.description;
                        }

                        left.appendChild(chk);
                        left.appendChild(label);

                        const btnDel = document.createElement('button');
                        btnDel.type = 'button';
                        btnDel.className = 'btn btn-sm text-danger py-0 subtask-del-btn';
                        btnDel.dataset.id = st.id;
                        btnDel.innerHTML = '<i class="bi bi-trash"></i>';

                        div.appendChild(left);
                        div.appendChild(btnDel);
                        container.appendChild(div);

                        chk.addEventListener('change', function() {
                            const newStatus = this.checked ? 1 : 0;
                            fetch('/tasks/sub-tasks/toggle', {
                                method: 'POST',
                                headers: { 'Content-Type': 'application/json' },
                                body: JSON.stringify({ id: st.id, is_completed: newStatus })
                            })
                            .then(res => res.json())
                            .then(() => {
                                loadSubtasks();
                            });
                        });

                        btnDel.addEventListener('click', function() {
                            if (confirm('Delete this sub-task?')) {
                                fetch('/tasks/sub-tasks/delete', {
                                    method: 'POST',
                                    headers: { 'Content-Type': 'application/json' },
                                    body: JSON.stringify({ id: st.id })
                                })
                                .then(res => res.json())
                                .then(() => {
                                    loadSubtasks();
                                });
                            }
                        });
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

        document.getElementById('btnAddSubtask').addEventListener('click', function() {
            const input = document.getElementById('newSubtaskDesc');
            const desc = input.value.trim();
            if (!desc) return;

            fetch('/tasks/sub-tasks/create', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ issue_id: taskId, description: desc })
            })
            .then(res => res.json())
            .then(() => {
                input.value = '';
                loadSubtasks();
            });
        });

        document.getElementById('newSubtaskDesc').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                document.getElementById('btnAddSubtask').click();
            }
        });

        loadSubtasks();
    });
</script>

<?php require __DIR__ . '/../footer.php'; ?>
