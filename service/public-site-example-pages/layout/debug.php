<?php
$config = \App\Config::instance();
$environment = $config->project_meta['environment'] ?? 'production';

if ($environment === 'development'):
    $pk_auth = $_SESSION['pk_authed'] ?? false;
    $is_pk_banned = $_SESSION['pk_ban'] ?? false;
    $is_ip_banned = $_SESSION['ip_banned'] ?? false;
    $prettyUrls = $config->project_meta['pretty_urls'] ?? false;
    $secretDoor = $config->routing_secrets['secret_door'] ?? 'contact';
    $secretPage = $config->routing_secrets['secret_page'] ?? 'registration';
    $pkLength = $config->application_config['pk_length'] ?? 5;
    $pkMaxHistory = $config->application_config['pk_max_history'] ?? 20;
    $domain = $config->domain ?? 'N/A';
    $dbUser = $config->db_credentials['user'] ?? 'N/A';
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
                <td>Domain</td>
                <td><?= htmlspecialchars($domain, ENT_QUOTES, 'UTF-8') ?></td>
            </tr>
            <tr>
                <td>Database User</td>
                <td><?= htmlspecialchars($dbUser, ENT_QUOTES, 'UTF-8') ?></td>
            </tr>
            <tr>
                <td>Secret Door</td>
                <td><?= htmlspecialchars($secretDoor, ENT_QUOTES, 'UTF-8') ?></td>
            </tr>
            <tr>
                <td>Secret Page</td>
                <td><?= htmlspecialchars($secretPage, ENT_QUOTES, 'UTF-8') ?></td>
            </tr>
            <tr>
                <td>Sequence Length</td>
                <td><?= $pkLength ?></td>
            </tr>
            <tr>
                <td>PK History</td>
                <td><?= htmlspecialchars(implode('', $_SESSION['pk_history'] ?? []), ENT_QUOTES, 'UTF-8') ?></td>
            </tr>
            <tr>
                <td>PK Max History</td>
                <td><?= $pkMaxHistory ?></td>
            </tr>
            <tr>
                <td>PK History Length</td>
                <td><?= count($_SESSION['pk_history'] ?? []) ?></td>
            </tr>
            <tr>
                <td>PK Banned</td>
                <td><?= $is_pk_banned ? 'YES' : 'NO' ?></td>
            </tr>
            <tr>
                <td>PK Sequence</td>
                <td>
                    <?= htmlspecialchars(
                            $pk_auth
                                    ? ($_SESSION['pk_sequence'] ?? 'N/A')
                                    : implode('', array_slice($_SESSION['pk_history'] ?? [], -$pkLength)),
                            ENT_QUOTES,
                            'UTF-8'
                    ) ?>
                </td>
            </tr>
            <tr>
                <td>IP Address</td>
                <td><?= htmlspecialchars($_SERVER['REMOTE_ADDR'], ENT_QUOTES, 'UTF-8') ?></td>
            </tr>
            <tr>
                <td>IP Banned</td>
                <td><?= $is_ip_banned ? 'YES' : 'NO' ?></td>
            </tr>
            <tr>
                <td>Authenticated</td>
                <td><?= $pk_auth ? 'YES' : 'NO' ?></td>
            </tr>
            <tr>
                <td>Session User</td>
                <td><?= htmlspecialchars(\App\Core\Session::sessionUser(), ENT_QUOTES, 'UTF-8') ?></td>
            </tr>
            <tr>
                <td>Pretty URLs</td>
                <td><?= $prettyUrls ? 'YES' : 'NO' ?></td>
            </tr>
        </table>

        <?php $logoutUrl = $prettyUrls ? '/pk-reset' : '?page=pk-reset'; ?>
        <p><a href="<?= htmlspecialchars($logoutUrl, ENT_QUOTES, 'UTF-8') ?>"><b>Restart my Session</b></a></p>
    </div>
<?php endif; ?>