<h3>Application Settings</h3>
<?php if (isset($_GET['saved'])): ?>
    <div class="alert alert-success">Settings saved!</div>
<?php endif; ?>

<form action="/admin/settings" method="post">
    <div class="mb-3">
        <label>Application Name</label>
        <input type="text" name="settings[app_name]" class="form-control" value="<?= htmlspecialchars($settings['app_name'] ?? 'Buggy') ?>">
    </div>
    <div class="mb-3">
        <label>Items per Page (Lists)</label>
        <input type="number" name="settings[items_per_page]" class="form-control" value="<?= htmlspecialchars($settings['items_per_page'] ?? '20') ?>">
    </div>
    <div class="mb-3">
        <label>Allow Public Registration (Coming Soon)</label>
        <select name="settings[allow_registration]" class="form-select">
            <option value="0" <?= ($settings['allow_registration'] ?? '0') == '0' ? 'selected' : '' ?>>No</option>
            <option value="1" <?= ($settings['allow_registration'] ?? '0') == '1' ? 'selected' : '' ?>>Yes</option>
        </select>
    </div>

    <hr>
    <h4>Auto-assign</h4>
    <div class="mb-3">
        <label>Auto-assign QA to:</label>
        <select name="settings[auto_assign_qa]" class="form-select">
            <option value="">-- None --</option>
            <?php foreach ($users as $user): ?>
                <option value="<?= $user['id'] ?>" <?= ($settings['auto_assign_qa'] ?? '') == $user['id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($user['username']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="mb-3">
        <label>Auto-assign Coding to:</label>
        <select name="settings[auto_assign_coding]" class="form-select">
            <option value="">-- None --</option>
            <?php foreach ($users as $user): ?>
                <option value="<?= $user['id'] ?>" <?= ($settings['auto_assign_coding'] ?? '') == $user['id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($user['username']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <button type="submit" class="btn btn-primary">Save Settings</button>
</form>
