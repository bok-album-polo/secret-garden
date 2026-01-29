<?php
use App\Core\Role;

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
</head>
<body>

<nav>
    <a href="index.php">Secret Garden Admin</a>
    <?php if (Role::hasPermission($userRoles, Role::ADMIN)): ?>
        <a href="index.php?route=dashboard">Registrations</a>
        <a href="index.php?route=user_management">User Management</a>
    <?php endif;?>
    
    <?php if (isset($_SESSION['username'])): ?>
        <span>
            <?= htmlspecialchars($_SESSION['username']) ?> 
            (<?= htmlspecialchars(Role::getHighestRole($userRoles)) ?>)
        </span>
        <a href="index.php?route=logout">Logout</a>
    <?php endif; ?>
</nav>

<main>
    <?php require $viewFile; ?>
</main>

<footer>
    <p>&copy; <?= date('Y') ?> Secret Garden Admin</p>
</footer>

</body>
</html>
