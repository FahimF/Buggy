    </div>
    
    <!-- Task Details Modal (Full Width, 16px Padding) -->
    <div class="modal fade" id="taskDetailsModal" tabindex="-1" aria-labelledby="taskDetailsModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-fullscreen-sm-down modal-xl" style="max-width: calc(100% - 32px); margin: 16px;">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="taskDetailsModalLabel">Task Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" style="padding: 16px;">
                    <div id="taskDetailsContent">
                        <!-- Loaded dynamically -->
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Task Edit Modal (Full Width, 16px Padding) -->
    <div class="modal fade" id="taskEditModal" tabindex="-1" aria-labelledby="taskEditModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-fullscreen-sm-down modal-xl" style="max-width: calc(100% - 32px); margin: 16px;">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="taskEditModalLabel">Edit Task</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" style="padding: 16px;">
                    <div id="taskEditContent">
                        <!-- Loaded dynamically -->
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Image Preview Modal -->
    <div class="modal fade" id="imagePreviewModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Image Preview</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body text-center p-0 bg-dark">
                    <img id="previewImage" src="" class="img-fluid" alt="Full size preview">
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@latest/Sortable.min.js"></script>
    <script src="https://cdn.quilljs.com/1.3.6/quill.js"></script>
    
    <script>
        // Global helper to show task details in a dialog
        var globalTaskDetailsModal = null;
        var globalTaskEditModal = null;
        var globalCommentQuill = null;
        var globalTaskEditQuill = null;
        
        function showTaskEdit(taskId) {
            // If details modal is open, hide it
            if (globalTaskDetailsModal) {
                globalTaskDetailsModal.hide();
            }
            
            var modalEl = document.getElementById('taskEditModal');
            if (!globalTaskEditModal) {
                globalTaskEditModal = new bootstrap.Modal(modalEl);
            }
            
            var contentDiv = document.getElementById('taskEditContent');
            contentDiv.innerHTML = '<div class="text-center my-5"><div class="spinner-border text-primary" role="status"></div><p class="mt-2">Loading task editor...</p></div>';
            globalTaskEditModal.show();
            
            fetch('/tasks/details?id=' + taskId)
                .then(res => res.json())
                .then(data => {
                    var task = data.task;
                    var users = data.users;
                    var subtasks = data.subtasks;
                    
                    var usersOptions = '<option value="">-- Unassigned --</option>';
                    users.forEach(function(u) {
                        var selected = task.assigned_to_id == u.id ? 'selected' : '';
                        usersOptions += `<option value="${u.id}" ${selected}>${escapeHtml(u.username)}</option>`;
                    });
                    
                    var subtasksHtml = '';
                    var incompleteCount = 0;
                    subtasks.forEach(function(st) {
                        var isComp = parseInt(st.is_completed) === 1;
                        if (!isComp) incompleteCount++;
                        var checked = isComp ? 'checked' : '';
                        var labelContent = isComp ? `<s>${escapeHtml(st.description)}</s>` : escapeHtml(st.description);
                        subtasksHtml += `
                            <div class="d-flex align-items-center justify-content-between border-bottom py-1">
                                <div class="form-check">
                                    <input type="checkbox" class="form-check-input modal-subtask-chk" ${checked} data-id="${st.id}">
                                    <label class="form-check-label ms-2">${labelContent}</label>
                                </div>
                                <button type="button" class="btn btn-sm text-danger py-0 modal-subtask-del-btn" data-id="${st.id}">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </div>
                        `;
                    });
                    
                    var html = `
                        <form action="/tasks/${task.id}/update" method="post" id="modalEditTaskForm">
                            <input type="hidden" name="referrer" value="${window.location.href}">
                            <div class="mb-3">
                                <label class="form-label">Title</label>
                                <input type="text" name="title" class="form-control" value="${escapeHtml(task.title)}" required>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-3 mb-3">
                                    <label class="form-label">Type</label>
                                    <select name="type" class="form-select">
                                        <option value="Bug" ${task.type === 'Bug' ? 'selected' : ''}>Bug</option>
                                        <option value="Feature" ${task.type === 'Feature' ? 'selected' : ''}>Feature</option>
                                    </select>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <label class="form-label">Priority</label>
                                    <select name="priority" class="form-select">
                                        <option value="High" ${task.priority === 'High' ? 'selected' : ''}>High</option>
                                        <option value="Medium" ${task.priority === 'Medium' ? 'selected' : ''}>Medium</option>
                                        <option value="Low" ${task.priority === 'Low' ? 'selected' : ''}>Low</option>
                                    </select>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <label class="form-label">Status</label>
                                    <select name="status" class="form-select">
                                        <option value="Unassigned" ${task.status === 'Unassigned' ? 'selected' : ''}>Unassigned</option>
                                        <option value="In Progress" ${task.status === 'In Progress' ? 'selected' : ''}>In Progress</option>
                                        <option value="Ready for QA" ${task.status === 'Ready for QA' ? 'selected' : ''}>Ready for QA</option>
                                        <option value="Completed" ${task.status === 'Completed' ? 'selected' : ''}>Completed</option>
                                        <option value="WND" ${task.status === 'WND' ? 'selected' : ''}>WND</option>
                                    </select>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <label class="form-label">Assign To</label>
                                    <select name="assigned_to" class="form-select">
                                        ${usersOptions}
                                    </select>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Description</label>
                                <div id="modal-editor-container" style="height: 250px;">${task.description || ''}</div>
                                <input type="hidden" name="description" id="modalDescriptionInput">
                            </div>
                            
                            <hr>
                            <div class="mb-4">
                                <h5>Sub-tasks</h5>
                                <div id="modalEditSubtasksContainer" class="mb-3">
                                    ${subtasksHtml}
                                </div>
                                <div class="input-group">
                                    <input type="text" id="modalNewSubtaskDesc" class="form-control" placeholder="Add a sub-task...">
                                    <button type="button" class="btn btn-outline-secondary" id="modalBtnAddSubtask">Add Sub-task</button>
                                </div>
                            </div>

                            <div class="d-flex justify-content-between">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                <button type="submit" class="btn btn-primary">Update Task</button>
                            </div>
                        </form>
                    `;
                    contentDiv.innerHTML = html;
                    
                    // Initialize Quill for Edit inside modal
                    globalTaskEditQuill = new Quill('#modal-editor-container', {
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
                    
                    var form = document.getElementById('modalEditTaskForm');
                    form.onsubmit = function() {
                        document.getElementById('modalDescriptionInput').value = globalTaskEditQuill.root.innerHTML;
                        var status = form.querySelector('select[name="status"]').value;
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
                    
                    // Subtasks inside modal handlers
                    function refreshModalSubtasks() {
                        fetch('/tasks/sub-tasks?issue_id=' + task.id)
                            .then(res => res.json())
                            .then(newSubtasks => {
                                var container = document.getElementById('modalEditSubtasksContainer');
                                container.innerHTML = '';
                                incompleteCount = 0;
                                newSubtasks.forEach(st => {
                                    var isComp = parseInt(st.is_completed) === 1;
                                    if (!isComp) incompleteCount++;
                                    var div = document.createElement('div');
                                    div.className = 'd-flex align-items-center justify-content-between border-bottom py-1';
                                    div.innerHTML = `
                                        <div class="form-check">
                                            <input type="checkbox" class="form-check-input modal-subtask-chk" ${isComp ? 'checked' : ''} data-id="${st.id}">
                                            <label class="form-check-label ms-2">${isComp ? '<s>' + escapeHtml(st.description) + '</s>' : escapeHtml(st.description)}</label>
                                        </div>
                                        <button type="button" class="btn btn-sm text-danger py-0 modal-subtask-del-btn" data-id="${st.id}">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    `;
                                    container.appendChild(div);
                                    
                                    // bind toggle
                                    div.querySelector('.modal-subtask-chk').addEventListener('change', function() {
                                        fetch('/tasks/sub-tasks/toggle', {
                                            method: 'POST',
                                            headers: { 'Content-Type': 'application/json' },
                                            body: JSON.stringify({ id: st.id, is_completed: this.checked ? 1 : 0 })
                                        }).then(() => refreshModalSubtasks());
                                    });
                                    // bind delete
                                    div.querySelector('.modal-subtask-del-btn').addEventListener('click', function() {
                                        if (confirm('Delete this sub-task?')) {
                                            fetch('/tasks/sub-tasks/delete', {
                                                method: 'POST',
                                                headers: { 'Content-Type': 'application/json' },
                                                body: JSON.stringify({ id: st.id })
                                            }).then(() => refreshModalSubtasks());
                                        }
                                    });
                                });
                            });
                    }
                    
                    // Initial binds
                    document.querySelectorAll('.modal-subtask-chk').forEach(function(chk) {
                        chk.addEventListener('change', function() {
                            fetch('/tasks/sub-tasks/toggle', {
                                method: 'POST',
                                headers: { 'Content-Type': 'application/json' },
                                body: JSON.stringify({ id: this.dataset.id, is_completed: this.checked ? 1 : 0 })
                            }).then(() => refreshModalSubtasks());
                        });
                    });
                    
                    document.querySelectorAll('.modal-subtask-del-btn').forEach(function(btn) {
                        btn.addEventListener('click', function() {
                            if (confirm('Delete this sub-task?')) {
                                fetch('/tasks/sub-tasks/delete', {
                                    method: 'POST',
                                    headers: { 'Content-Type': 'application/json' },
                                    body: JSON.stringify({ id: this.dataset.id })
                                }).then(() => refreshModalSubtasks());
                            }
                        });
                    });
                    
                    document.getElementById('modalBtnAddSubtask').addEventListener('click', function() {
                        var input = document.getElementById('modalNewSubtaskDesc');
                        var desc = input.value.trim();
                        if (!desc) return;
                        fetch('/tasks/sub-tasks/create', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({ issue_id: task.id, description: desc })
                        }).then(() => {
                            input.value = '';
                            refreshModalSubtasks();
                        });
                    });
                    
                    document.getElementById('modalNewSubtaskDesc').addEventListener('keypress', function(e) {
                        if (e.key === 'Enter') {
                            e.preventDefault();
                            document.getElementById('modalBtnAddSubtask').click();
                        }
                    });
                });
        }
        
        function showTaskDetails(taskId) {
            // If edit modal is open, hide it
            if (globalTaskEditModal) {
                globalTaskEditModal.hide();
            }
            
            var modalEl = document.getElementById('taskDetailsModal');
            if (!globalTaskDetailsModal) {
                globalTaskDetailsModal = new bootstrap.Modal(modalEl);
            }
            
            var contentDiv = document.getElementById('taskDetailsContent');
            contentDiv.innerHTML = '<div class="text-center my-5"><div class="spinner-border text-primary" role="status"></div><p class="mt-2">Loading task details...</p></div>';
            globalTaskDetailsModal.show();
            
            fetch('/tasks/details?id=' + taskId)
                .then(res => res.json())
                .then(data => {
                    var task = data.task;
                    var comments = data.comments;
                    var subtasks = data.subtasks;
                    var currentUserId = data.current_user_id;
                    var isAdmin = data.is_admin;
                    
                    var priorityClass = 'bg-secondary';
                    if (task.priority === 'High') priorityClass = 'bg-danger';
                    else if (task.priority === 'Medium') priorityClass = 'bg-warning text-dark';
                    else if (task.priority === 'Low') priorityClass = 'bg-success';
                    
                    var typeBadge = task.type === 'Bug' ? '<span class="badge bg-danger">Bug</span>' : '<span class="badge bg-info text-dark">Feature</span>';
                    
                    var statusBadgeClass = 'bg-secondary';
                    if (task.status === 'Unassigned') statusBadgeClass = 'bg-secondary';
                    else if (task.status === 'In Progress') statusBadgeClass = 'bg-warning text-dark';
                    else if (task.status === 'Ready for QA') statusBadgeClass = 'bg-orange';
                    else if (task.status === 'Completed') statusBadgeClass = 'bg-success';
                    else if (task.status === 'WND') statusBadgeClass = 'bg-danger';
                    
                    var commentsHtml = '';
                    comments.forEach(function(c) {
                        var commentActions = '';
                        if (currentUserId == c.user_id) {
                            commentActions += `<a href="/comments/${c.id}/edit" class="btn btn-sm btn-outline-secondary py-0 px-1" title="Edit"><i class="bi bi-pencil"></i></a>`;
                        }
                        if (currentUserId == c.user_id || isAdmin) {
                            commentActions += `
                                <form action="/comments/${c.id}/delete" method="POST" class="d-inline ms-1" onsubmit="return confirm('Are you sure you want to delete this comment?');">
                                    <button type="submit" class="btn btn-sm btn-outline-danger py-0 px-1" title="Delete"><i class="bi bi-trash"></i></button>
                                </form>
                            `;
                        }
                        
                        commentsHtml += `
                            <div class="card mb-3">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <strong>${escapeHtml(c.username)}</strong>
                                        <div class="d-flex align-items-center gap-2">
                                            <small class="text-muted">${c.created_at}</small>
                                            ${commentActions}
                                        </div>
                                    </div>
                                    <div class="mt-2 ql-editor" style="padding: 0;">${c.comment}</div>
                                </div>
                            </div>
                        `;
                    });
                    
                    var subtasksSection = '';
                    if (subtasks && subtasks.length > 0) {
                        var subtasksHtml = '';
                        subtasks.forEach(st => {
                            var isComp = parseInt(st.is_completed) === 1;
                            var checked = isComp ? 'checked' : '';
                            var labelClass = isComp ? 'text-decoration-line-through text-muted' : '';
                            subtasksHtml += `
                                <div class="d-flex align-items-center border-bottom py-2">
                                    <div class="form-check m-0">
                                        <input type="checkbox" class="form-check-input detail-subtask-chk" ${checked} data-id="${st.id}">
                                        <label class="form-check-label ms-2 ${labelClass}">${escapeHtml(st.description)}</label>
                                    </div>
                                </div>
                            `;
                        });
                        subtasksSection = `
                            <hr>
                            <div class="mt-4">
                                <h5>Sub-tasks</h5>
                                <div id="modalSubtasksContainer" class="mb-3">
                                    ${subtasksHtml}
                                </div>
                            </div>
                        `;
                    }
                    
                    var html = `
                        <div class="row">
                            <div class="col-md-8">
                                <div class="card mb-4">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <h2 class="card-title">${escapeHtml(task.title)}</h2>
                                            <div class="btn-group">
                                                <button type="button" class="btn btn-outline-primary modal-task-edit-trigger" data-id="${task.id}">
                                                    <i class="bi bi-pencil"></i> Edit
                                                </button>
                                                <form action="/tasks/${task.id}/delete" method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this task?');">
                                                    <button type="submit" class="btn btn-outline-danger">
                                                        <i class="bi bi-trash"></i> Delete
                                                    </button>
                                                </form>
                                            </div>
                                        </div>
                                        <div class="mb-3 text-muted small">
                                            Created by <strong>${escapeHtml(task.creator_name)}</strong> on ${task.created_at}
                                        </div>
                                        <div class="card-text ql-editor" style="padding: 0;">${(task.description || '').replace(/^(?:<p>\s*<br\s*\/?>\s*<\/p>|<p>\s*<\/p>|\s)+/gi, '').replace(/(?:<p>\s*<br\s*\/?>\s*<\/p>|<p>\s*<\/p>|\s)+$/gi, '')}</div>
                                        ${subtasksSection}
                                    </div>
                                </div>
                                
                                <div class="d-flex align-items-center mb-3">
                                    <h4 class="mb-0">Comments</h4>
                                    <span class="badge bg-secondary ms-2">${comments.length}</span>
                                </div>
                                
                                ${commentsHtml}
                                
                                <div class="card">
                                    <div class="card-header">Add Comment</div>
                                    <div class="card-body">
                                        <form action="/comments/create" method="post" id="modalAddCommentForm">
                                            <input type="hidden" name="task_id" value="${task.id}">
                                            <div class="mb-3">
                                                <div id="modal-comment-editor" style="height: 150px;"></div>
                                                <input type="hidden" name="comment" id="modalCommentInput">
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
                                            <span class="badge ${priorityClass}">${escapeHtml(task.priority || 'Medium')}</span>
                                        </li>
                                        <li class="list-group-item">
                                            <strong>Type:</strong>
                                            ${typeBadge}
                                        </li>
                                        <li class="list-group-item">
                                            <strong>Status:</strong>
                                            <span class="badge ${statusBadgeClass}">${escapeHtml(task.status)}</span>
                                        </li>
                                        <li class="list-group-item">
                                            <strong>Assigned To:</strong>
                                            ${task.assigned_to_name ? escapeHtml(task.assigned_to_name) : '<span class="text-muted">Unassigned</span>'}
                                        </li>
                                        <li class="list-group-item">
                                            <strong>Updated:</strong>
                                            ${task.updated_at}
                                        </li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    `;
                    contentDiv.innerHTML = html;
                    
                    // Bind edit trigger in details modal
                    contentDiv.querySelector('.modal-task-edit-trigger').addEventListener('click', function() {
                        showTaskEdit(this.dataset.id);
                    });
                    
                    // Initialize Quill Editor inside Modal
                    globalCommentQuill = new Quill('#modal-comment-editor', {
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
                    
                    document.getElementById('modalAddCommentForm').onsubmit = function() {
                        var htmlContent = globalCommentQuill.root.innerHTML;
                        if (globalCommentQuill.getText().trim().length === 0 && !htmlContent.includes('<img')) {
                            alert('Please write a comment.');
                            return false;
                        }
                        document.getElementById('modalCommentInput').value = htmlContent;
                    };
                    
                    // Attach preview handlers to images inside modal content
                    attachImageListeners();
                    
                    // Bind change event to details sub-task checkboxes
                    contentDiv.querySelectorAll('.detail-subtask-chk').forEach(function(chk) {
                        chk.addEventListener('change', function() {
                            var subtaskId = this.dataset.id;
                            var newStatus = this.checked ? 1 : 0;
                            var label = this.nextElementSibling;
                            
                            if (newStatus === 1) {
                                label.classList.add('text-decoration-line-through', 'text-muted');
                            } else {
                                label.classList.remove('text-decoration-line-through', 'text-muted');
                            }
                            
                            fetch('/tasks/sub-tasks/toggle', {
                                method: 'POST',
                                headers: { 'Content-Type': 'application/json' },
                                body: JSON.stringify({ id: subtaskId, is_completed: newStatus })
                            });
                        });
                    });
                });
        }
        
        function escapeHtml(text) {
            if (!text) return '';
            return text
                .toString()
                .replace(/&/g, "&amp;")
                .replace(/</g, "&lt;")
                .replace(/>/g, "&gt;")
                .replace(/"/g, "&quot;")
                .replace(/'/g, "&#039;");
        }

        document.addEventListener('DOMContentLoaded', function() {
            // Intercept all task detail links and route them to showTaskDetails dialog
            document.body.addEventListener('click', function(e) {
                var taskLink = e.target.closest('a[href^="/tasks/"]');
                if (taskLink) {
                    var href = taskLink.getAttribute('href');
                    var match = href.match(/^\/tasks\/(\d+)$/);
                    if (match) {
                        e.preventDefault();
                        var taskId = match[1];
                        showTaskDetails(taskId);
                    }
                    var matchEdit = href.match(/^\/tasks\/(\d+)\/edit$/);
                    if (matchEdit) {
                        e.preventDefault();
                        var taskId = matchEdit[1];
                        showTaskEdit(taskId);
                    }
                }
            });

            // Initialize the Bootstrap modal
            var imageModalElement = document.getElementById('imagePreviewModal');
            if (imageModalElement) {
                var imageModal = new bootstrap.Modal(imageModalElement);
                var previewImage = document.getElementById('previewImage');

                // Function to attach click listeners to images
                window.attachImageListeners = function() {
                    // Select all images in card text (descriptions) and quill editors (comments)
                    var images = document.querySelectorAll('.card-text img, .ql-editor img');
                    
                    images.forEach(function(img) {
                        // Avoid double-binding
                        if (!img.dataset.hasPreviewListener) {
                            img.dataset.hasPreviewListener = 'true';
                            img.addEventListener('click', function() {
                                previewImage.src = this.src;
                                imageModal.show();
                            });
                        }
                    });
                };

                // Initial attachment
                attachImageListeners();
            }
        });
    </script>
</body>
</html>
