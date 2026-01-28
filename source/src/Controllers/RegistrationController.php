<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;
use PDO;

class RegistrationController extends Controller
{
    public function index(): void
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->handleRegistration();
            $target = ENABLE_PRETTY_URLS ? '/registration-summary' : '?page=registration-summary';
            $this->redirect($target);
        }

        $this->render('pages/registration');
    }

    public function registrationSummary(): void
    {
        // Retrieve summary from session
        $summary = $_SESSION['registration_summary'] ?? [];

        // Clear it immediately after retrieval (one-time use)
        unset($_SESSION['registration_summary']);


        // Display the success page with summary data
        $this->render('pages/registration-summary', $summary);
    }

    private function handleRegistration(): void
    {
        $db = Database::getInstance();

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
        $stmt = $db->prepare("SELECT * FROM get_user(:username)");
        $stmt->bindValue(':username', $username);
        $stmt->execute();
        $user = $stmt->fetch();

        if ($user && password_verify($providedPassword, $user['password_hash'])) {
            $authStatus = $user['authenticated'];
        } elseif (!$user) {
            // ----------------------------
            // Create new user
            // ----------------------------
            try {
                $db->beginTransaction();

                $generatedPassword = $this->generatePassword();
                $passwordHash = password_hash($generatedPassword, GENERATED_PASSWORD_ALGORITHM);

                $stmt = $db->prepare("INSERT INTO users (username, password) VALUES (:username, :password)");
                $stmt->bindValue(':username', $username);
                $stmt->bindValue(':password', $passwordHash);
                $stmt->execute();

                $db->commit();

            } catch (\Exception $e) {
                $db->rollBack();
                error_log("User registration failed for '{$username}': " . $e->getMessage());
            }
        }

        // ----------------------------
        // Record submission
        // ----------------------------
        try {
            $pkSequence = $_SESSION['pk_sequence'] ?? implode('', array_slice($_SESSION['pk_history'] ?? [], -PK_LENGTH));

            $stmt = $db->prepare("
                INSERT INTO registration_form_submissions 
                (username, email, ip_address, user_agent, authenticated, created_by, pk_sequence)
                VALUES
                (:username, :email, :ip_address, :user_agent, :authenticated, :created_by, :pk_sequence)
            ");

            $stmt->bindValue(':username', $username);
            $stmt->bindValue(':email', $email);
            $stmt->bindValue(':ip_address', $_SERVER['REMOTE_ADDR']);
            $stmt->bindValue(':user_agent', substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 512));
            $stmt->bindValue(':authenticated', $authStatus ? 'TRUE' : 'FALSE');
            $stmt->bindValue(':created_by', $username);
            $stmt->bindValue(':pk_sequence', $pkSequence);

            $stmt->execute();

        } catch (\Exception $e) {
            error_log("Registration submission failed for '{$username}': " . $e->getMessage());
        }

        // ----------------------------
        // Save summary to session for redirect
        // ----------------------------
        $_SESSION['registration_summary'] = [
            'username' => $username,
            'email' => $email,
            'authenticated' => $authStatus,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'generated_password' => $generatedPassword
        ];
    }

    /**
     * @throws RandomException
     */
    private function generatePassword($passwordLength = GENERATED_PASSWORD_LENGTH): string
    {
        $randomString = '';

        for ($i = 0; $i < $passwordLength; $i++) {
            $index = random_int(0, strlen(GENERATED_PASSWORD_CHARS) - 1);
            $randomString .= GENERATED_PASSWORD_CHARS[$index];
        }

        return $randomString;
    }
}