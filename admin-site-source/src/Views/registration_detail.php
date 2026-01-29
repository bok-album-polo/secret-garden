<?php

use App\Core\Role;

$userRoles = $_SESSION['roles'] ?? [Role::USER];


if (!Role::hasPermission($userRoles, Role::ADMIN)) {
    exit;
}
?>

<div class="container mt-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1>Registration Details</h1>
        <div>
            <a href="index.php?route=edit_registration&id=<?= $registration['id'] ?>"
               class="btn btn-warning me-2">Edit</a>
            <a href="index.php?route=dashboard" class="btn btn-secondary">Back to Dashboard</a>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span>Latest Registration #<?= htmlspecialchars($registration['id']) ?></span>
            <button class="btn btn-sm btn-outline-primary" type="button" onclick="toggleAllHistory()">
                Show All History
            </button>
        </div>
        <div class="card-body">
            <?php
            // Define the fields we want to display
            $fields = [
                    'username' => 'Username',
                    'email' => 'Email',
                    'user_agent' => 'User Agent',
                    'domain' => 'Domain',
                    'ip_address' => 'IP Address',
                    'pk_sequence' => 'PK Sequence',
                    'created_at' => 'Timestamp',
                    'created_by' => 'Created By',
                    'authenticated' => 'Authenticated'
            ];

            // Helper to format value
            function formatValue($key, $value)
            {
                if ($value === null || $value === '') {
                    return '<span class="text-muted fst-italic">(blank)</span>';
                }
                if ($key === 'created_at') {
                    // Use the utc-date class for JS formatting
                    $dt = new DateTime($value);
                    return '<span class="utc-date" data-utc="' . htmlspecialchars($value) . '">' . $dt->format('Y-m-d H:i') . ' UTC</span>';
                }
                if ($key === 'authenticated') {
                    return $value ? '<span class="badge bg-success">Yes</span>' : '<span class="badge bg-danger">No</span>';
                }
                return htmlspecialchars($value);
            }

            // Group history by field to detect changes
            // $history is ordered by created_at DESC (latest first)
            // The current $registration is effectively $history[0] if we are viewing the latest.

            // We want to show history for fields that have changed.
            // A field has changed if there is more than one unique value in the history.
            ?>

            <dl class="row">
                <?php foreach ($fields as $key => $label): ?>
                    <dt class="col-sm-3"><?= $label ?></dt>
                    <dd class="col-sm-9">
                        <div class="d-flex align-items-center">
                            <div class="me-2">
                                <?= formatValue($key, $registration[$key] ?? null) ?>
                            </div>

                            <?php
                            // Check for history
                            $fieldHistory = [];
                            foreach ($history as $h) {
                                // Skip the current one being displayed if it's the same ID (though usually we want to see previous values)
                                // Actually, let's just collect all values.
                                $val = $h[$key] ?? null;
                                $fieldHistory[] = [
                                        'value' => $val,
                                        'date' => $h['created_at'],
                                        'id' => $h['id'],
                                        'created_by' => $h['created_by'] ?? null
                                ];
                            }

                            // Determine if modified (more than 1 unique value)
                            $uniqueValues = array_unique(array_column($fieldHistory, 'value'));
                            $isModified = count($uniqueValues) > 1;
                            ?>

                            <?php if ($isModified): ?>
                                <div class="ms-2">
                                    <span class="badge bg-warning text-dark">Modified</span>
                                    <button class="btn btn-sm btn-link history-toggle" type="button"
                                            data-bs-toggle="collapse" data-bs-target="#history-<?= $key ?>"
                                            aria-expanded="false">
                                        View History
                                    </button>
                                </div>
                            <?php endif; ?>
                        </div>

                        <?php if ($isModified): ?>
                            <div class="collapse mt-2 history-dropdown" id="history-<?= $key ?>">
                                <div class="card card-body bg-light">
                                    <ul class="list-unstyled mb-0">
                                        <?php foreach ($fieldHistory as $item): ?>
                                            <?php if ($item['id'] != $registration['id']): // Don't repeat the current value ?>
                                                <li class="mb-1">
                                                    <small class="text-muted">
                                                        <span class="utc-date" data-utc="<?= htmlspecialchars($item['date']) ?>">
                                                            <?= (new DateTime($item['date']))->format('Y-m-d H:i') ?> UTC
                                                        </span>
                                                        <?php if ($item['created_by']): ?>
                                                            (by <?= htmlspecialchars($item['created_by']) ?>)
                                                        <?php endif; ?>:
                                                    </small>
                                                    <?= formatValue($key, $item['value']) ?>
                                                </li>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                            </div>
                        <?php endif; ?>
                    </dd>
                <?php endforeach; ?>
            </dl>

            <?php if (!$registration['authenticated']): ?>
                <hr>
                <form method="post" action="index.php?route=authenticate" class="mt-3">
                    <input type="hidden" name="id" value="<?= $registration['id'] ?>">
                    <button class="btn btn-success">Authenticate User</button>
                </form>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
    function toggleAllHistory() {
        let collapses = document.querySelectorAll('.history-dropdown');
        let isAnyHidden = false;

        // Check if any are currently hidden (not showing)
        collapses.forEach(function (el) {
            if (!el.classList.contains('show')) {
                isAnyHidden = true;
            }
        });

        collapses.forEach(function (el) {
            // If any are hidden, show all. If all are shown, hide all.
            const bsCollapse = new bootstrap.Collapse(el, {
                toggle: false
            });

            if (isAnyHidden) {
                bsCollapse.show();
            } else {
                bsCollapse.hide();
            }
        });
    }
</script>

