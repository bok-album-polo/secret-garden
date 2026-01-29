<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($title ?? 'Secret Garden', ENT_QUOTES, 'UTF-8') ?></title>
</head>
<body>
<?php require __DIR__ . '/header.php'; ?>

<main>
    <?php /** @var string $viewFile */
    require $viewFile; ?>
</main>

<?php require __DIR__ . '/footer.php'; ?>

<!-- Debug info only in development -->
<?php if (ENVIRONMENT === 'development'): ?>
    <?php require_once __DIR__ . '/debug.php'; ?>
<?php endif; ?>
</body>
</html>
