<!-- pages/registration-success.php -->

<?php if (!empty($username) || !empty($email)): ?>
    <style>
        .summary-container {
            max-width: 600px;
            margin: 40px auto;
            background: white;
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 30px;
        }

        .summary-header {
            text-align: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid #667eea;
        }

        .summary-header h2 {
            color: #333;
            margin-bottom: 5px;
        }

        .summary-header p {
            color: #666;
            font-size: 14px;
        }

        .password-alert {
            background: #fff3cd;
            border: 1px solid #ffc107;
            border-radius: 5px;
            padding: 20px;
            margin-bottom: 25px;
        }

        .password-alert h3 {
            color: #856404;
            font-size: 16px;
            margin-bottom: 12px;
        }

        .password-display {
            background: white;
            border: 2px dashed #ffc107;
            padding: 15px;
            text-align: center;
            border-radius: 5px;
            margin-bottom: 10px;
        }

        .password-display strong {
            font-family: monospace;
            font-size: 20px;
            color: #856404;
            letter-spacing: 2px;
        }

        .password-alert small {
            color: #856404;
            font-size: 13px;
        }

        .summary-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 25px;
        }

        .summary-table th,
        .summary-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }

        .summary-table th {
            background: #f5f5f5;
            font-weight: 600;
            color: #555;
            width: 35%;
        }

        .summary-table td {
            color: #666;
        }

        .summary-table tr:last-child th,
        .summary-table tr:last-child td {
            border-bottom: none;
        }

        .badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 3px;
            font-size: 12px;
            font-weight: 600;
        }

        .badge-yes {
            background: #d4edda;
            color: #155724;
        }

        .badge-no {
            background: #f8d7da;
            color: #721c24;
        }

        .summary-actions {
            display: flex;
            gap: 10px;
            margin-top: 25px;
        }

        .btn {
            flex: 1;
            padding: 12px 20px;
            text-align: center;
            text-decoration: none;
            border-radius: 5px;
            font-weight: 600;
        }

        .btn-primary {
            background: #667eea;
            color: white;
        }

        .btn-primary:hover {
            background: #5568d3;
        }

        .btn-secondary {
            background: #f5f5f5;
            color: #333;
            border: 1px solid #ddd;
        }

        .btn-secondary:hover {
            background: #e5e5e5;
        }
    </style>

    <div class="summary-container">
        <div class="summary-header">
            <h2>✓ Registration Summary</h2>
            <p>Your submission has been successfully processed</p>
        </div>

        <?php if (!empty($generated_password)): ?>
            <div class="password-alert">
                <h3>🔒 Your Generated Password</h3>
                <div class="password-display">
                    <strong><?= htmlspecialchars($generated_password) ?></strong>
                </div>
                <small>⚠️ Copy and save this password securely. It will not be shown again.</small>
            </div>
        <?php endif; ?>

        <table class="summary-table">
            <tr>
                <th>Username</th>
                <td><?= htmlspecialchars($username ?? '') ?></td>
            </tr>
            <tr>
                <th>Email</th>
                <td><?= htmlspecialchars($email ?? '') ?></td>
            </tr>
            <tr>
                <th>Authenticated</th>
                <td>
                <span class="badge <?= ($authenticated ?? false) ? 'badge-yes' : 'badge-no' ?>">
                    <?= ($authenticated ?? false) ? 'Yes' : 'No' ?>
                </span>
                </td>
            </tr>
            <tr>
                <th>IP Address</th>
                <td><?= htmlspecialchars($ip_address ?? '') ?></td>
            </tr>
            <tr>
                <th>User Agent</th>
                <td><?= htmlspecialchars($user_agent ?? '') ?></td>
            </tr>
        </table>
    </div>
<?php endif; ?>