<!-- pages/registration-success.php -->

<div class="summary-container">
    <div class="summary-header">
        <h2>✓ Registration Summary</h2>
        <p>Your submission has been successfully processed</p>
    </div>

    <?php if (!empty($generated_password)): ?>
        <div class="password-alert">
            <h3>🔒 Your Generated Password</h3>
            <div class="password-display">
                <strong><?= htmlspecialchars($generated_password) ?></strong>
            </div>
            <small>⚠️ Copy and save this password securely. It will not be shown again.</small>
        </div>
    <?php endif; ?>

    <table class="summary-table">
        <tr>
            <th>Username</th>
            <td><?= htmlspecialchars($username ?? '') ?></td>
        </tr>
        <tr>
            <th>Display Name</th>
            <td><?= htmlspecialchars($displayname ?? '') ?></td>
        </tr>
        <tr>
            <th>Email</th>
            <td><?= htmlspecialchars($email ?? '') ?></td>
        </tr>
        <tr>
            <th>Authenticated</th>
            <td>
                <span class="badge <?= ($authenticated ?? false) ? 'badge-yes' : 'badge-no' ?>">
                    <?= ($authenticated ?? false) ? 'Yes' : 'No' ?>
                </span>
            </td>
        </tr>
        <tr>
            <th>IP Address</th>
            <td><?= htmlspecialchars($ip_address ?? '') ?></td>
        </tr>
        <tr>
            <th>User Agent</th>
            <td><?= htmlspecialchars($user_agent ?? '') ?></td>
        </tr>
    </table>
</div>