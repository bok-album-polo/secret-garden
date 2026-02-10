<?php

namespace App\controllers;

use App\core\Role;
use App\models\User;
use Controllers\Controller;

class UserController extends Controller
{
    private User $userModel;

    public function __construct()
    {
        AuthController::checkAuth();
        $this->userModel = new User();
    }

    public function index()
    {
        // Require at least ADMIN to views a user list
        $userRoles = $_SESSION['roles'] ?? [Role::USER];
        if (!Role::hasPermission($userRoles, Role::ADMIN)) {
            exit;
        }

        $search = $_GET['search'] ?? '';
        $users = $this->userModel->getAllUsers($search);

        // Attach roles to each user for display
        foreach ($users as &$user) {
            $user['roles'] = Role::getUserRoles($user['username']);
        }

        $this->render('user_management', [
            'pageTitle' => 'User Management',
            'users' => $users,
            'search' => $search,
            'currentUserRoles' => $userRoles
        ]);
    }

    public function resetPassword()
    {
        $userRoles = $_SESSION['roles'] ?? [Role::USER];
        if (!Role::hasPermission($userRoles, Role::ADMIN)) {
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

        $this->redirect('index.php?route=user_management');
    }

    public function updateRoles()
    {
        $userRoles = $_SESSION['roles'] ?? [Role::USER];
        // Only SUPERADMIN can grant/revoke roles
        if (!Role::hasPermission($userRoles, Role::SUPERADMIN)) {
            exit;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $username = $_POST['username'] ?? '';
            $roles = $_POST['roles'] ?? []; // Array of selected roles

            if ($username) {
                // Get current roles
                $currentRoles = Role::getUserRoles($username);
                
                // Determine roles to add
                $toAdd = array_diff($roles, $currentRoles);
                foreach ($toAdd as $role) {
                    if (Role::isValid($role)) {
                        $this->userModel->addRole($username, $role);
                    }
                }

                // Determine roles to remove
                // We don't remove all roles not in the list because the UI might not show all possible roles?
                // Assuming the UI shows all available roles (user, admin, superadmin).
                // So we should remove roles that are in currentRoles but not in the posted $roles.
                
                $allPossibleRoles = Role::getAll();
                // But wait, if the UI only sends checked roles, unchecked ones should be removed.
                
                $toRemove = array_diff($currentRoles, $roles);
                foreach ($toRemove as $role) {
                    $this->userModel->removeRole($username, $role);
                }
            }
        }

        $this->redirect('index.php?route=user_management');
    }
}
