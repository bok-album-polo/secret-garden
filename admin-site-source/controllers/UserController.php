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

    public function index()
    {
        // Require at least ADMIN to views a user list
        $userRoles = $_SESSION['roles'] ?? [UserRole::USER];
        if (!UserRole::hasPermission($userRoles, UserRole::ADMIN)) {
            exit;
        }

        $search = $_GET['search'] ?? '';
        $users = $this->userModel->getAllUsers($search);

        // Attach roles to each user for display
        foreach ($users as &$user) {
            $user['roles'] = UserRole::getUserRoles($user['username']);
        }

        $this->render('users-management', [
            'pageTitle' => 'User Management',
            'users' => $users,
            'search' => $search,
            'currentUserRoles' => $userRoles
        ]);
    }

    public function resetPassword()
    {
        $userRoles = $_SESSION['roles'] ?? [UserRole::USER];
        if (!UserRole::hasPermission($userRoles, UserRole::ADMIN)) {
            exit;
        }

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

    public function updateRoles()
    {
        $userRoles = $_SESSION['roles'] ?? [UserRole::USER];
        // Only SUPERADMIN can grant/revoke roles
        if (!UserRole::hasPermission($userRoles, UserRole::SUPERADMIN)) {
            exit;
        }

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

                // Determine roles to remove
                // We don't remove all roles not in the list because the UI might not show all possible roles?
                // Assuming the UI shows all available roles (user, admin, superadmin).
                // So we should remove roles that are in currentRoles but not in the posted $roles.

                $allPossibleRoles = UserRole::getAll();
                // But wait, if the UI only sends checked roles, unchecked ones should be removed.

                $toRemove = array_diff($currentRoles, $roles);
                foreach ($toRemove as $role) {
                    $this->userModel->removeRole($username, $role);
                }
            }
        }

        $this->redirect('index.php?route=users-management');
    }

    public function activateUser()
    {
        $username = $_POST['username'] ?? '';
        $this->userModel->activate($username);
        $this->redirect('index.php?route=users-management');
    }
}
