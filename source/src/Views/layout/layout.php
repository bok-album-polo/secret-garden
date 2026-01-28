<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($title ?? 'Secret Garden', ENT_QUOTES, 'UTF-8') ?></title>
    <link rel="stylesheet" href="/assets/styles.css">
</head>
<body>
<?php require __DIR__ . '/header.php'; ?>

<!-- Global flash messages -->
<?php if (!empty($_SESSION['flash'])): ?>
    <div class="flash-container">
        <?php foreach ($_SESSION['flash'] as $type => $messages): ?>
            <?php foreach ($messages as $msg): ?>
                <p class="flash <?= htmlspecialchars($type) ?>">
                    <?= htmlspecialchars($msg, ENT_QUOTES, 'UTF-8') ?>
                </p>
            <?php endforeach; ?>
        <?php endforeach; ?>
    </div>
    <?php unset($_SESSION['flash']); ?>
<?php endif; ?>

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
