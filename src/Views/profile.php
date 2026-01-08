<?php require __DIR__ . '/header.php'; ?>

<div class="row justify-content-center">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header">
                <h3>My Profile</h3>
            </div>
            <div class="card-body">
                <?php if (isset($_GET['saved'])): ?>
                    <div class="alert alert-success">Profile updated successfully!</div>
                <?php endif; ?>

                <form action="/profile/update" method="post">
                    <div class="mb-3">
                        <label class="form-label">Username</label>
                        <input type="text" class="form-control" value="<?= htmlspecialchars($userData['username']) ?>" disabled>
                        <div class="form-text">Username cannot be changed.</div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($userData['email'] ?? '') ?>" placeholder="user@example.com" autocomplete="email">
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Timezone</label>
                        <select name="timezone" class="form-select">
                            <?php 
                            $currentTimezone = $userData['timezone'] ?? 'UTC';
                            foreach (DateTimeZone::listIdentifiers() as $tz): 
                            ?>
                                <option value="<?= $tz ?>" <?= $currentTimezone === $tz ? 'selected' : '' ?>>
                                    <?= $tz ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text">Your local time is used for task due dates and reminders.</div>
                    </div>

                    <hr>

                    <div class="mb-3">
                        <label class="form-label">New Password</label>
                        <div class="input-group">
                            <input type="password" name="password" id="newPassword" class="form-control" placeholder="Leave blank to keep current password" autocomplete="new-password">
                            <button class="btn btn-outline-secondary" type="button" id="togglePassword">
                                <i class="bi bi-eye"></i>
                            </button>
                        </div>
                    </div>

                    <div class="d-flex justify-content-end">
                        <button type="submit" class="btn btn-primary">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
document.getElementById('togglePassword').addEventListener('click', function() {
    const passwordInput = document.getElementById('newPassword');
    const icon = this.querySelector('i');
    
    if (passwordInput.type === 'password') {
        passwordInput.type = 'text';
        icon.classList.remove('bi-eye');
        icon.classList.add('bi-eye-slash');
    } else {
        passwordInput.type = 'password';
        icon.classList.remove('bi-eye-slash');
        icon.classList.add('bi-eye');
    }
});
</script>

<?php require __DIR__ . '/footer.php'; ?>
