<?php
// Fallback to empty strings if not set
$user = $_SESSION['dispatched_user'] ?? [];

$dispatched_username = $user['username'] ?? '';
$dispatched_display_name = $user['display_name'] ?? '';
?>

<section>
    <h2>Internal Registration</h2>
    <p><strong>Authorized Access Only</strong></p>

    <form action="" method="POST" enctype="multipart/form-data">
        <?=
        /** @var array<int,array<string,mixed> $fields */
        \App\Controllers\Controller::renderFields($fields, [
                'username' => $dispatched_username,
                'displayname' => $dispatched_display_name,
        ]);
        ?>

        <div>
            <button type="submit">Submit</button>
            <br>
        </div>
    </form>
</section>

<section>
    <?php if (!empty($showManage)): ?>
        <?php include __DIR__ . '/manage-users.php'; ?>
    <?php endif; ?>
</section>