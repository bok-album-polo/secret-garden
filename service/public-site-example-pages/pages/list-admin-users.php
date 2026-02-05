<section>
    <h2><?= htmlspecialchars($title ?? 'Manage Users') ?></h2>

    <p style="font-weight: bold;color: #ff211a"><?= $_SESSION['flash_message'] ?? '' ?></p>
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
                            <input type="hidden" name="action" value="reset_password">
                            <input type="hidden" name="username" value="<?= $user['username'] ?>">
                            <button type="submit">Reset Password</button>
                        </form>

                        <!-- changes the user authentication status -->
                        <form action="" method="POST" style="display:inline;">
                            <input type="hidden" name="action" value="deactivate_user">
                            <input type="hidden" name="username" value="<?= $user['username'] ?>">
                            <button type="submit">Change authentication status</button>
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