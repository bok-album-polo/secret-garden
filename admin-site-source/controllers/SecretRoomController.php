<?php

namespace App\Controllers;

use App\Models\SecretRoomSubmission;
use App\Models\UserAgent;
use App\Models\UserRole;

class SecretRoomController extends Controller
{
    private SecretRoomSubmission $secretRoomSubmissionModel;

    public function __construct()
    {
        parent::__construct();
        $this->secretRoomSubmissionModel = new SecretRoomSubmission();
    }

    public function index()
    {
        $filters = [
            'username' => $_GET['username'] ?? '',
            'domain' => $_GET['domain'] ?? '',
            'pk_sequence' => $_GET['pk_sequence'] ?? '',
            'authenticated' => $_GET['authenticated'] ?? '',
            'date_from' => $_GET['date_from'] ?? '',
            'date_to' => $_GET['date_to'] ?? ''
        ];

        $sortColumn = $_GET['sort'] ?? 'created_at';
        $sortDir = ($_GET['dir'] ?? 'desc') === 'asc' ? 'ASC' : 'DESC';

        $registrations = $this->secretRoomSubmissionModel->getLatestSubmissions($filters, [
            'column' => $sortColumn,
            'dir' => $sortDir
        ]);

        $this->render('submissions-dashboard', [
            'pageTitle' => 'SecretRoomSubmission Dashboard',
            'registrations' => $registrations,
            'filters' => $filters,
            'sortColumn' => $sortColumn,
            'sortDir' => $sortDir
        ]);
    }

    public function view()
    {
        $this->requireRole(UserRole::SITE_ADMIN);

        $id = $_GET['id'] ?? null;
        if (!$id) {
            $this->redirect('index.php');
        }

        $submission = $this->secretRoomSubmissionModel->getSubmissionById($id);
        if (!$submission) {
            $this->redirect('index.php');
        }

        $fields = $this->config->secret_room_fields;
        // Fetch history for this username
        $history = $this->secretRoomSubmissionModel->getSubmissionsHistoryByUsername($submission['username']);

        $this->render('submission-edit', [
            'pageTitle' => 'View SecretRoomSubmission',
            'fields' => $fields,
            'submission' => $submission,
            'form_readonly' => true
        ]);

//        $this->render('submission-detail', [
//            'pageTitle' => 'SecretRoomSubmission Details',
//            'submission' => $submission,
//            'history' => $history
//        ]);
    }

    public function edit()
    {
        $this->requireRole(UserRole::SITE_ADMIN);

        $submission = [];
        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
            $id = (int)($_GET['id'] ?? -1);

            $submission = $this->secretRoomSubmissionModel->getSubmissionById($id);
            if (!$submission) {
                $this->redirect('index.php');
            }
        }
        $fields = $this->config->secret_room_fields;

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $username = trim($_POST['username'] ?? '');
            $email = trim($_POST['primary_email'] ?? '');
            $userAgentId = UserAgent::getUserAgentId($_SERVER['HTTP_USER_AGENT'] ?? '');
            $data = [
                'username' => $username,
                'created_by' => $_SESSION['username'],
                'primary_email' => $email,
                'user_agent_id' => $userAgentId,
                'ip_address' => $_SERVER['REMOTE_ADDR'],
                'authenticated' => true,
            ];

            $extraFields = [
                ['name' => 'ip_address'],
                ['name' => 'username'],
                ['name' => 'user_agent_id'],
                ['name' => 'authenticated'],
                ['name' => 'created_by']
            ];
            $submission_fields = array_merge($fields, $extraFields);

            if ($this->recordSubmission(fields: $submission_fields, data: $data, isSecretRoom: true)) {
                $this->redirect('index.php?route=dashboard');
            }
        }


        $fields = array_merge($fields, [
            ['name' => 'username', 'html_type' => 'hidden', 'readonly' => true,],
        ]);

        $this->render('submission-edit', [
            'pageTitle' => 'Edit SecretRoomSubmission',
            'fields' => $fields,
            'submission' => $submission,
            'form_readonly' => false
        ]);
    }
}
