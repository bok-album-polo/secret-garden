<section>
    <h2><?= htmlspecialchars($title ?? 'Manage submissions') ?></h2>

    <p style="font-weight: bold;color: #ff211a"><?= $_SESSION['flash_message'] ?? '' ?></p>
    <table border="1" cellpadding="4" cellspacing="0" style="width:100%; border-collapse:collapse;">
        <thead>
        <tr>
            <th>ID</th>
            <th>Username</th>
            <th>Primary Email</th>
            <th>Domain</th>
            <th>Created By</th>
            <th>IP Address</th>
            <th>Authenticated</th>
            <th style="width: 30%">Actions</th>
        </tr>
        </thead>
        <tbody>
        <?php if (!empty($submissions)): ?>
            <?php foreach ($submissions as $submission): ?>
                <tr>
                    <td><?= htmlspecialchars($submission['id']) ?></td>
                    <td><?= htmlspecialchars($submission['username']) ?></td>
                    <td><?= htmlspecialchars($submission['primary_email'] ?? '') ?></td>
                    <td><?= htmlspecialchars($submission['domain'] ?? '') ?></td>
                    <td><?= htmlspecialchars($submission['created_by'] ?? '') ?></td>
                    <td><?= htmlspecialchars($submission['ip_address'] ?? '') ?></td>
                    <td>
                        <?= ($submission['authenticated'] ?? false) ? 'Yes' : 'No' ?>
                    </td>
                    <td>
                        <!-- admin_authenticate_submission -->
                        <form action="" method="POST" style="display:inline;">
                            <input type="hidden" name="action" value="admin_authenticate_submission">
                            <input type="hidden" name="id" value="<?= $submission['id'] ?>">
                            <button type="submit">Authenticate submission</button>
                        </form>

                        <form action="" method="POST" style="display:inline;">
                            <input type="hidden" name="action" value="admin_view_submission">
                            <input type="hidden" name="username" value="<?= $submission['username'] ?>">
                            <button type="submit">View submission</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php else: ?>
            <tr>
                <td colspan="6">No submissions found.</td>
            </tr>
        <?php endif; ?>
        </tbody>
    </table>
</section>