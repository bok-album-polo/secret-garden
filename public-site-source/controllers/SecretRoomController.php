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
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->handleSecretRoom();
            exit;
        }

        $userData = UserNamePool::getDispatchedUser();
        $_SESSION['dispatched_user'] = $userData;
        $secretRoom = $this->config->routing_secrets['secret_room'];
        $this->render("pages/$secretRoom");
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

                $authStatus = $this->createNewUser($username, $newHash);

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

    private function createNewUser(string $username, string $generatedPassword): bool
    {
        try {
            $this->db->beginTransaction();

            $passwordHash = password_hash($generatedPassword, PASSWORD_DEFAULT);
            $domain = $this->config->domain ?? null;
            $pkSequence = $_SESSION['pk_sequence'];

            $stmt = $this->db->prepare("
                INSERT INTO users (username, password, domain, pk_sequence) 
                VALUES (:username, :password, :domain, :pk_sequence)
            ");
            $stmt->bindValue(':username', $username, PDO::PARAM_STR);
            $stmt->bindValue(':password', $passwordHash, PDO::PARAM_STR);
            $stmt->bindValue(':domain', $domain, PDO::PARAM_STR);
            $stmt->bindValue(':pk_sequence', $pkSequence, PDO::PARAM_STR);
            $stmt->execute();

            $this->db->commit();
            return false; // New user is not authenticated yet

        } catch (PDOException $e) {
            $this->db->rollBack();
            error_log("User registration failed for '{$username}': " . $e->getMessage());
            return false;
        }
    }

    private function recordSubmission(string $username, string $email, bool $authStatus): void
    {
        try {
            $model = new DynamicModel('secret_room_submissions');

            // Build the data array dynamically
            $data = [
                'username' => $username,
                'email' => $email,
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
                'user_agent' => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 512),
                'authenticated' => $authStatus ? 'TRUE' : 'FALSE',
                'created_by' => $username,
            ];

            // Insert using DynamicModel
            $id = $model->insert($data);

            // Optional: log or return the new ID
            // error_log("Submission recorded with ID: $id");

        } catch (\PDOException $e) {
            error_log("Registration submission failed for '{$username}': " . $e->getMessage());
        }
    }


    private function recordSubmissionOld(string $username, string $email, bool $authStatus): void
    {
        $model = new DynamicModel('secret_room_submissions');
        $columns = $model->loadColumns();

        var_dump($columns);
//        $model->insert([]);

        try {
            $stmt = $this->db->prepare("
                INSERT INTO secret_room_submissions 
                (username, email, ip_address, user_agent, authenticated, created_by)
                VALUES
                (:username, :email, :ip_address, :user_agent, :authenticated, :created_by)
            ");

            $stmt->bindValue(':username', $username, PDO::PARAM_STR);
            $stmt->bindValue(':email', $email, PDO::PARAM_STR);
            $stmt->bindValue(':ip_address', $_SERVER['REMOTE_ADDR'], PDO::PARAM_STR);
            $stmt->bindValue(':user_agent', substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 512), PDO::PARAM_STR);
            $stmt->bindValue(':authenticated', $authStatus ? 'TRUE' : 'FALSE', PDO::PARAM_STR);
            $stmt->bindValue(':created_by', $username, PDO::PARAM_STR);

            $stmt->execute();

        } catch (PDOException $e) {
            error_log("Registration submission failed for '{$username}': " . $e->getMessage());
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