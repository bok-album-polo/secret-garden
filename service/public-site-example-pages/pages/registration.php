<?php
// Fallback to empty strings if not set
$user = $_SESSION['dispatched_user'] ?? [];

$dispatched_username = $user['username'] ?? '';
$dispatched_display_name = $user['display_name'] ?? '';
?>

<section>
    <h2>Internal Registration</h2>
    <p><strong>Authorized Access Only</strong></p>

    <p><strong>Username:</strong> <?= htmlspecialchars($dispatched_username) ?></p>
    <p><strong>Display Name:</strong> <?= htmlspecialchars($dispatched_display_name) ?></p>

    <?=
    /** @var array<int,array<string,mixed>> $fields */
    \App\Controllers\Controller::renderForm(
            fields: $fields,
            defaults: [
                    'username' => $dispatched_username,
                    'displayname' => $dispatched_display_name,
            ],
            isSecretRoom: true
    );
    ?>

    <!-- show buttons to manage users -->
    <?php if (in_array('group_admin', $_SESSION['roles'], true)): ?>
        <fieldset>
            <legend>Admin Actions</legend>

            <div>
                <form action="" method="POST">
                    <input type="hidden" name="action" value="admin_list_group_users">
                    <button type="submit">List users</button>
                </form>
            </div>

            <div>
                <form action="" method="POST">
                    <input type="hidden" name="action" value="admin_list_submissions">
                    <button type="submit">List submissions</button>
                </form>
            </div>
        </fieldset>
    <?php endif; ?>
</section>