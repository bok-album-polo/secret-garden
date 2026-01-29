<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;
use InvalidArgumentException;
use PDO;

class SecretDoorController extends Controller
{
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

        $db = Database::getInstance();

        // ----------------------------
        // Honeypot check
        // ----------------------------
        $stmt = $db->prepare(
            "INSERT INTO ip_bans (network, reason)
             VALUES (:network, 'Honeypot Triggered')
             ON CONFLICT DO NOTHING"
        );
        $stmt->execute([
            ':network' => $_SERVER['REMOTE_ADDR'] . '/32'
        ]);

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
            $uploadedFile = $this->handleFileUploadToDb('file_upload');
        } catch (\RuntimeException $e) {
            error_log(
                'File upload failed from IP ' .
                $_SERVER['REMOTE_ADDR'] . ': ' . $e->getMessage()
            );
        }

        // ----------------------------
        // Persist submission
        // ----------------------------
        $stmt = $db->prepare("
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
    }


    private function handleFileUploadToDb(string $inputName, int $maxSize = 1048576): ?array
    {
        if (empty($_FILES[$inputName]['name'])) {
            return null;
        }

        $file = $_FILES[$inputName];
        $filename = basename($file['name']);
        $fileContent = '';

        if ($file['error'] !== UPLOAD_ERR_OK) {
            // If upload failed, return error code as filename and empty content
            $filename = "Upload error code {$file['error']}";
        } elseif ($file['size'] > $maxSize) {
            // If file too large, keep filename but empty content
            error_log("File '{$filename}' exceeds maximum size of {$maxSize} bytes");
        } else {
            // Read file content
            $content = file_get_contents($file['tmp_name']);
            if ($content !== false) {
                $fileContent = $content;
            } else {
                error_log("Failed to read file content for '{$filename}'");
                $filename = "Read error";
            }
        }

        return [
            'name' => $filename,
            'data' => $fileContent
        ];
    }

}
