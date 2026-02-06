<section>
    <h2><?= htmlspecialchars($title ?? 'Manage Users') ?></h2>
    <table border="1" cellpadding="4" cellspacing="0" style="width:100%; border-collapse:collapse;">
        <thead>
        <tr>
            <th>Username</th>
            <th style="width: 30%">Actions</th>
        </tr>
        </thead>
        <tbody>
        <?php if (!empty($users)): ?>
            <?php foreach ($users as $user): ?>
                <tr>
                    <td><?= htmlspecialchars($user['username']) ?></td>
                    <td>
                        <!-- Reset password -->
                        <form action="" method="POST" style="display:inline;">
                            <input type="hidden" name="action" value="admin_reset_password">
                            <input type="hidden" name="username" value="<?= $user['username'] ?>">
                            <button type="submit">Reset Password</button>
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