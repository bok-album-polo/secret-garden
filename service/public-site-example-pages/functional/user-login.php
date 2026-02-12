<section>
    <h2><?= /** @var string $title */
        htmlspecialchars($title ?? 'User login') ?></h2>
    <p><strong>Authorized Access Only</strong></p>
    <!-- Login Block -->
    <form action="" method="POST">
        <input type="hidden" name="action" value="login">
        <input type="hidden" name="csrf_token" value="<?=$_SESSION['csrf_token']?>">
        <div>
            <label>Username</label><br>
            <input type="text" name="username" required><br>
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
        <input type="hidden" name="action" value="username_choice">
        <button type="submit">Choose username</button>
    </form>
</section>