<?php require __DIR__ . '/../header.php'; ?>

<div class="row">
    <div class="col-md-3">
        <div class="list-group">
            <a href="/admin/users" class="list-group-item list-group-item-action <?= $view === 'users' ? 'active' : '' ?>">
                <i class="bi bi-people"></i> Users
            </a>
            <a href="/admin/inbox" class="list-group-item list-group-item-action <?= $view === 'inbox' ? 'active' : '' ?>">
                <i class="bi bi-inbox"></i> Inbox Items
            </a>
            <a href="/admin/logs" class="list-group-item list-group-item-action <?= $view === 'logs' ? 'active' : '' ?>">
                <i class="bi bi-journal-text"></i> System Logs
            </a>
            <a href="/admin/settings" class="list-group-item list-group-item-action <?= $view === 'settings' ? 'active' : '' ?>">
                <i class="bi bi-gear"></i> Settings
            </a>
        </div>
    </div>
    <div class="col-md-9">
        <?php 
        if (isset($view)) {
            require __DIR__ . '/' . $view . '.php'; 
        }
        ?>
    </div>
</div>

<?php require __DIR__ . '/../footer.php'; ?>
