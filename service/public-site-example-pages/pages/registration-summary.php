<!-- pages/registration-success.php -->

<div>
    <h2>Summary</h2>

    <?php if (!empty($generated_password)): ?>
        <fieldset>
            <legend>Generated Password</legend>
            <p>🔒 <strong><?= htmlspecialchars($generated_password) ?></strong></p>
            <p>⚠️ Copy and save this password securely. It will not be shown again.</p>
        </fieldset>
    <?php endif; ?>

    <table border="1" cellpadding="4" cellspacing="0" width="100%">
        <tr>
            <th>Username</th>
            <td><?= htmlspecialchars($username ?? '') ?></td>
        </tr>
        <tr>
            <th>Display Name</th>
            <td><?= htmlspecialchars($displayname ?? '') ?></td>
        </tr>
        <tr>
            <th>Authenticated</th>
            <td>
                <?= ($authenticated ?? false) ? 'Yes' : 'No' ?>
            </td>
        </tr>
    </table>

    <form method="POST">
        <input type="hidden" name="action" value="login">
        <p>
            <button type="submit">Proceed to secret_room form</button>
        </p>
    </form>
</div>