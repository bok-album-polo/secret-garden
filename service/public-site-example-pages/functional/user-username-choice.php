<?php
$user = $_SESSION['dispatched_user'] ?? [];
$dispatched_username = $user['username'] ?? '';
$dispatched_display_name = $user['display_name'] ?? '';
$config = \App\controllers\Config::instance();
$password_allow_custom = $config->application_config['password_allow_custom'];
?>

<section>
    <h2><?= /** @var string $title */
        htmlspecialchars($title) ?></h2>
    <p><strong>Authorized Access Only</strong></p>

    <form action="" method="POST">
        <input type="hidden" name="action" value="user_activate">
        <input type="hidden" name="csrf_token" value="<?=$_SESSION['csrf_token']?>">
        <div style="margin-bottom:1em;">
            <label>Username</label><br>
            <input type="text" name="username"
                   value="<?= htmlspecialchars($dispatched_username, ENT_QUOTES, 'UTF-8') ?>"
                   required readonly><br>
        </div>

        <div style="margin-bottom:1em;">
            <label>Display name</label><br>
            <input type="text" name="displayname"
                   value="<?= htmlspecialchars($dispatched_display_name, ENT_QUOTES, 'UTF-8') ?>"
                   readonly><br>
        </div>

        <?php if ($password_allow_custom): ?>
            <div style="margin-bottom:1em;">
                <label>Password</label><br>
                <input type="password" name="password" required><br>
            </div>
        <?php endif; ?>

        <button type="submit">Create user</button>
    </form>

    <br/>

    <!-- Username choice block -->
    <form action="" method="POST" style="margin-bottom:1em;">
        <input type="hidden" name="action" value="username_choice">
        <input type="hidden" name="csrf_token" value="<?=$_SESSION['csrf_token']?>">
        <button type="submit">Get another username</button>
    </form>
</section>