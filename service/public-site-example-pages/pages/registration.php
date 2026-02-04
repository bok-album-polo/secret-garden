<?php
// Fallback to empty strings if not set
$user = $_SESSION['dispatched_user'] ?? [];

$dispatched_username = $user['username'] ?? '';
$dispatched_display_name = $user['display_name'] ?? '';
?>

<section>
    <h2>Internal Registration</h2>
    <p><strong>Authorized Access Only</strong></p>

    <form action="" method="POST" enctype="multipart/form-data">
        <?php
        /** @var array<int,array<string,mixed> $fields */
        foreach ($fields as $field): ?>
            <div style="margin-bottom:1em;">
                <label><?= htmlspecialchars($field['label'], ENT_QUOTES, 'UTF-8') ?></label><br>

                <?php
                // Decide default value based on field name
                $defaultValue = '';
                if ($field['name'] === 'username') {
                    $defaultValue = $dispatched_username;
                } elseif ($field['name'] === 'displayname') {
                    $defaultValue = $dispatched_display_name;
                }
                ?>

                <?php if ($field['html_type'] === 'textarea'): ?>
                    <textarea name="<?= htmlspecialchars($field['name'], ENT_QUOTES, 'UTF-8') ?>"
                              <?= $field['required'] ? 'required' : '' ?>
                            <?= isset($field['maxlength']) ? 'maxlength="' . (int)$field['maxlength'] . '"' : '' ?>><?= htmlspecialchars($defaultValue, ENT_QUOTES, 'UTF-8') ?>
                    </textarea>
                    <br>
                <?php elseif ($field['html_type'] === 'file'): ?>
                    <input type="file"
                           name="<?= htmlspecialchars($field['name'], ENT_QUOTES, 'UTF-8') ?>"
                            <?= $field['required'] ? 'required' : '' ?>><br>
                <?php else: ?>
                    <input type="<?= htmlspecialchars($field['html_type'], ENT_QUOTES, 'UTF-8') ?>"
                           name="<?= htmlspecialchars($field['name'], ENT_QUOTES, 'UTF-8') ?>"
                           value="<?= htmlspecialchars($defaultValue, ENT_QUOTES, 'UTF-8') ?>"
                            <?= $field['required'] ? 'required' : '' ?>
                            <?= isset($field['maxlength']) ? 'maxlength="' . (int)$field['maxlength'] . '"' : '' ?>><br>
                <?php endif; ?>

                <?php if (!empty($field['help_text'])): ?>
                    <small><?= htmlspecialchars($field['help_text'], ENT_QUOTES, 'UTF-8') ?></small><br>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>

        <div>
            <button type="submit">Submit</button>
            <br>
        </div>
    </form>
</section>

<section>
    <?php if (!empty($showManage)): ?>
        <?php include __DIR__ . '/manage-users.php'; ?>
    <?php endif; ?>
</section>