<?php


/**
 * @var array $registrations
 * @var string $sortDir
 * @var string $sortColumn
 * @var array $filters
 */

// UserRole check


use App\Models\UserRole;

$userRoles = $_SESSION['roles'] ?? [UserRole::USER];

?>

<div class="container mt-5">
    <h1 class="mb-4">Welcome, <?= htmlspecialchars($_SESSION['username']) ?>!</h1>

    <?php
    if (!UserRole::hasPermission($userRoles, UserRole::SITE_ADMIN)) {
        exit;
    }
    ?>

    <p>This is your dashboard. Below are the latest submissions:</p>

    <!-- Search / filter form -->
    <form class="row g-3 mb-3" method="get" action="index.php">
        <input type="hidden" name="route" value="dashboard">
        <!-- Preserve sort params when filtering -->
        <input type="hidden" name="sort" value="<?= htmlspecialchars($sortColumn) ?>">
        <input type="hidden" name="dir" value="<?= $sortDir === 'ASC' ? 'asc' : 'desc' ?>">

        <!-- Row 1 (3 fields) -->
        <div class="row mb-2">
            <div class="col-md-4">
                <label for="username" class="form-label">Username</label>
                <input type="text" name="username" id="username" class="form-control" placeholder="Username"
                       value="<?= htmlspecialchars($filters['username']) ?>">
            </div>
            <div class="col-md-4">
                <label for="domain" class="form-label">Domain</label>
                <input type="text" name="domain" id="domain" class="form-control" placeholder="Domain"
                       value="<?= htmlspecialchars($filters['domain']) ?>">
            </div>
            <div class="col-md-4">
                <label for="pk_sequence" class="form-label">PK Sequence</label>
                <input type="text" name="pk_sequence" id="pk_sequence" class="form-control" placeholder="PK sequence"
                       value="<?= htmlspecialchars($filters['pk_sequence']) ?>">
            </div>
        </div>

        <!-- Row 2 (3 fields) -->
        <div class="row mb-2">
            <div class="col-md-4">
                <label for="authenticated" class="form-label">Authenticated Status</label>
                <select name="authenticated" id="authenticated" class="form-select">
                    <option value="">All records</option>
                    <option value="yes" <?= ($filters['authenticated'] ?? '') === 'yes' ? 'selected' : '' ?>>
                        Authenticated
                    </option>
                    <option value="no" <?= ($filters['authenticated'] ?? '') === 'no' ? 'selected' : '' ?>>Not
                        Authenticated
                    </option>
                </select>
            </div>
            <div class="col-md-4">
                <label for="date_from" class="form-label">Date Range</label>
                <div class="input-group">
                    <input type="date" name="date_from" id="date_from" class="form-control" placeholder="From"
                           value="<?= htmlspecialchars($filters['date_from'] ?? '') ?>" title="Date From">
                    <span class="input-group-text">to</span>
                    <input type="date" name="date_to" id="date_to" class="form-control" placeholder="To"
                           value="<?= htmlspecialchars($filters['date_to'] ?? '') ?>" title="Date To">
                </div>
            </div>
            <div class="col-md-4">
                <label class="form-label">&nbsp;</label>
                <div class="d-grid gap-2 d-md-flex">
                    <button class="btn btn-primary me-2" type="submit">
                        <i class="bi bi-funnel"></i> Filter
                    </button>
                    <a href="index.php?route=dashboard" class="btn btn-secondary">
                        <i class="bi bi-arrow-clockwise"></i> Reset
                    </a>
                </div>
            </div>
        </div>
    </form>

    <?php
    function sortLink($colTitle, $col, $currentCol, $currentDir, $filters): string
    {
        $newDir = ($col === $currentCol && $currentDir === 'ASC') ? 'desc' : 'asc';
        $icon = '';
        if ($col === $currentCol) {
            $icon = $currentDir === 'ASC' ? ' ↑' : ' ↓';
        }

        // Build query string
        $params = ['route' => 'dashboard', 'sort' => $col, 'dir' => $newDir];
        $params = array_merge($params, $filters);
        $queryString = http_build_query($params);

        return "<a href='index.php?$queryString' class='text-white text-decoration-none'>$colTitle$icon</a>";
    }

    ?>


    <?php
    if (count($registrations) === 0): ?>
        <div class="alert alert-warning">No registrations found.</div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-bordered table-striped table-hover">
                <thead class="table-dark">
                <tr>
                    <th><?= sortLink('Username', 'username', $sortColumn, $sortDir, $filters) ?></th>
                    <th><?= sortLink('Email', 'primary_email', $sortColumn, $sortDir, $filters) ?></th>
                    <th><?= sortLink('Domain', 'domain', $sortColumn, $sortDir, $filters) ?></th>
                    <th><?= sortLink('PK Sequence', 'pk_sequence', $sortColumn, $sortDir, $filters) ?></th>
                    <th><?= sortLink('Authenticated', 'authenticated', $sortColumn, $sortDir, $filters) ?></th>
                    <th><?= sortLink('Created At', 'created_at', $sortColumn, $sortDir, $filters) ?></th>
                    <th>Action</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($registrations as $row): ?>
                    <?php
                    $notAuth = !$row['authenticated'];
                    $rowClass = $notAuth ? 'table-danger' : '';
                    ?>
                    <tr class="<?= $rowClass ?>">
                        <td>
                            <?= htmlspecialchars($row['username']) ?>
                        </td>
                        <td><?= htmlspecialchars($row['email'] ?? '') ?></td>
                        <td><?= htmlspecialchars($row['domain'] ?? '') ?></td>
                        <td><?= htmlspecialchars($row['pk_sequence'] ?? '') ?></td>
                        <td><?= $row['authenticated'] ? 'Yes' : 'No' ?></td>
                        <td class="utc-date" data-utc="<?= htmlspecialchars($row['created_at']) ?>">
                            <?php
                            // Fallback for JS disabled
                            $dt = new DateTime($row['created_at']);
                            echo $dt->format('Y-m-d H:i') . ' UTC';
                            ?>
                        </td>
                        <td>
                            <a href="index.php?route=submission-view&id=<?= $row['id'] ?>"
                               class="btn btn-sm btn-info">View Submission</a>
                            <?php if ($notAuth): ?>
                                <?php if (UserRole::hasPermission($userRoles, UserRole::SITE_ADMIN)): ?>
                                    <form method="post" action="index.php?route=authenticate" style="display:inline;">
                                        <input type="hidden" name="id" value="<?= $row['id'] ?>">
                                        <button class="btn btn-sm btn-success">Authenticate</button>
                                    </form>
                                <?php endif; ?>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

