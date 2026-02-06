<!-- pages/admin-edit-submission.php -->
<?php
/**
 * @var array $submission
 * @var array $fields
 */
?>
<section>
    <h2>Edit Submission #<?= htmlspecialchars($submission['id']) ?></h2>

    <fieldset>
        <legend>Submission Details</legend>
        <p><strong>Username:</strong> <?= htmlspecialchars($submission['username'] ?? '') ?></p>
        <p><strong>Primary Email:</strong> <?= htmlspecialchars($submission['primary_email'] ?? '') ?></p>
        <p><strong>Domain:</strong> <?= htmlspecialchars($submission['domain'] ?? '') ?></p>
        <p><strong>Created By:</strong> <?= htmlspecialchars($submission['created_by'] ?? '') ?></p>
        <p><strong>IP Address:</strong> <?= htmlspecialchars($submission['ip_address'] ?? '') ?></p>
        <p><strong>Authenticated:</strong> <?= ($submission['authenticated'] ?? false) ? 'Yes' : 'No' ?></p>
    </fieldset>


    <legend>Edit Form</legend>
    <?= \App\Controllers\Controller::renderForm(
            fields: $fields,
            defaults: $submission,
            isSecretRoom: false
    ); ?>

<!--    <form method="POST">-->
<!--        <input type="hidden" name="action" value="admin_delete_submission">-->
<!--        <input type="hidden" name="id" value="--><?php //= htmlspecialchars($submission['id']) ?><!--">-->
<!--        <p>-->
<!--            <button type="submit">Delete Submission</button>-->
<!--        </p>-->
<!--    </form>-->
</section>