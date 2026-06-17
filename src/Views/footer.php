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
        var globalCommentQuill = null;
        
        function showTaskDetails(taskId) {
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
                                    <div class="mt-2 ql-editor" style="padding: 0;">
                                        ${c.comment}
                                    </div>
                                </div>
                            </div>
                        `;
                    });
                    
                    var subtasksHtml = '';
                    if (subtasks.length === 0) {
                        subtasksHtml = '<div class="text-muted small py-1">No sub-tasks.</div>';
                    } else {
                        subtasks.forEach(st => {
                            var isComp = parseInt(st.is_completed) === 1;
                            var iconClass = isComp ? 'bi bi-check-square-fill text-success' : 'bi bi-square text-muted';
                            var descSpan = isComp ? `<s>${escapeHtml(st.description)}</s>` : escapeHtml(st.description);
                            subtasksHtml += `
                                <div class="d-flex align-items-center border-bottom py-2">
                                    <i class="${iconClass} me-2"></i>
                                    <span>${descSpan}</span>
                                </div>
                            `;
                        });
                    }
                    
                    var html = `
                        <div class="row">
                            <div class="col-md-8">
                                <div class="card mb-4">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <h2 class="card-title">${escapeHtml(task.title)}</h2>
                                            <div class="btn-group">
                                                <a href="/tasks/${task.id}/edit" class="btn btn-outline-primary">
                                                    <i class="bi bi-pencil"></i> Edit
                                                </a>
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
                                        <hr>
                                        <div class="card-text ql-editor" style="padding: 0;">
                                            ${task.description || ''}
                                        </div>
                                        <hr>
                                        <div class="mt-4">
                                            <h5>Sub-tasks</h5>
                                            <div id="modalSubtasksContainer" class="mb-3">
                                                ${subtasksHtml}
                                            </div>
                                        </div>
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
                    // Matches /tasks/123, but not /tasks/123/edit, /tasks/create etc.
                    var match = href.match(/^\/tasks\/(\d+)$/);
                    if (match) {
                        e.preventDefault();
                        var taskId = match[1];
                        showTaskDetails(taskId);
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
