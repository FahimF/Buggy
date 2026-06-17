<?php require __DIR__ . '/../header.php'; ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="d-inline"><?= htmlspecialchars($project['name']) ?> (<?= count($tasks) ?>)</h1>
        <span class="badge bg-secondary ms-2">List View</span>
    </div>
    <div>
        <div class="dropdown d-inline-block me-2">
            <button class="btn btn-outline-secondary dropdown-toggle" type="button" id="viewModeDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                <i class="bi bi-eye"></i> List View
            </button>
            <ul class="dropdown-menu" aria-labelledby="viewModeDropdown">
                <li><a class="dropdown-item active" href="/projects/<?= $project['id'] ?>?view=list">List View</a></li>
                <li><a class="dropdown-item" href="/projects/<?= $project['id'] ?>/kanban">Kanban View</a></li>
                <li><a class="dropdown-item" href="/projects/<?= $project['id'] ?>/status">Status View</a></li>
            </ul>
        </div>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createTaskModal">
            <i class="bi bi-plus-lg"></i> New Task
        </button>
    </div>
</div>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <div class="btn-group">
            <?php 
            $baseParams = $_GET;
            $hideCompletedParam = isset($hideCompleted) && $hideCompleted ? '&hide_completed=1' : '&hide_completed=0';
            $myIssuesParam = isset($onlyMyIssues) && $onlyMyIssues ? '&my_issues=1' : '';
            
            function sortLink($col, $label, $currentSort, $currentDir, $hideCompletedParam, $myIssuesParam) {
                $isActive = $currentSort === $col;
                $newDir = ($isActive && $currentDir === 'ASC') ? 'DESC' : 'ASC';
                $icon = '';
                if ($isActive) {
                    $icon = $currentDir === 'ASC' ? ' <i class="bi bi-caret-up-fill"></i>' : ' <i class="bi bi-caret-down-fill"></i>';
                }
                return '<a href="?sort=' . $col . '&dir=' . $newDir . $hideCompletedParam . $myIssuesParam . '" class="text-decoration-none text-dark">' . $label . $icon . '</a>';
            }
            ?>
            <a href="?sort=sort_order&dir=ASC<?= $hideCompletedParam . $myIssuesParam ?>" class="btn btn-sm btn-outline-secondary">Custom Order</a>
            <a href="?sort=<?= $sort ?>&dir=<?= $dir . $hideCompletedParam ?>&my_issues=<?= isset($onlyMyIssues) && $onlyMyIssues ? '0' : '1' ?>" class="btn btn-sm <?= isset($onlyMyIssues) && $onlyMyIssues ? 'btn-primary' : 'btn-outline-primary' ?>">My Tasks</a>
        </div>
        <div class="form-check form-switch m-0">
            <input class="form-check-input" type="checkbox" id="hideCompletedCheck" <?= isset($hideCompleted) && $hideCompleted ? 'checked' : '' ?> onchange="window.location.href='?sort=<?= $sort ?>&dir=<?= $dir ?>&hide_completed=' + (this.checked ? '1' : '0') + '<?= $myIssuesParam ?>'">
            <label class="form-check-label" for="hideCompletedCheck">Hide Completed</label>
        </div>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th style="width: 50px;"></th>
                        <th><?= sortLink('title', 'Title', $sort, $dir, $hideCompletedParam, $myIssuesParam) ?></th>
                        <th><?= sortLink('priority', 'Priority', $sort, $dir, $hideCompletedParam, $myIssuesParam) ?></th>
                        <th><?= sortLink('type', 'Type', $sort, $dir, $hideCompletedParam, $myIssuesParam) ?></th>
                        <th><?= sortLink('status', 'Status', $sort, $dir, $hideCompletedParam, $myIssuesParam) ?></th>
                        <th><?= sortLink('assigned_to_name', 'Assigned To', $sort, $dir, $hideCompletedParam, $myIssuesParam) ?></th>
                        <th><?= sortLink('creator_name', 'Author', $sort, $dir, $hideCompletedParam, $myIssuesParam) ?></th>
                        <th>Comments</th>
                        <th><?= sortLink('created_at', 'Created', $sort, $dir, $hideCompletedParam, $myIssuesParam) ?></th>
                    </tr>
                </thead>
                <tbody id="taskList">
                    <?php foreach ($tasks as $task): ?>
                    <tr data-id="<?= $task['id'] ?>">
                        <td class="text-center">
                            <i class="bi bi-grip-vertical handle d-block text-muted mb-2" style="cursor: move;"></i>
                            <div class="btn-group-vertical btn-group-sm">
                                <a href="/tasks/<?= $task['id'] ?>/edit" class="btn btn-outline-primary border-0 p-1" title="Edit">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                <form action="/tasks/<?= $task['id'] ?>/delete" method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this task?');">
                                    <button type="submit" class="btn btn-outline-danger border-0 p-1" title="Delete">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </form>
                            </div>
                        </td>
                        <td>
                            <a href="/tasks/<?= $task['id'] ?>" class="text-decoration-none fw-bold">
                                <?= htmlspecialchars($task['title']) ?>
                            </a>
                            <div class="description-preview">
                                <?= strip_tags($task['description']) ?>
                            </div>
                        </td>
                        <td>
                            <span class="badge <?= getPriorityBadgeClass($task['priority'] ?? 'Medium') ?>"><?= htmlspecialchars($task['priority'] ?? 'Medium') ?></span>
                        </td>
                        <td>
                            <?php if (($task['type'] ?? 'Bug') === 'Bug'): ?>
                                <span class="badge bg-danger">Bug</span>
                            <?php else: ?>
                                <span class="badge bg-info text-dark">Feature</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="badge <?= getStatusBadgeClass($task['status']) ?>">
                                <?= htmlspecialchars($task['status']) ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($task['assigned_to_name']): ?>
                                <?php 
                                $isCurrentUser = $task['assigned_to_name'] === Auth::user()['username'];
                                $badgeClass = $isCurrentUser ? 'bg-warning text-dark' : 'bg-light text-dark border';
                                ?>
                                <span class="badge rounded-pill <?= $badgeClass ?>">
                                    <?= htmlspecialchars($task['assigned_to_name']) ?>
                                </span>
                            <?php else: ?>
                                <span class="text-muted small">Unassigned</span>
                            <?php endif; ?>
                        </td>
                        <td><?= htmlspecialchars($task['creator_name']) ?></td>
                        <td>
                            <?php if ($task['comment_count'] > 0): ?>
                                <span class="badge bg-secondary rounded-pill"><?= $task['comment_count'] ?></span>
                            <?php else: ?>
                                <span class="text-muted">-</span>
                            <?php endif; ?>
                        </td>
                        <td class="small text-muted"><?= date('M j', strtotime($task['created_at'])) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Create Task Modal -->
<?php require 'create_modal.php'; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var el = document.getElementById('taskList');
    var sortable = Sortable.create(el, {
        handle: '.handle',
        animation: 150,
        onEnd: function (evt) {
            var order = [];
            el.querySelectorAll('tr').forEach(function(row) {
                order.push(row.getAttribute('data-id'));
            });
            
            fetch('/tasks/reorder', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ order: order })
            });
        }
    });
});
</script>

<?php require __DIR__ . '/../footer.php'; ?>
