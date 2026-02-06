<?php
// Fallback to empty strings if not set
$user = $_SESSION['dispatched_user'] ?? [];

$dispatched_username = $user['username'] ?? '';
$dispatched_display_name = $user['display_name'] ?? '';
?>

<section>
    <h2>Internal Registration</h2>
    <p><strong>Authorized Access Only</strong></p>


    <?=
    /** @var array<int,array<string,mixed> $fields */
    \App\Controllers\Controller::renderForm(fields: $fields, defaults: [
            'username' => $dispatched_username,
            'displayname' => $dispatched_display_name,
    ], isSecretRoom: true);
    ?>

    <!-- show buttons to manage users -->
    <?php if (in_array('group_admin', $_SESSION['roles'], true)): ?>
        <section style="display:flex; gap:0.5em; margin-top:1em;">
            <form action="" method="POST">
                <input type="hidden" name="action" value="admin_list_group_users">
                <button type="submit">List users</button>
            </form>

            <form action="" method="POST">
                <input type="hidden" name="action" value="admin_list_submissions">
                <button type="submit">List submissions</button>
            </form>
        </section>
    <?php endif; ?>
</section>