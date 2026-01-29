<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title ?? 'Secret Garden', ENT_QUOTES, 'UTF-8') ?></title>
</head>
<body>

<header>
    <h2>Secret Garden</h2>
    <nav>
        <ul>
            <?php foreach (PAGES as $slug => $page_id): ?>
                <?php if ($slug === SECRET_PAGE) continue; ?>
                <li>
                    <?php if (ENABLE_PRETTY_URLS): ?>
                    <a href="/<?= htmlspecialchars($slug, ENT_QUOTES, 'UTF-8') ?>">
                        <?php else: ?>
                        <a href="?page=<?= htmlspecialchars($slug, ENT_QUOTES, 'UTF-8') ?>">
                            <?php endif; ?>

                            <?php if (ENVIRONMENT === 'development'): ?>
                                <span style="color: gray;"><?= htmlspecialchars($page_id, ENT_QUOTES, 'UTF-8') ?> - </span>
                            <?php endif; ?>

                            <?= ucwords(str_replace('-', ' ', $slug)) ?>
                        </a>
                </li>
            <?php endforeach; ?>
        </ul>
    </nav>
</header>
<hr>

<main>
    <?php /** @var string $viewFile */
    require $viewFile; ?>
</main>

<hr>
<footer>
    <p><small>&copy; <?= date('Y') ?> Secret Garden. Curating nature's finest moments.</small></p>
</footer>

<!-- Debug info only in development -->
<?php if (ENVIRONMENT === 'development'): ?>
    <?php require_once __DIR__ . '/debug.php'; ?>
<?php endif; ?>
</body>
</html>
