<?php

/** @var array $submission */

/** @var bool $form_readonly */

use App\Models\UserRole;

$userRoles = $_SESSION['roles'] ?? [UserRole::USER];


if (!UserRole::hasPermission($userRoles, UserRole::SITE_ADMIN)) {
    exit;
}
?>

<div class="container mt-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1>Submission</h1>
        <a href="index.php?route=submission-view&id=<?= $submission['id'] ?>" class="btn btn-secondary">Cancel</a>
    </div>

    <div class="card">
        <div class="card-header">
            Submission for <?= htmlspecialchars($submission['username']) ?>
        </div>
        <div class="card-body">
            <?=
            /** @var array<int,array<string,mixed>> $fields */
            \App\Controllers\Controller::renderForm(
                    fields: $fields,
                    defaults: $submission,
                    form_readonly: $form_readonly
            );
            ?>
        </div>
    </div>
</div>

