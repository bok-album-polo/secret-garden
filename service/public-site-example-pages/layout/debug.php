<?php
$config = App\Controllers\Config::instance();
$environment = $config->project_meta['environment'] ?? 'production';

if ($environment === 'development'):
    ?>
    <style>
        #debug-panel {
            color: gray;
            border-top: 1px solid gray;
            margin-top: 50px;
            padding: 20px;
            font-family: monospace;
        }

        #debug-panel table {
            border-collapse: collapse;
        }

        #debug-panel th,
        #debug-panel td {
            border: 1px solid #000;
            padding: 4px;
        }

        #debug-panel a {
            color: gray;
        }
    </style>

    <div id="debug-panel">
        <table>
            <tr>
                <th>Config / State</th>
                <th>Value</th>
            </tr>
            <tr>
                <td>$config->domain</td>
                <td><?= htmlspecialchars($config->domain, ENT_QUOTES, 'UTF-8') ?></td>
            </tr>
            <tr>
                <td>$config->db_credentials['user']</td>
                <td><?= htmlspecialchars($config->db_credentials['user'], ENT_QUOTES, 'UTF-8') ?></td>
            </tr>
            <tr>
                <td>$config->routing_secrets['secret_door']</td>
                <td><?= htmlspecialchars($config->routing_secrets['secret_door'], ENT_QUOTES, 'UTF-8') ?></td>
            </tr>
            <tr>
                <td>$config->routing_secrets['secret_room']</td>
                <td><?= htmlspecialchars($config->routing_secrets['secret_room'], ENT_QUOTES, 'UTF-8') ?></td>
            </tr>
            <tr>
                <td>$config->application_config['pk_length']</td>
                <td><?= $config->application_config['pk_length'] ?></td>
            </tr>
            <tr>
                <td>$_SESSION['pk_history']</td>
                <td><?= htmlspecialchars(implode('', $_SESSION['pk_history'] ?? []), ENT_QUOTES, 'UTF-8') ?></td>
            </tr>
            <tr>
                <td>$config->application_config['pk_max_history']</td>
                <td><?= $config->application_config['pk_max_history'] ?></td>
            </tr>
            <tr>
                <td>count($_SESSION['pk_history'] ?? [])</td>
                <td><?= count($_SESSION['pk_history'] ?? []) ?></td>
            </tr>
            <tr>
                <td>$_SESSION['pk_sequence']</td>
                <td>
                    <?= htmlspecialchars(
                            $_SESSION['pk_sequence'] ?? 'N/A',
                            ENT_QUOTES,
                            'UTF-8'
                    ) ?>
                </td>
            </tr>
            <tr>
                <td>$_SERVER['REMOTE_ADDR']</td>
                <td><?= htmlspecialchars($_SERVER['REMOTE_ADDR'], ENT_QUOTES, 'UTF-8') ?></td>
            </tr>
            <tr>
                <td>$_SESSION['ip_banned']</td>
                <td><?= $_SESSION['ip_banned'] ? 'YES' : 'NO' ?></td>
            </tr>
            <tr>
                <td>$_SESSION['pk_authed']</td>
                <td><?= $_SESSION['pk_authed'] ? 'YES' : 'NO' ?></td>
            </tr>
            <tr>
                <td>$_SESSION['user_logged_in']</td>
                <td><?= $_SESSION['user_logged_in'] ? 'YES' : 'NO' ?></td>
            </tr>
        </table>

        <?php $logoutUrl = $prettyUrls ? '/clear-auth-trackers' : '?page=clear-auth-trackers'; ?>
        <p><a href="<?= htmlspecialchars($logoutUrl, ENT_QUOTES, 'UTF-8') ?>"><b>clear-auth-trackers</b></a></p>
    </div>
<?php endif; ?>
