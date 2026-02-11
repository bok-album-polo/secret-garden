<?php

use App\Models\UserRole;

$userRoles = $_SESSION['roles'] ?? [UserRole::USER];


if (!UserRole::hasPermission($userRoles, UserRole::GROUP_ADMIN)) {
    exit;
}
?>

<div class="container mt-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1>Edit Registration</h1>
        <a href="index.php?route=view_registration&id=<?= $registration['id'] ?>" class="btn btn-secondary">Cancel</a>
    </div>

    <div class="card">
        <div class="card-header">
            Editing Registration for <?= htmlspecialchars($registration['username']) ?>
        </div>
        <div class="card-body">
            <form method="post" action="index.php?route=edit_registration&id=<?= $registration['id'] ?>">
                
                <div class="mb-3">
                    <label class="form-label">Username</label>
                    <input type="text" class="form-control" value="<?= htmlspecialchars($registration['username']) ?>" disabled>
                    <div class="form-text">Username cannot be changed.</div>
                </div>

                <div class="mb-3">
                    <label for="email" class="form-label">Email</label>
                    <input type="email" class="form-control" id="email" name="email" value="<?= htmlspecialchars($registration['email']??'') ?>">
                </div>

                <div class="mb-3 form-check">
                    <input type="checkbox" class="form-check-input" id="authenticated" name="authenticated" <?= $registration['authenticated'] ? 'checked' : '' ?>>
                    <label class="form-check-label" for="authenticated">Authenticated</label>
                </div>

                <button type="submit" class="btn btn-primary">Save Changes</button>
            </form>
        </div>
    </div>
</div>

