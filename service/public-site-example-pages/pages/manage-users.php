<section>
    <h2><?= htmlspecialchars($title) ?></h2>
    <p><strong>Domain:</strong> <?= htmlspecialchars($domain ?? 'N/A') ?></p>

    <p style="font-weight: bold;color: #ff211a"><?= $_SESSION['flash_message'] ?? '' ?></p>
    <table border="1" cellpadding="4" cellspacing="0" style="width:100%; border-collapse:collapse;">
        <thead>
        <tr>
            <th>Username</th>
            <th>Display Name</th>
            <th>Domain</th>
            <th>Roles</th>
            <th>Actions</th>
        </tr>
        </thead>
        <tbody>
        <?php if (!empty($users)): ?>
            <?php foreach ($users as $user): ?>
                <tr>
                    <td><?= htmlspecialchars($user['username']) ?></td>
                    <td><?= htmlspecialchars($user['displayname'] ?? '') ?></td>
                    <td><?= htmlspecialchars($user['domain'] ?? '') ?></td>
                    <td><?= htmlspecialchars($user['roles'] ?? '') ?></td>
                    <td>
                        <!-- Reset password -->
                        <form action="" method="POST" style="display:inline;">
                            <input type="hidden" name="action" value="reset_password">
                            <input type="hidden" name="username" value="<?= $user['username'] ?>">
                            <button type="submit">Reset Password</button>
                        </form>

                        <!-- Deactivate user -->
                        <form action="" method="POST" style="display:inline;">
                            <input type="hidden" name="action" value="deactivate_user">
                            <input type="hidden" name="username" value="<?= $user['username'] ?>">
                            <button type="submit">Toggle Authentication</button>
                        </form>

                        <!-- Promote/Demote roles -->
                        <form action="" method="POST" style="display:inline;">
                            <input type="hidden" name="action" value="toggle_role">
                            <input type="hidden" name="username" value="<?= $user['username'] ?>">
                            <button type="submit">Toggle Group Admin</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php else: ?>
            <tr>
                <td colspan="6">No users found in this domain.</td>
            </tr>
        <?php endif; ?>
        </tbody>
    </table>
</section>