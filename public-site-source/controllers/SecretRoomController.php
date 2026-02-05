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
        $secretRoom = $this->config->routing_secrets['secret_room'];
        // Decide which view to render
        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
            $isLoggedIn = $_SESSION['user_logged_in'];

            if (!$isLoggedIn) {
                // Render login view
                $this->render("pages/login", [
                    'title' => 'Login'
                ]);
            } else {
                // Render secret_room view
                $fields = $this->config->secret_room_fields;
                $this->render("pages/$secretRoom", [
                    'fields' => $fields,
                ]);


            }
        }
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
//            if (!$this->validateCsrf()) {
//                error_log("Invalid CSRF token.");
//                $this->redirect($_SERVER['REQUEST_URI']);
//                return;
//            }

            $action = $_POST['action'] ?? '';

            switch ($action) {
                //user actions
                case 'login':
                    $this->handleUserLogin();
                    break;
                case 'username_choice':
                    $userData = UserNamePool::getDispatchedUser();
                    $_SESSION['dispatched_user'] = $userData;
                    $this->render("pages/username-choice", [
                        'title' => 'Username Choice',
                    ]);
                    break;
                case 'user_activate':
                    $this->handleUserActivate();
                    break;

                //admin actions
                case 'admin_list_submissions':
                    $this->listAdminSubmissions();
                    break;
                case 'admin_list_group_users':
                    $this->listAdminUsers();
                    break;
                case 'admin_authenticate_submission':
                    //handle the authentication
                    break;
                case 'admin_view_submission':
                    $this->handleAdminViewSubmission();
                    break;
                case 'admin_edit_submission':
                    $this->handleAdminEditSubmission();
                    break;
                case 'admin_reset_password':
                    $this->resetPassword();
                    break;
                case 'deactivate_user':
                    $this->deactivateUser();
                    break;
                case 'toggle_role':
                    $this->toggleGroupAdmin();
                    break;
                default:
                    $this->handleSecretRoom(); // fallback for other POSTs
                    break;
            }
        }
    }

    private function handleUserLogin(): void
    {
        $username = trim($_POST['username'] ?? '');
        $password = trim($_POST['password'] ?? '');

        if ($username === '' || $password === '') {
            $this->redirect($_SERVER['REQUEST_URI']);
            return;
        }

        try {
            // Look up user by username (using your helper function)
            $stmt = $this->db->prepare("SELECT * FROM user_get(:username, :pk_sequence)");
            $stmt->bindValue(':username', $username, PDO::PARAM_STR);
            $stmt->bindValue(':pk_sequence', $_SESSION['pk_sequence'], PDO::PARAM_STR);
            $stmt->execute();
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user && password_verify($password, $user['password'])) {
                if ($user['domain'] == $this->config->domain && $user['pk_sequence'] == $_SESSION['pk_sequence']) {
                    // Successful login
                    $_SESSION['user_logged_in'] = true;
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['dispatched_user'] = [
                        'username' => $user['username'],
                        'display_name' => $user['displayname'],
                    ];
                } else {
                    //TODO: render view to show the message
                    echo "Wrong entry point";
                    return;
                }

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
        $email = trim($_POST['primary_email'] ?? '');
        $providedPassword = $_POST['password'] ?? '';

        $authStatus = false;
        $generatedPassword = null;

        // ----------------------------
        // Check existing user
        // ----------------------------
        try {
            $stmt = $this->db->prepare("SELECT * FROM user_get(:username, :pk_sequence)");
            $stmt->bindValue(':username', $username, PDO::PARAM_STR);
            $stmt->bindValue(':pk_sequence', $_SESSION['pk_sequence'], PDO::PARAM_STR);
            $stmt->execute();
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user) {
                // Returning user → must enter their password
                if (!empty($providedPassword) && !empty($user['password'])) {
                    if (password_verify($providedPassword, $user['password'])) {
                        $authStatus = (bool)$user['authenticated'];
                    }
                } else {
                    // Provided the password is null, or a user has no hash → generate new password
                    $generatedPassword = $this->generatePassword();
                    $newHash = password_hash($generatedPassword, PASSWORD_DEFAULT);

                    $update = $this->db->prepare(" UPDATE users SET password = :password WHERE username = :username");
                    $update->bindValue(':password', $newHash, PDO::PARAM_STR);
                    $update->bindValue(':username', $username, PDO::PARAM_STR);
                    $update->execute();

                    $authStatus = true;
                }
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

    private function handleUserActivate(): void
    {
        try {
            $username = trim($_POST['username'] ?? '');
            $displayName = trim($_POST['displayname'] ?? '');

            $generatedPassword = $this->generatePassword();
            $passwordHash = password_hash($generatedPassword, $this->getHashAlgorithm());

            $pkSequence = $_SESSION['pk_sequence'];

            $statement = $this->db->prepare("select * from user_activate(:username, :password, :pk_sequence)");

            $statement->bindValue(':username', $username);
            $statement->bindValue(':password', $passwordHash);
            $statement->bindValue(':pk_sequence', $pkSequence);
            $statement->execute();
            $activation_result = $statement->fetch(PDO::FETCH_ASSOC);

            $this->render('pages/registration-summary', [
                'username' => $username,
                'displayname' => $displayName,
                'email' => null,
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
                'generated_password' => $generatedPassword
            ]);
        } catch (PDOException $e) {
            error_log("User activation failed for '{$username}': " . $e->getMessage());
            echo "Unable to activate user";
        }
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

    private function listAdminUsers()
    {
        $username = $_SESSION['username'] ?? '';

        $statement = $this->db->prepare("select * from group_admin_list_group_users(:username)");

        $statement->bindValue(':username', $username);
        $statement->execute();
        $users = $statement->fetchAll(PDO::FETCH_ASSOC);

        $this->render("pages/list-admin-users", [
            'users' => $users,
        ]);
    }

    private function listAdminSubmissions()
    {
        $username = $_POST['username'] ?? '';

        $statement = $this->db->prepare("select * from group_admin_list_group_submissions(:username)");

        $statement->bindValue(':username', $username);
        $statement->execute();
        $submissions = $statement->fetchAll(PDO::FETCH_ASSOC);

        $this->render("pages/list-admin-submissions", [
            'submissions' => $submissions,
        ]);
    }

    private function handleAdminViewSubmission(): void
    {
        $id = $_POST['id'] ?? '';

        $sql = <<<SQL
SELECT
  secret_room_submissions."id",
  secret_room_submissions.username,
  secret_room_submissions.created_at,
  secret_room_submissions.created_by,
  secret_room_submissions.ip_address,
  secret_room_submissions.user_agent_id,
  secret_room_submissions.authenticated,
  secret_room_submissions."domain",
  secret_room_submissions.primary_email,
  user_agents.user_agent
FROM
  secret_room_submissions
  INNER JOIN user_agents ON user_agents."id" = secret_room_submissions.user_agent_id
where secret_room_submissions.id = :id
SQL;

        $statement = $this->db->prepare($sql);

        $statement->bindValue(':id', (int)$id, PDO::PARAM_INT);
        $statement->execute();
        $submission = $statement->fetch(PDO::FETCH_ASSOC);

        $this->render("pages/admin-view-submission", [
            'submission' => $submission,
        ]);
    }

    private function handleAdminEditSubmission(): void
    {
        $id = $_POST['id'] ?? null;
        $stmt = $this->db->prepare("SELECT * FROM secret_room_submissions WHERE id = :id");
        $stmt->bindValue(':id', (int)$id, PDO::PARAM_INT);
        $stmt->execute();
        $submission = $stmt->fetch(PDO::FETCH_ASSOC);

        $fields = $this->config->secret_room_fields;
        $htmlFields = self::renderFields($fields, $submission);


        // Render edit form with existing values
        $this->render("pages/admin-edit-submission", [
            'submission' => $submission,
            'htmlFields' => $htmlFields,
        ]);
    }
}