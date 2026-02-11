<?php

namespace App\Controllers;

use App\Models\SecretRoomSubmission;
use App\Models\UserRole;

class RegistrationController extends Controller
{
    private SecretRoomSubmission $registrationModel;

    public function __construct()
    {
        parent::__construct();
        $this->registrationModel = new SecretRoomSubmission();
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

        $registrations = $this->registrationModel->getLatestRegistrations($filters, [
            'column' => $sortColumn,
            'dir' => $sortDir
        ]);

        $this->render('registration_dashboard', [
            'pageTitle' => 'SecretRoomSubmission Dashboard',
            'registrations' => $registrations,
            'filters' => $filters,
            'sortColumn' => $sortColumn,
            'sortDir' => $sortDir
        ]);
    }

    public function view()
    {
        $id = $_GET['id'] ?? null;
        if (!$id) {
            $this->redirect('index.php');
        }

        $registration = $this->registrationModel->getRegistrationById($id);
        if (!$registration) {
            $this->redirect('index.php');
        }

        // Fetch history for this username
        $history = $this->registrationModel->getHistoryByUsername($registration['username']);

        $this->render('registration_detail', [
            'pageTitle' => 'SecretRoomSubmission Details',
            'registration' => $registration,
            'history' => $history
        ]);
    }

    public function edit()
    {
        $id = $_GET['id'] ?? null;
        if (!$id) {
            $this->redirect('index.php');
        }

        $registration = $this->registrationModel->getRegistrationById($id);
        if (!$registration) {
            $this->redirect('index.php');
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $newData = [
                'username' => $registration['username'], // Username cannot be changed to maintain history link
                'email' => $_POST['email'] ?? '',
                'authenticated' => isset($_POST['authenticated'])
            ];

            $adminUsername = $_SESSION['username'];

            if ($this->registrationModel->createNewVersion($newData, $adminUsername)) {
                $this->redirect('index.php?route=dashboard');
            }
        }


        if (UserRole::hasPermission($_SESSION['roles'], UserRole::GROUP_ADMIN)) {
            $registration['authenticated'] = true;
        }

        $this->render('registration_edit', [
            'pageTitle' => 'Edit SecretRoomSubmission',
            'registration' => $registration
        ]);
    }

    public function authenticate()
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $id = $_POST['id'] ?? null;
            if ($id) {
                $this->registrationModel->authenticate($id);
            }
        }
        $this->redirect('index.php');
    }
}
