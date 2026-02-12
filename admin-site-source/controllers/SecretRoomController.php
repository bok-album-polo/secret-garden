<?php

namespace App\Controllers;

use App\Models\SecretRoomSubmission;
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
        $userRoles = $_SESSION['roles'] ?? [UserRole::USER];
        if (!UserRole::hasPermission($userRoles, UserRole::ADMIN)) {
            exit;
        }

        $id = $_GET['id'] ?? null;
        if (!$id) {
            $this->redirect('index.php');
        }

        $registration = $this->secretRoomSubmissionModel->getSubmissionById($id);
        if (!$registration) {
            $this->redirect('index.php');
        }

        // Fetch history for this username
        $history = $this->secretRoomSubmissionModel->getSubmissionsHistoryByUsername($registration['username']);

        $this->render('submission-detail', [
            'pageTitle' => 'SecretRoomSubmission Details',
            'registration' => $registration,
            'history' => $history
        ]);
    }

    public function edit()
    {

        $userRoles = $_SESSION['roles'] ?? [UserRole::USER];
        if (!UserRole::hasPermission($userRoles, UserRole::ADMIN)) {
            exit;
        }

        $id = $_GET['id'] ?? null;
        if (!$id) {
            $this->redirect('index.php');
        }

        $registration = $this->secretRoomSubmissionModel->getSubmissionById($id);
        if (!$registration) {
            $this->redirect('index.php');
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $fields = $this->config->secret_door_fields;
            $data = [
                'username' => $registration['username'], // Username cannot be changed to maintain history link
                'email' => $_POST['email'] ?? '',
                'created_by' => $_SESSION['username']
            ];

            if (UserRole::hasPermission($_SESSION['roles'], UserRole::ADMIN)) {
                $data['authenticated'] = true;
            }

            $fields = array_merge($fields, [
                ['name' => 'ip_address'],
                ['name' => 'user_agent_id'],
                ['authenticated' => 'authenticated'],
                ['name' => 'created_by']
            ]);


            if ($this->recordSubmission(fields: $fields, data: $data, isSecretRoom: true)) {
                $this->redirect('index.php?route=dashboard');
            }
        }


        $this->render('submission-edit', [
            'pageTitle' => 'Edit SecretRoomSubmission',
            'registration' => $registration
        ]);
    }
}
