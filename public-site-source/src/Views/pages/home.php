<section id="secret-garden">
    <style>
        #secret-garden {
            max-width: 700px;
            margin: 40px auto;
            padding: 20px;
            font-family: Arial, sans-serif;
            line-height: 1.6;
            background-color: #f9f9f9;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }

        #secret-garden h1 {
            text-align: center;
            color: #2e7d32; /* deep green */
            margin-bottom: 20px;
            font-size: 2em;
        }

        #secret-garden p {
            margin-bottom: 15px;
            color: #333;
        }

        #secret-garden hr {
            border: none;
            border-top: 2px solid #c8e6c9; /* light green */
            margin: 25px 0;
        }

        .message {
            background-color: #e8f5e9; /* very light green */
            border: 1px solid #81c784; /* medium green */
            color: #2e7d32; /* dark green */
            padding: 10px 15px;
            border-radius: 5px;
            margin-bottom: 15px;
            font-weight: bold;
        }
    </style>

    <!-- Title injected from controller -->
    <h1><?= /** @var string $title */
        htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?></h1>

    <?php if (!empty($messages)): ?>
        <?php foreach ($messages as $msg): ?>
            <p class="message"><?= htmlspecialchars($msg, ENT_QUOTES, 'UTF-8') ?></p>
        <?php endforeach; ?>
    <?php endif; ?>

    <p>An oasis of tranquility in a chaotic world. We specialize in bringing the healing power of nature into your
        personal space.</p>
    <hr>
    <p>Our philosophy supports sustainable growth, organic beauty, and timeless design.</p>
</section>
