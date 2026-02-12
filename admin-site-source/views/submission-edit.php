<?php

/** @var array $registration */

use App\Models\UserRole;

$userRoles = $_SESSION['roles'] ?? [UserRole::USER];


if (!UserRole::hasPermission($userRoles, UserRole::ADMIN)) {
    exit;
}
?>

<div class="container mt-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1>Edit Submission</h1>
        <a href="index.php?route=submission-view&id=<?= $registration['id'] ?>" class="btn btn-secondary">Cancel</a>
    </div>

    <div class="card">
        <div class="card-header">
            Editing submission for <?= htmlspecialchars($registration['username']) ?>
        </div>
        <div class="card-body">
            <?=
            /** @var array<int,array<string,mixed>> $fields */
            \App\Controllers\Controller::renderForm(
                    fields: $fields,
                    defaults: $registration,
            );
            ?>
        </div>
    </div>
</div>

