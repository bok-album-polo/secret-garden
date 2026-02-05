<section>
    <h2>Submission Details</h2>

    <?php if (!empty($submission)): ?>
        <table border="1" cellpadding="8" cellspacing="0" style="width:100%; border-collapse:collapse;">
            <tbody>
            <tr>
                <th>ID</th>
                <td><?= htmlspecialchars($submission['id']) ?></td>
            </tr>
            <tr>
                <th>Username</th>
                <td><?= htmlspecialchars($submission['username']) ?></td>
            </tr>
            <tr>
                <th>Created At</th>
                <td><?= htmlspecialchars($submission['created_at']) ?></td>
            </tr>
            <tr>
                <th>Created By</th>
                <td><?= htmlspecialchars($submission['created_by']) ?></td>
            </tr>
            <tr>
                <th>IP Address</th>
                <td><?= htmlspecialchars($submission['ip_address']) ?></td>
            </tr>
            <tr>
                <th>User Agent</th>
                <td><?= htmlspecialchars($submission['user_agent']) ?></td>
            </tr>
            <tr>
                <th>Authenticated</th>
                <td><?= $submission['authenticated'] ? 'Yes' : 'No' ?></td>
            </tr>
            <tr>
                <th>Domain</th>
                <td><?= htmlspecialchars($submission['domain']) ?></td>
            </tr>
            <tr>
                <th>Primary Email</th>
                <td><?= htmlspecialchars($submission['primary_email']) ?></td>
            </tr>
            </tbody>
        </table>

        <div style="margin-top:1em;">
            <form action="" method="POST" style="display:inline;">
                <input type="hidden" name="action" value="admin_edit_submission">
                <input type="hidden" name="id" value="<?= $submission['id'] ?>">
                <button type="submit">Edit submission</button>
            </form>
        </div>

    <?php else: ?>
        <p>No submission found.</p>
    <?php endif; ?>
</section>