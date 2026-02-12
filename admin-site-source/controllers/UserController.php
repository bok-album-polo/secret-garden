<?php

namespace App\Controllers;

use App\Models\User;
use App\Models\UserRole;

class UserController extends Controller
{
    private User $userModel;

    public function __construct()
    {
        parent::__construct();
        $this->userModel = new User();
    }

    public function index(): void
    {
        // Require at least ADMIN to views the users list
        $userRoles = $_SESSION['roles'] ?? [UserRole::USER];
        $this->requireRole(UserRole::ADMIN);
        $filters = [
            'username' => $_GET['username'] ?? '',
            'domain' => $_GET['domain'] ?? '',
            'pk_sequence' => $_GET['pk_sequence'] ?? '',
            'authenticated' => $_GET['authenticated'] ?? '',
            'activated' => $_GET['activated'] ?? 'yes',
            'date_from' => $_GET['date_from'] ?? '',
            'date_to' => $_GET['date_to'] ?? '',
        ];

        $sortColumn = $_GET['sort'] ?? 'activated_at';
        $sortDir = strtoupper($_GET['dir'] ?? 'DESC');

        $users = $this->userModel->getAllUsers(
            $filters,
            [
                'column' => $sortColumn,
                'dir' => $sortDir
            ]
        );

        // Attach roles to each user for display
        foreach ($users as &$user) {
            $user['roles'] = UserRole::getUserRoles($user['username']);
        }

        $this->render('users-management', [
            'pageTitle' => 'User Management',
            'users' => $users,
            'currentUserRoles' => $userRoles,
            'filters' => $filters,
            'sortColumn' => $sortColumn,
            'sortDir' => $sortDir
        ]);

    }

    public function resetPassword(): void
    {
        $this->requireRole(UserRole::ADMIN);

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $username = $_POST['username'] ?? '';
            $password = $_POST['password'] ?? '';

            if ($username && $password) {
                $hash = password_hash($password, PASSWORD_ARGON2ID);
                $this->userModel->updatePassword($username, $hash);
            }
        }


        $this->redirect('index.php?route=users-management');
    }

    public function updateRoles(): void
    {
        // Only SUPERADMIN can grant/revoke roles
        $this->requireRole(UserRole::SUPERADMIN);
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $username = $_POST['username'] ?? '';
            $roles = $_POST['roles'] ?? []; // Array of selected roles

            if ($username) {
                // Get current roles
                $currentRoles = UserRole::getUserRoles($username);

                // Determine roles to add
                $toAdd = array_diff($roles, $currentRoles);
                foreach ($toAdd as $role) {
                    if (UserRole::isValid($role)) {
                        $this->userModel->addRole($username, $role);
                    }
                }

                $toRemove = array_diff($currentRoles, $roles);
                foreach ($toRemove as $role) {
                    $this->userModel->removeRole($username, $role);
                }
            }
        }

        $this->redirect('index.php?route=users-management');
    }
}
