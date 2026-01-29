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
