<?php
use App\Core\Role;

if (!isset($pageTitle)) {
    $pageTitle = 'Secret Garden';
}

$userRoles = $_SESSION['roles'] ?? [Role::USER];
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title><?= htmlspecialchars($pageTitle) ?></title>

    <!-- CSS -->
    <link rel="stylesheet" href="assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="assets/css/bootstrap-icons.min.css">
    <link rel="stylesheet" href="assets/css/app.css">
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
    <div class="container-fluid">
        <a class="navbar-brand" href="index.php">
            Secret Garden
        </a>
        
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                <?php if (Role::hasPermission($userRoles, Role::ADMIN)): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="index.php?route=dashboard">Registrations</a>
                    </li>
                <?php endif;?>

                <?php if (Role::hasPermission($userRoles, Role::ADMIN)): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="index.php?route=user_management">User Management</a>
                    </li>
                <?php endif;?>
            </ul>
            
            <?php if (isset($_SESSION['username'])): ?>
                <div class="d-flex align-items-center">
                    <span class="text-light me-3">
                        <?= htmlspecialchars($_SESSION['username']) ?> 
                        <span class="badge bg-secondary"><?= htmlspecialchars(Role::getHighestRole($userRoles)) ?></span>
                    </span>
                    <a href="index.php?route=logout" class="btn btn-outline-light btn-sm">Logout</a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</nav>
