<?php

use App\core\Role;

if (!isset($pageTitle)) {
    $pageTitle = 'Secret Garden - Admin';
}

$userRoles = $_SESSION['roles'] ?? [Role::USER];
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

<nav>
    <?php if (Role::hasPermission($userRoles, Role::ADMIN)): ?>
        <a href="index.php?route=dashboard">Registrations</a>
        <a href="index.php?route=user_management">User Management</a>
    <?php endif; ?>

    <?php if (isset($_SESSION['username'])): ?>
        <span>
            <?= htmlspecialchars($_SESSION['username']) ?> 
            (<?= htmlspecialchars(Role::getHighestRole($userRoles)) ?>)
        </span>
        <a href="index.php?route=logout">Logout</a>
    <?php endif; ?>
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
