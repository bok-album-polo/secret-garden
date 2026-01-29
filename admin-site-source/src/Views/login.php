<?php require __DIR__ . '/layout/header.php'; ?>

<div class="container vh-100 d-flex justify-content-center align-items-center">
    <div class="card p-4 shadow" style="width: 400px">
        <h4 class="mb-3">Login</h4>

        <?php if (isset($_GET['error'])): ?>
            <div class="alert alert-danger">
                <?php
                if ($_GET['error'] == 1) echo 'Invalid username or password.';
                elseif ($_GET['error'] == 2) echo 'Your account is not authenticated yet.';
                ?>
            </div>
        <?php endif; ?>

        <form method="post" action="index.php?route=login">
            <div class="mb-3">
                <input name="username" type="text" class="form-control" placeholder="Username" required>
            </div>
            <div class="mb-3">
                <input name="password" type="password" class="form-control" placeholder="Password" required>
            </div>
            <button class="btn btn-primary w-100">Login</button>
        </form>
    </div>
</div>

<?php require __DIR__ . '/layout/footer.php'; ?>
