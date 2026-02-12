<?php
/** @var array $submission */
/** @var bool $form_readonly */


if ($submission ?? null) {
    $defaults = $submission;
    $dispatched_username = $submission['username'] ?? '';
    $dispatched_display_name = $submission['display_name'] ?? '';
} else {
    $user = $_SESSION['dispatched_user'] ?? [];
    $dispatched_username = $user['username'] ?? '';
    $dispatched_display_name = $user['display_name'] ?? '';
    $defaults = [
            'username' => $dispatched_username,
            'displayname' => $dispatched_display_name,
    ];
    $form_readonly = false;
}
?>

<section>
    <h2>Internal Registration</h2>
    <p><strong>Authorized Access Only</strong></p>

    <p><strong>Username:</strong> <?= htmlspecialchars($dispatched_username) ?></p>
    <p><strong>Display Name:</strong> <?= htmlspecialchars($dispatched_display_name) ?></p>

    <?php if ($_SESSION['user_logged_in']): ?>
        <form action="" method="POST" style="display:inline;">
            <input type="hidden" name="action" value="user_logout">
            <input type="hidden" name="csrf_token" value="<?=$_SESSION['csrf_token']?>">
            <button type="submit">Logout</button>
        </form>
    <?php endif; ?>

    <?=
    /** @var array<int,array<string,mixed>> $fields */
    \App\Controllers\Controller::renderForm(
            fields: $fields,
            defaults: $defaults,
            isSecretRoom: true,
            target_username: $dispatched_username,
            form_readonly: $form_readonly
    );
    ?>

    <!-- show buttons to manage users -->
    <?php if (\App\Controllers\Controller::isGroupAdmin($_SESSION['username'])): ?>
        <fieldset>
            <legend>Admin Actions</legend>

            <form action="" method="POST" style="display:inline;">
                <input type="hidden" name="action" value="admin_list_group_users">
                <input type="hidden" name="csrf_token" value="<?=$_SESSION['csrf_token']?>">
                <button type="submit">List users</button>
            </form>

            <form action="" method="POST" style="display:inline;">
                <input type="hidden" name="action" value="admin_list_submissions">
                <input type="hidden" name="csrf_token" value="<?=$_SESSION['csrf_token']?>">
                <button type="submit">List submissions</button>
            </form>
        </fieldset>
    <?php endif; ?>
</section>