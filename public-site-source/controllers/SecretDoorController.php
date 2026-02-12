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

    private function handleSecretDoor(): void
    {
        Session::banIp('Secret Door Triggered');

        $email = trim($_POST['email'] ?? '');
        $message = trim($_POST['message'] ?? '');

        $fields = $this->config->secret_door_fields;
        $user_agent_id = Session::getUserAgentId($_SERVER['HTTP_USER_AGENT']);

        $baseData = [
            'message' => $message,
            'email' => $email,
            'user_agent_id' => $user_agent_id,
            'ip_address' => $_SERVER['REMOTE_ADDR'],
        ];

        // Reusable file upload handler
        $uploads = $this->processFileUploads($fields);

        // Merge base + file data
        $data = array_merge($baseData, $uploads['data']);

        // Merge extra fields
        $fields = array_merge(
            $fields,
            [
                ['name' => 'ip_address'],
                ['name' => 'user_agent_id']
            ],
            $uploads['fields']
        );

        $this->recordSubmission(fields: $fields, data: $data);
    }
}