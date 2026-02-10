<?php

namespace App\controllers;

use App\Core\Role;
use App\Models\User;
use Controllers\Controller;

class AuthController extends Controller
{
    private $userModel;

    public function __construct()
    {
        $this->userModel = new User();
    }

    public function showLogin()
    {
        if (isset($_SESSION['username'])) {
            $this->redirect('index.php');
        }
        $this->render('login', ['pageTitle' => 'Login']);
    }

    public function login()
    {
        $username = $_POST['username'] ?? '';
        $pass = $_POST['password'] ?? '';

        $user = $this->userModel->findByUsername($username);

        if ($user && password_verify($pass, $user['password'])) {

            if (!$user['authenticated']) {
                $this->redirect('index.php?route=login&error=2');
            }

            session_regenerate_id(true);
            $_SESSION['username'] = $user['username'];
            
            // Fetch roles from user_roles table
            $roles = Role::getUserRoles($user['username']);
            
            // Store roles as an array in session
            $_SESSION['roles'] = $roles;
            
            // Store highest role for convenience/backward compatibility
            $_SESSION['role'] = Role::getHighestRole($roles);

            $this->redirect('index.php');
        }

        $this->redirect('index.php?route=login&error=1');
    }

    public function logout()
    {
        $_SESSION = [];
        session_destroy();
        $this->redirect('index.php?route=login');
    }

    public static function checkAuth()
    {
        if (!isset($_SESSION['username'])) {
            header('Location: index.php?route=login');
            exit;
        }
    }
}
