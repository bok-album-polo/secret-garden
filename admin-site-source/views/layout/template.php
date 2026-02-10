<?php

use App\Core\UserRole;

if (!isset($pageTitle)) {
    $pageTitle = 'Secret Garden - Admin';
}

$userRoles = $_SESSION['roles'] ?? [UserRole::USER];
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title><?= htmlspecialchars($pageTitle) ?></title>
    <link rel="stylesheet" href="/assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="/assets/css/bootstrap-icons.min.css">
    <link rel="stylesheet" href="/assets/css/app.css">
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-dark bg-dark mb-4">
    <div class="container-fluid">
        <a class="navbar-brand" href="index.php?route=dashboard">Secret Garden Admin</a>

        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav"
                aria-controls="mainNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="mainNav">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                <?php if (UserRole::hasPermission($userRoles, UserRole::GROUP_ADMIN)): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="index.php?route=dashboard">
                            <i class="bi bi-clipboard-data"></i> Registrations
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="index.php?route=user_management">
                            <i class="bi bi-people"></i> User Management
                        </a>
                    </li>
                <?php endif; ?>
            </ul>

            <?php if (isset($_SESSION['username'])): ?>
                <span class="navbar-text me-3">
                    <?= htmlspecialchars($_SESSION['username']) ?>
                    (<?= htmlspecialchars(UserRole::getHighestRole($userRoles)) ?>)
                </span>
                <a class="btn btn-outline-light btn-sm" href="index.php?route=logout">
                    <i class="bi bi-box-arrow-right"></i> Logout
                </a>
            <?php endif; ?>
        </div>
    </div>
</nav>

<main>
    <?php /** @var string $viewFile */
    require $viewFile; ?>
</main>

<footer>
    <p>&copy; <?= date('Y') ?> Secret Garden Admin</p>
</footer>
<script src="/assets/js/date-formatter.js" defer></script>
<script src="/assets/js/bootstrap.bundle.min.js"></script>
</body>
</html>
