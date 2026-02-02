<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title ?? 'Secret Garden', ENT_QUOTES, 'UTF-8') ?></title>
</head>
<body>

<?php
$config = \App\Config::instance();
$prettyUrls = $config->project_meta['pretty_urls'] ?? false;
$environment = $config->project_meta['environment'] ?? 'production';
$secretPage = $config->routing_secrets['secret_room'] ?? 'registration';
$pagesMenu = $config->pages_menu ?? [];
?>

<header>
    <h2>Secret Garden</h2>
    <!-- Navigation menu-->
    <nav>
        <ul>
            <?php foreach ($pagesMenu as $index => $slug): ?>
                <?php if ($slug === $secretPage) continue; ?>
                <li>
                    <?php if ($prettyUrls): ?>
                    <a href="/<?= htmlspecialchars($slug, ENT_QUOTES, 'UTF-8') ?>">
                        <?php else: ?>
                        <a href="?page=<?= htmlspecialchars($slug, ENT_QUOTES, 'UTF-8') ?>">
                            <?php endif; ?>

                            <?php if ($environment === 'development'): ?>
                                <span style="color: gray;"><?= htmlspecialchars((string)$index, ENT_QUOTES, 'UTF-8') ?> - </span>
                            <?php endif; ?>

                            <?= ucwords(str_replace('-', ' ', $slug)) ?>
                        </a>
                </li>
            <?php endforeach; ?>
        </ul>
    </nav>
    <!-- End of Navigation menu-->
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
<?php if ($environment === 'development'): ?>
    <?php require_once __DIR__ . '/debug.php'; ?>
<?php endif; ?>
</body>
</html>