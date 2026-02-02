<?php

namespace App\Controllers;

use PDO;
use PDOException;
use RuntimeException;

class SecretDoorController extends Controller
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
            $this->handleContact();
            $this->redirect($_SERVER['REQUEST_URI']);
        }

        $this->render('pages/contact-us', [
            'title' => 'Contact Us',
        ]);
    }

    private function handleContact(): void
    {
        // ----------------------------
        // Honeypot check - ban IP
        // ----------------------------
        $this->banIpAddress($_SERVER['REMOTE_ADDR'], 'Honeypot Triggered');

        // ----------------------------
        // Basic validation
        // ----------------------------
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $message = trim($_POST['message'] ?? '');

        // ----------------------------
        // File upload (optional)
        // ----------------------------
        $uploadedFile = null;

        try {
            $uploadedFile = $this->handleFileUploadToDb('file');
        } catch (RuntimeException $e) {
            error_log(
                'File upload failed from IP ' .
                $_SERVER['REMOTE_ADDR'] . ': ' . $e->getMessage()
            );
        }

        // ----------------------------
        // Persist submission
        // ----------------------------
        $this->recordContactSubmission($name, $email, $message, $uploadedFile);
    }

    private function banIpAddress(string $ipAddress, string $reason): void
    {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO ip_bans (network, reason)
                VALUES (:network, :reason)
                ON CONFLICT DO NOTHING
            ");
            $stmt->bindValue(':network', $ipAddress . '/32');
            $stmt->bindValue(':reason', $reason);
            $stmt->execute();
        } catch (PDOException $e) {
            error_log("IP ban insertion failed for '$ipAddress': " . $e->getMessage());
        }
    }

    private function recordContactSubmission(
        string $name,
        string $email,
        string $message,
        ?array $uploadedFile
    ): void
    {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO contact_form_submissions
                (name, email, message, ip_address, user_agent, uploaded_file, uploaded_file_name)
                VALUES
                (:name, :email, :message, :ip_address, :user_agent, :uploaded_file, :uploaded_file_name)
            ");

            $stmt->bindValue(':name', $name);
            $stmt->bindValue(':email', $email);
            $stmt->bindValue(':message', $message);
            $stmt->bindValue(':ip_address', $_SERVER['REMOTE_ADDR']);
            $stmt->bindValue(':user_agent', substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 512));
            $stmt->bindValue(':uploaded_file', $uploadedFile['data'] ?? null, PDO::PARAM_LOB);
            $stmt->bindValue(':uploaded_file_name', $uploadedFile['name'] ?? null);

            $stmt->execute();
        } catch (PDOException $e) {
            error_log("Contact form submission failed: " . $e->getMessage());
        }
    }

    private function handleFileUploadToDb(string $inputName): ?array
    {
        if (empty($_FILES[$inputName]['name'])) {
            return null;
        }

        $maxSize = $this->config->application_config['max_upload_size'] ?? 1048576; // Default 1MB
        $file = $_FILES[$inputName];
        $filename = basename($file['name']);
        $fileContent = '';

        if ($file['error'] !== UPLOAD_ERR_OK) {
            // If upload failed, return error code as filename and empty content
            $filename = "Upload error code {$file['error']}";
        } elseif ($file['size'] > $maxSize) {
            // If file too large, keep filename but empty content
            error_log("File '$filename' exceeds maximum size of $maxSize bytes");
        } else {
            // Read file content
            $content = file_get_contents($file['tmp_name']);
            if ($content !== false) {
                $fileContent = $content;
            } else {
                error_log("Failed to read file content for '$filename'");
                $filename = "Read error";
            }
        }

        return [
            'name' => $filename,
            'data' => $fileContent
        ];
    }
}