<?php
if (ENVIRONMENT === 'development'):
    $pk_auth = $_SESSION['pk_auth'] ?? false;
    $is_banned = $_SESSION['pk_ban'] ?? false;
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
                <td><?= DATABASE_USER ?></td>
            </tr>
            <tr>
                <td>Secret Door</td>
                <td><?= SECRET_DOOR ?></td>
            </tr>
            <tr>
                <td>Secret Page</td>
                <td><?= SECRET_PAGE ?></td>
            </tr>
            <tr>
                <td>Sequence Length</td>
                <td><?= PK_LENGTH ?></td>
            </tr>
            <tr>
                <td>PK History</td>
                <td><?= implode('', $_SESSION['pk_history']) ?></td>
            </tr>
            <tr>
                <td>PK Max History</td>
                <td><?= PK_MAX_HISTORY ?></td>
            </tr>
            <tr>
                <td>PK History Length</td>
                <td><?= count($_SESSION['pk_history']) ?></td>
            </tr>
            <tr>
                <td>PK Banned</td>
                <td><?= ($_SESSION['pk_ban'] ?? false) ? 'YES' : 'NO' ?></td>
            </tr>
            <tr>
                <td>PK Sequence</td>
                <td>
                    <?= $pk_auth
                            ? ($_SESSION['pk_sequence'] ?? 'N/A')
                            : implode('', array_slice($_SESSION['pk_history'], -PK_LENGTH)); ?>
                </td>
            </tr>
            <tr>
                <td>IP Address</td>
                <td><?= $_SERVER['REMOTE_ADDR'] ?></td>
            </tr>
            <tr>
                <td>IP Banned</td>
                <td><?= $is_banned ? 'YES' : 'NO' ?></td>
            </tr>
            <tr>
                <td>Authenticated</td>
                <td><?= $pk_auth ? 'YES' : 'NO' ?></td>
            </tr>
            <tr>
                <td>SessionUser</td>
                <td><?= App\Core\Session::sessionUser() ?></td>
            </tr>
        </table>


        <?php
        $logoutUrl = ENABLE_PRETTY_URLS ? '/pk-reset' : '?page=pk-reset';
        ?>
        <p><a href="<?= $logoutUrl ?>"><b>Restart my Session</b></a></p>


    </div>
<?php endif; ?>
