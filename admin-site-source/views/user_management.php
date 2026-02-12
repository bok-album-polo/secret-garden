<?php

/**
 * @var array $users
 * @var array $user
 * @var array $currentUserRoles
 * @var string $search
 *
 **/

use App\Models\UserRole;


?>

<div class="container mt-5">
    <h1 class="mb-4">User Management</h1>

    <form class="row g-3 mb-4" method="get" action="index.php">
        <input type="hidden" name="route" value="user_management">
        <div class="col-md-4">
            <input type="text" name="search" class="form-control" placeholder="Search username"
                   value="<?= htmlspecialchars($search) ?>">
        </div>
        <div class="col-md-2">
            <button class="btn btn-primary" type="submit">Search</button>
        </div>
    </form>

    <div class="table-responsive">
        <table class="table table-bordered table-striped">
            <thead class="table-dark">
            <tr>
                <th>Username</th>
                <th>Authenticated</th>
                <th>Activated At</th>
                <th>Roles</th>
                <th style="width: 25%;">Actions</th>
            </tr>
            </thead>
            <tbody>
            <?php
            foreach ($users as $user): ?>
                <tr>
                    <td><?= htmlspecialchars($user['username']) ?></td>
                    <td><?= $user['authenticated'] ? 'Yes' : 'No' ?></td>
                    <td class="utc-date" data-utc="<?= htmlspecialchars($user['activated_at'] ?? '') ?>">
                        <?php if (!empty($user['activated_at'])): ?>
                            <?php
                            $dt = new DateTime($user['activated_at'], new DateTimeZone('UTC'));
                            echo $dt->format('Y-m-d H:i') . ' UTC';
                            ?>
                        <?php else: ?>
                            —
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php foreach ($user['roles'] as $role): ?>
                            <span class="badge bg-secondary"><?= htmlspecialchars($role) ?></span>
                        <?php endforeach; ?>
                    </td>
                    <td>
                        <!-- Reset Password (Admin+) -->
                        <button class="btn btn-sm btn-warning me-2" data-bs-toggle="modal"
                                data-bs-target="#resetPasswordModal"
                                data-username="<?= htmlspecialchars($user['username']) ?>">
                            Reset Password
                        </button>

                        <!-- Manage Roles (Superadmin only) -->
                        <?php if (UserRole::hasPermission($currentUserRoles, UserRole::ADMIN)): ?>
                            <form method="post" action="index.php?route=activate_user" style="display:inline;">
                                <input type="hidden" name="username" value="<?= $user['username'] ?>">
                                <button class="btn btn-sm btn-info">Activate</button>
                            </form>
                        <?php endif; ?>

                        <?php if (UserRole::hasPermission($currentUserRoles, UserRole::SUPERADMIN)): ?>

                            <button class="btn btn-sm btn-primary" data-bs-toggle="modal"
                                    data-bs-target="#manageRolesModal"
                                    data-username="<?= htmlspecialchars($user['username']) ?>"
                                    data-roles='<?= json_encode($user['roles']) ?>'>
                                Manage Roles
                            </button>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Reset Password Modal -->
<div class="modal fade" id="resetPasswordModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post" action="index.php?route=user_reset_password">
                <div class="modal-header">
                    <h5 class="modal-title">Reset Password</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="username" id="resetUsername">
                    <div class="mb-3">
                        <label class="form-label">New Password</label>
                        <div class="input-group">
                            <input type="text" class="form-control" name="password" id="newPassword" required>
                            <button class="btn btn-outline-secondary" type="button" onclick="generatePassword()">
                                Generate
                            </button>
                        </div>
                        <div class="form-text">Click Generate to create a random 8-digit number.</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Password</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Manage Roles Modal -->
<div class="modal fade" id="manageRolesModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post" action="index.php?route=user_update_roles">
                <div class="modal-header">
                    <h5 class="modal-title">Manage Roles</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="username" id="rolesUsername">
                    <p>Select roles for <strong id="rolesUsernameDisplay"></strong>:</p>

                    <?php foreach (UserRole::getAll() as $role): ?>
                        <div class="form-check">
                            <input class="form-check-input role-checkbox" type="checkbox" name="roles[]"
                                   value="<?= $role ?>" id="role_<?= $role ?>">
                            <label class="form-check-label" for="role_<?= $role ?>">
                                <?= ucfirst($role) ?>
                            </label>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Roles</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    const resetModal = document.getElementById('resetPasswordModal');
    resetModal.addEventListener('show.bs.modal', function (event) {
        const button = event.relatedTarget;
        document.getElementById('resetUsername').value = button.getAttribute('data-username');
        generatePassword(); // Auto-generate on open
    });

    const rolesModal = document.getElementById('manageRolesModal');
    rolesModal.addEventListener('show.bs.modal', function (event) {
        const button = event.relatedTarget;
        const username = button.getAttribute('data-username');
        const roles = JSON.parse(button.getAttribute('data-roles'));

        document.getElementById('rolesUsername').value = username;
        document.getElementById('rolesUsernameDisplay').textContent = username;

        // Reset checkboxes
        document.querySelectorAll('.role-checkbox').forEach(cb => cb.checked = false);

        // Check active roles
        roles.forEach(role => {
            const cb = document.getElementById('role_' + role);
            if (cb) cb.checked = true;
        });
    });

    function generatePassword() {
        // Generate 8 digit number
        document.getElementById('newPassword').value = Math.floor(10000000 + Math.random() * 90000000);
    }
</script>

