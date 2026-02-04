<?php
$user = $_SESSION['dispatched_user'] ?? [];
$dispatched_username = $user['username'] ?? '';
$dispatched_display_name = $user['display_name'] ?? '';
?>

<section>
    <h2><?= /** @var string $title */
        htmlspecialchars($title) ?></h2>
    <p><strong>Authorized Access Only</strong></p>

    <?php /** @var string $action */
    if ($action === 'login'): ?>
        <!-- Login Block -->
        <form action="" method="POST">
            <input type="hidden" name="action" value="login">
            <div>
                <label>Username</label><br>
                <input type="text" name="username"
                       required><br>
            </div>
            <br>
            <div>
                <label>Password</label><br>
                <input type="password" name="password" required><br>
            </div>
            <br>
            <button type="submit">Login</button>
        </form>
        <br>

        <p>Don’t have an account?</p>
        <form action="" method="POST" style="display:inline;">
            <input type="hidden" name="action" value="register">
            <button type="submit">Register here</button>
        </form>

    <?php else: ?>
        <!-- Registration Block -->
        <form action="" method="POST" style="margin-bottom:1em;">
            <input type="hidden" name="action" value="reload_username">
            <button type="submit">Reload for new username</button>
        </form>

        <form action="" method="POST">
            <input type="hidden" name="action" value="register_submit">
            <div style="margin-bottom:1em;">
                <label>Username</label><br>
                <input type="text" name="username"
                       value="<?= htmlspecialchars($dispatched_username, ENT_QUOTES, 'UTF-8') ?>"
                       required><br>
            </div>

            <div style="margin-bottom:1em;">
                <label>Username</label><br>
                <input type="text" name="displayname"
                       value="<?= htmlspecialchars($dispatched_display_name, ENT_QUOTES, 'UTF-8') ?>"
                       readonly><br>
            </div>

            <button type="submit">Register</button>
        </form>
    <?php endif; ?>
</section>