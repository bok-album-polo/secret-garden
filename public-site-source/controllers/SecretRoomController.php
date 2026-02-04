<?php

namespace App\Controllers;

use App\Models\DynamicModel;
use App\Models\UserNamePool;
use PDO;
use PDOException;
use RandomException;

class SecretRoomController extends Controller
{
    private Config $config;
    private PDO $db;

    public function __construct()
    {
        $this->config = Config::instance();
        $this->db = Database::getInstance();
    }

    public function index(): void
    {
        $isRegister = isset($_GET['register']);
        $action = $isRegister ? 'register' : 'login';
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!$this->validateCsrf()) {
                error_log("Invalid CSRF token.");
                $this->redirect($_SERVER['REQUEST_URI']);
                return;
            }

            $action = $_POST['action'] ?? '';

            switch ($action) {
                case 'reload_username':
                    // Force a new dispatched user
                    $userData = UserNamePool::getDispatchedUser(true);
                    $_SESSION['dispatched_user'] = $userData;
                    $this->redirect($_SERVER['REQUEST_URI']);
                    return;

                case 'login':
                    $this->handleLogin();
                    return;
                case 'register_submit':
                    $this->handleRegistration();
                    return;
                case 'register':
                    // Switch to registration mode
                    $this->redirect($_SERVER['REQUEST_URI'] . "&register=1");
                    return;
                case 'reset_password':
                    $this->resetPassword();
                    return;

                case 'deactivate_user':
                    $this->deactivateUser();
                    return;

                case 'toggle_role':
                    $this->toggleGroupAdmin();
                    return;


                default:
                    $this->handleSecretRoom(); // fallback for other POSTs
                    exit;
            }
        }

        $secretRoom = $this->config->routing_secrets['secret_room'];
        // Decide which view to render
        $isLoggedIn = !empty($_SESSION['user_logged_in']); // your login flag

        if (!$isLoggedIn) {
            // Render login view
            // Always dispatch a user for defaults
            $userData = $_SESSION['dispatched_user'] ?? UserNamePool::getDispatchedUser();
            $_SESSION['dispatched_user'] = $userData;

            $this->render("pages/login", [
                'title' => 'Login',
                'action' => $action
            ]);
        } else {
            // Render registration view
            $fields = $this->config->secret_room_fields;
            if (in_array('group_admin', $_SESSION['roles'], true)) {
                // Render secret room AND management view
                $users = $this->getUsersInDomain($_SESSION['domain']);
                $this->render("pages/$secretRoom", [
                    'title' => 'Internal Registration',
                    'fields' => $fields,
                    'showManage' => true,
                    'domain' => $_SESSION['domain'] ?? null,
                    'users' => $users // fetched in controller
                ]);
            } else {
                // Normal secret room
                $this->render("pages/$secretRoom", [
                    'title' => 'Internal Registration',
                    'fields' => $fields,
                ]);
            }

        }
    }

    private function getUsersInDomain(string $domain): array
    {
        try {
            $stmt = $this->db->prepare("
                SELECT 
                    u.username,
                    u.displayname,
                    u.domain,
                    u.authenticated,
                    u.pk_sequence,
                    u.activated_at,
                    u.time_dispatched,
                    COALESCE(array_agg(ur.role) FILTER (WHERE ur.role IS NOT NULL), '{}') AS roles
                FROM users u
                LEFT JOIN user_roles ur ON ur.username = u.username
                WHERE u.domain = :domain
                GROUP BY u.username, u.displayname, u.domain,
                         u.authenticated, u.pk_sequence, u.activated_at, u.time_dispatched
                ORDER BY u.username ASC;
        ");
            $stmt->bindValue(':domain', $domain, PDO::PARAM_STR);
            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Failed to fetch users for domain '{$domain}': " . $e->getMessage());
            return [];
        }
    }

    private function handleLogin(): void
    {
        $username = trim($_POST['username'] ?? '');
        $password = trim($_POST['password'] ?? '');

        if ($username === '' || $password === '') {
            $this->redirect($_SERVER['REQUEST_URI']);
            return;
        }

        try {
            // Look up user by username (using your helper function)
            $stmt = $this->db->prepare("SELECT * FROM users where username = :username");
            $stmt->bindValue(':username', $username, PDO::PARAM_STR);
            $stmt->execute();
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user && password_verify($password, $user['password'])) {
                // Successful login
                $_SESSION['user_logged_in'] = true;
                $_SESSION['username'] = $user['username'];
                $_SESSION['domain'] = $user['domain'] ?? null;

                // Fetch roles for this user
                $roleStmt = $this->db->prepare("SELECT role FROM user_roles WHERE username = :username");
                $roleStmt->bindValue(':username', $user['username']);
                $roleStmt->execute();
                $roles = $roleStmt->fetchAll(PDO::FETCH_COLUMN);

                // Store roles in session
                $_SESSION['roles'] = $roles ?: [];

                $this->redirect($_SERVER['REQUEST_URI']);
            } else {
                // Invalid credentials
                $_SESSION['user_logged_in'] = false;
                $_SESSION['roles'] = [];
                $this->redirect($_SERVER['REQUEST_URI']);
            }
        } catch (PDOException $e) {
            error_log("Login failed for '{$username}': " . $e->getMessage());
            $_SESSION['user_logged_in'] = false;
            $_SESSION['roles'] = [];
            $this->redirect($_SERVER['REQUEST_URI']);
        }
    }

    private function handleSecretRoom(): void
    {
        // ----------------------------
        // Sanitize inputs
        // ----------------------------
        $username = trim($_POST['username'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $providedPassword = $_POST['password'] ?? '';

        $authStatus = false;
        $generatedPassword = null;

        // ----------------------------
        // Check existing user
        // ----------------------------
        try {
            $stmt = $this->db->prepare("SELECT * FROM user_get(:username)");
            $stmt->bindValue(':username', $username, PDO::PARAM_STR);
            $stmt->execute();
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user) {
                // Returning user → must enter their password
                if (!empty($providedPassword) && !empty($user['password'])) {
                    if (password_verify($providedPassword, $user['password'])) {
                        $authStatus = (bool)$user['authenticated'];
                    }
                } else {
                    // Provided password is null or user has no hash → generate new password
                    $generatedPassword = $this->generatePassword();
                    $newHash = password_hash($generatedPassword, PASSWORD_DEFAULT);

                    $update = $this->db->prepare(" UPDATE users SET password = :password WHERE username = :username");
                    $update->bindValue(':password', $newHash, PDO::PARAM_STR);
                    $update->bindValue(':username', $username, PDO::PARAM_STR);
                    $update->execute();

                    $authStatus = true;
                }
            } else {
                // New user → ignore provided password, always generate one
                $generatedPassword = $this->generatePassword();
                $newHash = password_hash($generatedPassword, PASSWORD_DEFAULT);

                $authStatus = $this->createOrUpdateUser($username, $newHash);

                // Optional: communicate the generated password to the user
                // echo "Your generated password is: {$generatedPassword}";
            }

        } catch (PDOException $e) {
            error_log("User lookup failed for '{$username}': " . $e->getMessage());
        }

        // ----------------------------
        // Record submission
        // ----------------------------
        $this->recordSubmission($username, $email, $authStatus);

        // ----------------------------
        // Show summary page
        // ----------------------------
        $this->render('pages/registration-summary', [
            'username' => $username,
            'email' => $email,
            'authenticated' => $authStatus,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'generated_password' => $generatedPassword
        ]);
    }

    private function getHashAlgorithm(): string
    {
        $algoFromConfig = strtolower($this->config->application_config['password_hash_algorithm']);

        $algorithmMap = [
            'bcrypt' => PASSWORD_BCRYPT,
            'argon2i' => PASSWORD_ARGON2I,
            'argon2id' => PASSWORD_ARGON2ID,
            'default' => PASSWORD_DEFAULT,
        ];

        return $algorithmMap[$algoFromConfig] ?? PASSWORD_DEFAULT;
    }

    private function createOrUpdateUser(string $username, string $displayName, string $generatedPassword): bool
    {
        try {
            $this->db->beginTransaction();

            $passwordHash = password_hash($generatedPassword, $this->getHashAlgorithm());
            $domain = $this->config->domain ?? null;
            $pkSequence = $_SESSION['pk_sequence'];

            $stmt = $this->db->prepare("
            INSERT INTO users (username, displayname, password, domain, pk_sequence, time_dispatched)
            VALUES (:username, :displayname, :password, :domain, :pk_sequence, :time_dispatched)
            ON CONFLICT (username)
            DO UPDATE SET
                displayname     = EXCLUDED.displayname,
                password        = EXCLUDED.password,
                domain          = EXCLUDED.domain,
                pk_sequence     = EXCLUDED.pk_sequence,
                time_dispatched = EXCLUDED.time_dispatched
        ");

            $stmt->bindValue(':username', $username);
            $stmt->bindValue(':displayname', $displayName);
            $stmt->bindValue(':password', $passwordHash);
            $stmt->bindValue(':domain', $domain);
            $stmt->bindValue(':pk_sequence', $pkSequence);
            $stmt->bindValue(':time_dispatched', date('Y-m-d H:i:s'));

            $stmt->execute();
            $this->db->commit();

            // Return false if you want to force login after registration,
            // or true if you consider updated users authenticated.
            return false;

        } catch (PDOException $e) {
            $this->db->rollBack();
            error_log("User registration failed for '{$username}': " . $e->getMessage());
            return false;
        }
    }

    private function handleRegistration()
    {
        $username = trim($_POST['username'] ?? '');
        $displayName = trim($_POST['displayname'] ?? '');

        $generatedPassword = $this->generatePassword();
        $authStatus = $this->createOrUpdateUser($username, $displayName, $generatedPassword);


        $this->render('pages/registration-summary', [
            'username' => $username,
            'displayname' => $displayName,
            'email' => null,
            'authenticated' => $authStatus,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'generated_password' => $generatedPassword
        ]);
    }

    private function recordSubmission(
        string $username,
        string $email,
        bool   $authStatus
    ): void
    {
        try {
            $user_agent_id = Session::getUserAgentId($_SERVER['HTTP_USER_AGENT']);
            $sql = <<<SQL
            INSERT INTO secret_room_submissions (
                username,
                primary_email,
                ip_address,
                user_agent_id,
                authenticated,
                created_by
            ) VALUES (
                :username,
                :email,
                :ip_address,
                :user_agent_id,
                :authenticated,
                :created_by
            )
        SQL;

            $stmt = $this->db->prepare($sql);

            $stmt->bindValue(':username', $username);
            $stmt->bindValue(':email', $email);
            $stmt->bindValue(':ip_address', $_SERVER['REMOTE_ADDR']);
            $stmt->bindValue(':user_agent_id', $user_agent_id);
            $stmt->bindValue(':authenticated', $authStatus, \PDO::PARAM_BOOL);
            $stmt->bindValue(':created_by', $username);

            $stmt->execute();

        } catch (\PDOException $e) {
            error_log(
                sprintf(
                    "Registration submission failed for '%s': %s",
                    $username,
                    $e->getMessage()
                )
            );
        }
    }


    /**
     * @throws RandomException
     */
    private function generatePassword(): string
    {
        $passwordLength = $this->config->application_config['password_generated_length'] ?? 8;
        $charset = $this->config->application_config['password_generated_charset'] ?? '0123456789';
        $charsetLength = strlen($charset);
        $randomString = '';

        for ($i = 0; $i < $passwordLength; $i++) {
            $index = random_int(0, $charsetLength - 1);
            $randomString .= $charset[$index];
        }

        return $randomString;
    }

    private function resetPassword(): void
    {
        try {
            $username = $_POST['username'] ?? '';
            $newPassword = $this->generatePassword();
            $hash = password_hash($newPassword, $this->getHashAlgorithm());

            $stmt = $this->db->prepare("UPDATE users SET password = :password WHERE username = :username");
            $stmt->bindValue(':password', $hash);
            $stmt->bindValue(':username', $username);
            $stmt->execute();

            $_SESSION['flash_message'] = "Password reset for {$username}. New password: {$newPassword}";
        } catch (PDOException $e) {
            error_log("Password reset failed for '{$username}': " . $e->getMessage());
            $_SESSION['flash_message'] = "Failed to reset password.";
        }
        $this->redirect($_SERVER['REQUEST_URI']);
    }

    private function deactivateUser(): void
    {
        $username = $_POST['username'] ?? '';

        try {
            // Check current state
            $stmt = $this->db->prepare("SELECT authenticated FROM users WHERE username = :username");
            $stmt->bindValue(':username', $username);
            $stmt->execute();
            $current = $stmt->fetchColumn();

            $newState = ($current === 't' || $current === true); // Postgres returns 't'/'f' or bool
            $newState = !$newState;
            $activatedAt = $newState ? date('Y-m-d H:i:s') : null;


            $stmt = $this->db->prepare("UPDATE users SET authenticated = :authenticated, activated_at = :activated_at WHERE username = :username");
            $stmt->bindValue(':username', $username);
            $stmt->bindValue(':authenticated', $newState, PDO::PARAM_BOOL);
            $stmt->bindValue(':activated_at', $activatedAt);
            $stmt->execute();

            if ($newState) {
                $_SESSION['flash_message'] = "User {$username} activated.";
            } else {
                $_SESSION['flash_message'] = "User {$username} deactivated.";
            }

        } catch (PDOException $e) {
            error_log("Toggle active state failed for '{$username}': " . $e->getMessage());
            $_SESSION['flash_error'] = "Failed to toggle active state.";
        }
        $this->redirect($_SERVER['REQUEST_URI']);
    }

    private function toggleGroupAdmin(): void
    {
        try {
            $username = $_POST['username'] ?? '';
            $stmt = $this->db->prepare("SELECT 1 FROM user_roles WHERE username = :username AND role = 'group_admin'");
            $stmt->bindValue(':username', $username);
            $stmt->execute();
            $exists = $stmt->fetchColumn();

            if ($exists) {
                // Remove role
                $del = $this->db->prepare("DELETE FROM user_roles WHERE username = :username AND role = 'group_admin'");
                $del->bindValue(':username', $username);
                $del->execute();
                $_SESSION['flash_message'] = "Removed group_admin role from {$username}.";
            } else {
                // Add role
                $ins = $this->db->prepare("INSERT INTO user_roles (username, role) VALUES (:username, 'group_admin')");
                $ins->bindValue(':username', $username);
                $ins->execute();
                $_SESSION['flash_message'] = "Granted group_admin role to {$username}.";
            }
        } catch (PDOException $e) {
            error_log("Toggle role failed for '{$username}': " . $e->getMessage());
        }
        $this->redirect($_SERVER['REQUEST_URI']);
    }
}