<div class="container vh-100 d-flex justify-content-center align-items-center">
    <div class="card p-4 shadow" style="max-width: 400px; width: 100%;">
        <div class="card-body">
            <h1 class="h4 mb-4 text-center">Welcome Back</h1>

            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?php
                    $errorMessages = [
                            '1' => 'Invalid username or password.',
                            '2' => 'Your account has not been authenticated yet.',
                            '3' => 'Session expired. Please login again.'
                    ];
                    echo htmlspecialchars($errorMessages[$_SESSION['error']] ?? 'An error occurred. Please try again.', ENT_QUOTES, 'UTF-8');
                    unset($_SESSION['error']);
                    ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    Registration successful! Please login.
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <form method="post" action="index.php?route=login" novalidate>
                <!-- CSRF Token -->
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?? '' ?>">

                <div class="mb-3">
                    <label for="username" class="form-label visually-hidden">Username</label>
                    <input
                            id="username"
                            name="username"
                            type="text"
                            class="form-control"
                            placeholder="Username"
                            autocomplete="username"
                            autofocus
                            required
                            value="<?= htmlspecialchars($_POST['username'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                </div>

                <div class="mb-3">
                    <label for="password" class="form-label visually-hidden">Password</label>
                    <input
                            id="password"
                            name="password"
                            type="password"
                            class="form-control"
                            placeholder="Password"
                            autocomplete="current-password"
                            required>
                </div>

                <div class="mb-3 form-check">
                    <input type="checkbox" class="form-check-input" id="rememberMe" name="remember_me">
                    <label class="form-check-label" for="rememberMe">
                        Remember me
                    </label>
                </div>

                <button type="submit" class="btn btn-primary w-100 mb-3">
                    Login
                </button>
            </form>
        </div>
    </div>
</div>