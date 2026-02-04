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

                default:
                    $this->handleSecretRoom(); // fallback for other POSTs
                    exit;
            }
        }
        // Always dispatch a user for defaults
        $userData = $_SESSION['dispatched_user'] ?? UserNamePool::getDispatchedUser();
        $_SESSION['dispatched_user'] = $userData;

        $secretRoom = $this->config->routing_secrets['secret_room'];
        // Decide which view to render
        $isLoggedIn = !empty($_SESSION['user_logged_in']); // your login flag

        if (!$isLoggedIn) {
            // Render login view

            $this->render("pages/login", [
                'title' => 'Login',
                'action' => $action
            ]);
        } else {
            // Render registration view
            $fields = $this->config->secret_door_fields;
            $this->render("pages/$secretRoom", [
                'title' => 'Internal Registration',
                'fields' => $fields,
            ]);
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
            // Look up user by username
            $stmt = $this->db->prepare("SELECT * FROM user_get(:username)");
            $stmt->bindValue(':username', $username, PDO::PARAM_STR);
            $stmt->execute();
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user && password_verify($password, $user['password'])) {
                // Successful login
                $_SESSION['user_logged_in'] = true;
                $_SESSION['username'] = $user['username'];
                $_SESSION['user_id'] = $user['id'];

                $this->redirect($_SERVER['REQUEST_URI']);
            } else {
                // Invalid credentials
                $_SESSION['user_logged_in'] = false;
                $this->redirect($_SERVER['REQUEST_URI']);
            }
        } catch (PDOException $e) {
            error_log("Login failed for '{$username}': " . $e->getMessage());
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
        $passwordLength = $this->config->application_config['generated_password_length'] ?? 8;
        $charset = $this->config->application_config['generated_password_charset'] ?? '0123456789';
        $charsetLength = strlen($charset);
        $randomString = '';

        for ($i = 0; $i < $passwordLength; $i++) {
            $index = random_int(0, $charsetLength - 1);
            $randomString .= $charset[$index];
        }

        return $randomString;
    }
}