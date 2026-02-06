<?php

namespace App\Controllers;

use App\Models\UserNamePool;
use PDO;
use PDOException;

class SecretRoomController extends Controller
{

    public function index(): void
    {
        $secretRoom = $this->config->routing_secrets['secret_room'];
        $mode = $this->config->project_meta['mode'];

        // Decide which view to render
        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
            $isLoggedIn = $_SESSION['user_logged_in'];
            if (!$isLoggedIn) {
                // Render login view
                $this->render("user-login", [
                    'title' => 'Login'
                ]);
                return; // stop here so secret room isn't rendered
            }

            // For all other modes, or if logged in in writeonly mode
            $fields = $this->config->secret_room_fields;
            $this->render("$secretRoom", [
                'fields' => $fields,
            ]);
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
                    $this->render("username-choice", [
                        'title' => 'Username Choice',
                    ]);
                    break;
                case 'user_activate':
                    $this->handleUserActivate();
                    break;
                case 'user_logout':
                    Session::clear_auth_trackers();
                    $this->redirect("/");
                    break;

                //admin actions
                case 'admin_list_submissions':
                    $this->listAdminSubmissions();
                    break;
                case 'admin_list_group_users':
                    $this->listAdminUsers();
                    break;
                case 'admin_authenticate_submission':
                    $this->handleAdminAuthenticateSubmission();
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
            $stmt->bindValue(':username', $username);
            $stmt->bindValue(':pk_sequence', $_SESSION['pk_sequence']);
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

                $this->redirect($_SERVER['REQUEST_URI']);
            } else {
                // Invalid credentials
                $_SESSION['user_logged_in'] = false;
                $_SESSION['roles'] = [];
                $this->redirect($_SERVER['REQUEST_URI']);
            }
        } catch (PDOException $e) {
            error_log("Login failed for '$username': " . $e->getMessage());
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
        //$username = trim($_POST['username'] ?? ''); use for writeonly mode


//        $authStatus = false;
//        $generatedPassword = null;
//
//        // ----------------------------
//        // Check existing user---writeonly mode
//        // ----------------------------
//        try {
//            $stmt = $this->db->prepare("SELECT * FROM user_get(:username, :pk_sequence)");
//            $stmt->bindValue(':username', $username, PDO::PARAM_STR);
//            $stmt->bindValue(':pk_sequence', $_SESSION['pk_sequence'], PDO::PARAM_STR);
//            $stmt->execute();
//            $user = $stmt->fetch(PDO::FETCH_ASSOC);
//
//            if ($user) {
//                // Returning user → must enter their password
//                if (!empty($providedPassword) && !empty($user['password'])) {
//                    if (password_verify($providedPassword, $user['password'])) {
//                        $authStatus = (bool)$user['authenticated'];
//                    }
//                } else {
//                    // Provided the password is null, or a user has no hash → generate new password
//                    $generatedPassword = $this->generatePassword();
//                    $newHash = password_hash($generatedPassword, PASSWORD_DEFAULT);
//
//                    $update = $this->db->prepare(" UPDATE users SET password = :password WHERE username = :username");
//                    $update->bindValue(':password', $newHash, PDO::PARAM_STR);
//                    $update->bindValue(':username', $username, PDO::PARAM_STR);
//                    $update->execute();
//
//                    $authStatus = true;
//                }
//            }
//
//        } catch (PDOException $e) {
//            error_log("User lookup failed for '{$username}': " . $e->getMessage());
//        }

        // ----------------------------
        // Record submission
        // ----------------------------
        $fields = $this->config->secret_room_fields;
        $username = trim($_POST['username'] ?? $_SESSION['username']);
        $email = trim($_POST['primary_email'] ?? '');
        $user_agent_id = Session::getUserAgentId($_SERVER['HTTP_USER_AGENT']);
        $data = [
            'username' => $username,
            'created_by' => $username,
            'primary_email' => $email,
            'user_agent_id' => $user_agent_id,
            'ip_address' => $_SERVER['REMOTE_ADDR'],
        ];


        $fields = array_merge($fields, [
            ['name' => 'username'],
            ['name' => 'created_by'],
            ['name' => 'ip_address'],
            ['name' => 'user_agent_id'],
        ]);

        $this->recordSubmission(fields: $fields, data: $data, isSecretRoom: true);
        $this->redirect($_SERVER['REQUEST_URI']);
    }

    private function handleUserActivate(): void
    {
        try {
            $username = trim($_POST['username'] ?? '');
            $displayName = trim($_POST['displayname'] ?? '');
            $password = trim($_POST['password'] ?? '');

            $generatedPassword = $this->generatePassword();

            $passwordHash = password_hash($generatedPassword, constant($this->config->application_config['password_hash_algorithm']));

            $pkSequence = $_SESSION['pk_sequence'];

            $statement = $this->db->prepare("select * from user_activate(:username, :password, :pk_sequence)");

            $statement->bindValue(':username', $username);
            $statement->bindValue(':password', $passwordHash);
            $statement->bindValue(':pk_sequence', $pkSequence);
            $statement->execute();
            $user = $statement->fetch(PDO::FETCH_ASSOC);

            $_SESSION['user_logged_in'] = true;
            $_SESSION['username'] = $user['username'];
            $_SESSION['dispatched_user'] = [
                'username' => $user['username'],
                'display_name' => $user['displayname'],
            ];

            $this->render('registration-summary', [
                'username' => $username,
                'displayname' => $displayName,
                'generated_password' => $generatedPassword
            ]);
        } catch (PDOException $e) {
            error_log("User activation failed for '{$username}': " . $e->getMessage());
            echo "Unable to activate user";
        }
    }

    /**
     * @throws \Random\RandomException
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
            $hash = password_hash($newPassword, constant($this->config->application_config['password_hash_algorithm']));

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

    private function listAdminUsers(): void
    {
        $username = $_SESSION['username'] ?? '';

        $statement = $this->db->prepare("select * from group_admin_list_group_users(:username)");

        $statement->bindValue(':username', $username);
        $statement->execute();
        $users = $statement->fetchAll(PDO::FETCH_ASSOC);

        $this->render("admin-list-users", [
            'users' => $users,
        ]);
    }

    private function listAdminSubmissions(): void
    {
        $username = $_SESSION['username'] ?? '';

        $statement = $this->db->prepare("select * from group_admin_list_group_submissions(:username)");

        $statement->bindValue(':username', $username);
        $statement->execute();
        $submissions = $statement->fetchAll(PDO::FETCH_ASSOC);

        $this->render("admin-list-submissions", [
            'submissions' => $submissions,
        ]);
    }

    private function handleAdminAuthenticateSubmission(): void
    {
        $id = $_POST['id'] ?? '';

        $sql = "UPDATE secret_room_submissions set authenticated = 1 where id = :id";

        $statement = $this->db->prepare($sql);

        $statement->bindValue(':id', (int)$id, PDO::PARAM_INT);
        $statement->execute();
        $submission = $statement->fetch(PDO::FETCH_ASSOC);

        $this->render("admin-view-submission", [
            'submission' => $submission,
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

        $this->render("admin-view-submission", [
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

        $fields = array_merge($fields, [
            ['name' => 'username', 'html_type' => 'text', 'readonly' => true,],
        ]);


        // Render edit form with existing values
        $this->render("admin-edit-submission", [
            'submission' => $submission,
            'fields' => $fields,
        ]);
    }
}