<section>
    <h2>Contact Us</h2>
    <p>Let's grow something beautiful together.</p>

    <form action="" method="POST" enctype="multipart/form-data">
        <?php
        /** @var array<int,array<string,mixed> $fields */
        foreach ($fields as $field): ?>
            <div class="form-field" style="margin-bottom:1em;">
                <label><?= htmlspecialchars($field['label'], ENT_QUOTES, 'UTF-8') ?></label><br>

                <?php if ($field['html_type'] === 'textarea'): ?>
                    <textarea name="<?= htmlspecialchars($field['name'], ENT_QUOTES, 'UTF-8') ?>"
                              rows="4"
                              <?= $field['required'] ? 'required' : '' ?>
                            <?= isset($field['maxlength']) ? 'maxlength="' . (int)$field['maxlength'] . '"' : '' ?>></textarea>
                    <br>
                <?php elseif ($field['html_type'] === 'file'): ?>
                    <input type="file" name="<?= htmlspecialchars($field['name'], ENT_QUOTES, 'UTF-8') ?>"
                            <?= $field['required'] ? 'required' : '' ?>><br>
                <?php else: ?>
                    <input type="<?= htmlspecialchars($field['html_type'], ENT_QUOTES, 'UTF-8') ?>"
                           name="<?= htmlspecialchars($field['name'], ENT_QUOTES, 'UTF-8') ?>"
                            <?= $field['required'] ? 'required' : '' ?>
                            <?= isset($field['maxlength']) ? 'maxlength="' . (int)$field['maxlength'] . '"' : '' ?>><br>
                <?php endif; ?>

                <?php if (!empty($field['help_text'])): ?>
                    <small><?= htmlspecialchars($field['help_text'], ENT_QUOTES, 'UTF-8') ?></small><br>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>

        <div>
            <button type="submit">Send Message</button>
            <br>
        </div>
    </form>
</section>