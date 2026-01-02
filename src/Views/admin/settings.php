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
        <label>Quill Editor Base Font Size</label>
        <input type="text" name="settings[quill_base_font_size]" class="form-control" value="<?= htmlspecialchars($settings['quill_base_font_size'] ?? '16px') ?>" placeholder="e.g. 16px or 1rem">
    </div>
    <div class="mb-3">
        <label>Allow Public Registration (Coming Soon)</label>
        <select name="settings[allow_registration]" class="form-select">
            <option value="0" <?= ($settings['allow_registration'] ?? '0') == '0' ? 'selected' : '' ?>>No</option>
            <option value="1" <?= ($settings['allow_registration'] ?? '0') == '1' ? 'selected' : '' ?>>Yes</option>
        </select>
    </div>

    <hr>
    <h4>Email Settings</h4>
    <div class="mb-3">
        <div class="form-check">
            <input type="hidden" name="settings[enable_email]" value="0">
            <input type="checkbox" class="form-check-input" name="settings[enable_email]" value="1" id="enableEmail" <?= ($settings['enable_email'] ?? '0') == '1' ? 'checked' : '' ?>>
            <label class="form-check-label" for="enableEmail">Enable Email Notifications</label>
        </div>
        
        <div class="ms-4 mt-2" id="emailSubOptions" style="<?= ($settings['enable_email'] ?? '0') == '1' ? '' : 'display: none;' ?>">
            <div class="form-check">
                <input type="hidden" name="settings[send_email_comment]" value="0">
                <input type="checkbox" class="form-check-input" name="settings[send_email_comment]" value="1" id="sendEmailComment" <?= ($settings['send_email_comment'] ?? '1') == '1' ? 'checked' : '' ?>>
                <label class="form-check-label" for="sendEmailComment">Send notification on comment</label>
            </div>
            <div class="form-check">
                <input type="hidden" name="settings[send_email_assign]" value="0">
                <input type="checkbox" class="form-check-input" name="settings[send_email_assign]" value="1" id="sendEmailAssign" <?= ($settings['send_email_assign'] ?? '0') == '1' ? 'checked' : '' ?>>
                <label class="form-check-label" for="sendEmailAssign">Send notification on assign</label>
            </div>
        </div>

        <div class="form-text">
            When checked, emails will be sent out based on the selected events.
        </div>
    </div>

    <script>
        document.getElementById('enableEmail').addEventListener('change', function() {
            document.getElementById('emailSubOptions').style.display = this.checked ? 'block' : 'none';
        });
    </script>

    <div class="card card-body bg-light mb-3">
        <h5>SMTP Configuration</h5>
        <div class="row">
            <div class="col-md-8">
                <div class="mb-3">
                    <label>SMTP Host</label>
                    <input type="text" name="settings[smtp_host]" class="form-control" value="<?= htmlspecialchars($settings['smtp_host'] ?? '') ?>" placeholder="e.g. smtp.gmail.com">
                </div>
            </div>
            <div class="col-md-4">
                <div class="mb-3">
                    <label>Port</label>
                    <input type="number" name="settings[smtp_port]" class="form-control" value="<?= htmlspecialchars($settings['smtp_port'] ?? '587') ?>">
                </div>
            </div>
        </div>
        <div class="row">
            <div class="col-md-6">
                <div class="mb-3">
                    <label>SMTP Username</label>
                    <input type="text" name="settings[smtp_user]" class="form-control" value="<?= htmlspecialchars($settings['smtp_user'] ?? '') ?>">
                </div>
            </div>
            <div class="col-md-6">
                <div class="mb-3">
                    <label>SMTP Password</label>
                    <input type="password" name="settings[smtp_pass]" class="form-control" value="<?= htmlspecialchars($settings['smtp_pass'] ?? '') ?>">
                </div>
            </div>
        </div>
        <div class="row">
            <div class="col-md-6">
                <div class="mb-3">
                    <label>Encryption</label>
                    <select name="settings[smtp_encryption]" class="form-select">
                        <option value="tls" <?= ($settings['smtp_encryption'] ?? 'tls') == 'tls' ? 'selected' : '' ?>>TLS (STARTTLS)</option>
                        <option value="ssl" <?= ($settings['smtp_encryption'] ?? '') == 'ssl' ? 'selected' : '' ?>>SSL</option>
                        <option value="none" <?= ($settings['smtp_encryption'] ?? '') == 'none' ? 'selected' : '' ?>>None</option>
                    </select>
                </div>
            </div>
            <div class="col-md-6">
                <div class="mb-3">
                    <label>From Email</label>
                    <input type="email" name="settings[smtp_from]" class="form-control" value="<?= htmlspecialchars($settings['smtp_from'] ?? 'noreply@buggy.local') ?>">
                </div>
            </div>
        </div>
    </div>

    <div class="mb-3">
        <label>Test Email</label>
        <div class="input-group">
            <input type="email" id="testEmailInput" class="form-control" placeholder="Enter email address">
            <button type="button" class="btn btn-outline-secondary" id="sendTestEmailBtn">Send Test Email</button>
        </div>
        <div id="testEmailFeedback" class="mt-2"></div>
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

<script>
document.getElementById('sendTestEmailBtn').addEventListener('click', function() {
    const email = document.getElementById('testEmailInput').value;
    const feedback = document.getElementById('testEmailFeedback');
    const btn = this;

    if (!email) {
        feedback.innerHTML = '<span class="text-danger">Please enter an email address.</span>';
        return;
    }

    // Gather SMTP settings from the form to allow testing before saving
    const smtpHost = document.querySelector('input[name="settings[smtp_host]"]').value;
    const smtpPort = document.querySelector('input[name="settings[smtp_port]"]').value;
    const smtpUser = document.querySelector('input[name="settings[smtp_user]"]').value;
    const smtpPass = document.querySelector('input[name="settings[smtp_pass]"]').value;
    const smtpEncryption = document.querySelector('select[name="settings[smtp_encryption]"]').value;
    const smtpFrom = document.querySelector('input[name="settings[smtp_from]"]').value;

    btn.disabled = true;
    btn.textContent = 'Sending...';
    feedback.innerHTML = '';

    fetch('/admin/settings/test-email', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({ 
            email: email,
            smtp_host: smtpHost,
            smtp_port: smtpPort,
            smtp_user: smtpUser,
            smtp_pass: smtpPass,
            smtp_encryption: smtpEncryption,
            smtp_from: smtpFrom
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            feedback.innerHTML = '<span class="text-success">' + data.message + '</span>';
        } else {
            feedback.innerHTML = '<span class="text-danger">' + data.message + '</span>';
        }
    })
    .catch(error => {
        console.error('Error:', error);
        feedback.innerHTML = '<span class="text-danger">An unexpected error occurred.</span>';
    })
    .finally(() => {
        btn.disabled = false;
        btn.textContent = 'Send Test Email';
    });
});
</script>
