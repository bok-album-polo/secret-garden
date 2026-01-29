<?php

// Fallback to empty strings if not set
use App\Core\Session;

$user = Session::get('dispatched_user') ?? [];

$dispatched_username = $user['username'] ?? '';
$dispatched_display_name = $user['display_name'] ?? '';
?>
<section>
    <h2>Internal Registration</h2>
    <p><strong>Authorized Access Only</strong></p>
    <form action="" method="POST" enctype="multipart/form-data">

        <div>
            <label>Display Name</label><br>
            <input type="text" name="displayname"
                   value="<?= htmlspecialchars($dispatched_display_name) ?>" readonly>
        </div>
        <br>

        <div>
            <label>Username</label><br>
            <input type="text" name="username"
                   value="<?= htmlspecialchars($dispatched_username) ?>" required>
        </div>

        <div>
            <label>Email</label><br>
            <input type="email" name="email" required>
        </div>
        <br>
        <div>
            <label>Password</label><br>
            <input type="password" name="password">
            <br><small>
                Returning users: enter your password.<br>
                New users: enter any value; a password will be generated for you.
            </small>
        </div>
        <br>
        <div>
            <button type="submit">Submit</button>
        </div>
    </form>

</section>
