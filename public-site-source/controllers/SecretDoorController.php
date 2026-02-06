<?php

namespace App\Controllers;

use PDO;
use PDOException;
use RuntimeException;

class SecretDoorController extends Controller
{

    public function index(): void
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->handleSecretDoor();
            $this->redirect($_SERVER['REQUEST_URI']);
        }

        $secretDoor = $this->config->routing_secrets['secret_door'];
        $fields = $this->config->secret_door_fields;

        $this->render("pages/$secretDoor", [
            'fields' => $fields
        ]);
    }

    private function handleSecretDoor(): void
    {
        // ----------------------------
        // Honeypot check - ban IP
        // ----------------------------
        Session::banIp('Secret Door Triggered');

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
        $fields = $this->config->secret_door_fields;
        $user_agent_id = Session::getUserAgentId($_SERVER['HTTP_USER_AGENT']);
        $data = [
            'message' => $message,
            'name' => $uploadedFile['name'] ?? null,
            'file' => $uploadedFile['data'] ?? null,
            'email' => $email,
            'user_agent_id' => $user_agent_id,
            'ip_address' => $_SERVER['REMOTE_ADDR'],
        ];


        $fields = array_merge($fields, [
            ['name' => 'ip_address'],
            ['name' => 'user_agent_id'],
        ]);

        $this->recordSubmission(fields: $fields, data: $data);
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