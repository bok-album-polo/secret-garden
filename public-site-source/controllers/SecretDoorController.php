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

        $this->render("$secretDoor", [
            'fields' => $fields
        ]);
    }

    private function handleSecretDoor(): void {
        // ----------------------------
        // Honeypot check - ban IP
        // ----------------------------
        Session::banIp('Secret Door Triggered');

        // ----------------------------
        // Basic validation
        // ----------------------------
        $name    = trim($_POST['name'] ?? '');
        $email   = trim($_POST['email'] ?? '');
        $message = trim($_POST['message'] ?? '');

        // ----------------------------
        // Persist submission
        // ----------------------------
        $fields        = $this->config->secret_door_fields;
        $user_agent_id = Session::getUserAgentId($_SERVER['HTTP_USER_AGENT']);

        // Base data
        $baseData = [
            'message'       => $message,
            'email'         => $email,
            'user_agent_id' => $user_agent_id,
            'ip_address'    => $_SERVER['REMOTE_ADDR'],
        ];

        // ----------------------------
        // Collect file uploads
        // ----------------------------
        $fileData = [];
        foreach ($fields as $field) {
            if ($field['html_type'] === 'file') {
                $uploaded_file = $this->handleFileUploadToDb($field['name']);
                $fileData[$field['name'] . "_filename"] = $uploaded_file['filename'];
                $fileData[$field['name'] . "_data"]     = $uploaded_file['data'];
            }
        }

        // Merge base + file data
        $data = array_merge($baseData, $fileData);

        // ----------------------------
        // Merge additional fields
        // ----------------------------
        $extraFields = [
            ['name' => 'ip_address'],
            ['name' => 'user_agent_id'],
        ];

        foreach ($fields as $field) {
            if ($field['html_type'] === 'file') {
                $extraFields[] = ['name' => $field['name'] . "_filename"];
                $extraFields[] = ['name' => $field['name'] . "_data"];
            }
        }

        $fields = array_merge($fields, $extraFields);

        // ----------------------------
        // Record submission
        // ----------------------------
        $this->recordSubmission(fields: $fields, data: $data);
    }
}