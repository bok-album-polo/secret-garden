<section id="secret-garden">
    <!-- Title injected from controller -->
    <h1><?= /** @var string $title */
        htmlspecialchars($title ?? 'Home', ENT_QUOTES, 'UTF-8') ?></h1>

    <?php if (!empty($messages)): ?>
        <?php foreach ($messages as $msg): ?>
            <p><strong>🔔 <?= htmlspecialchars($msg, ENT_QUOTES, 'UTF-8') ?></strong></p>
        <?php endforeach; ?>
    <?php endif; ?>

    <p>An oasis of tranquility in a chaotic world. We specialize in bringing the healing power of nature into your
        personal space.</p>

    <hr>

    <p>Our philosophy supports sustainable growth, organic beauty, and timeless design.</p>
</section>