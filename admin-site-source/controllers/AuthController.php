<?php

namespace App\Controllers;

use App\Models\User;
use App\Models\UserRole;

class AuthController extends Controller
{
    private User $userModel;

    public function __construct()
    {
        parent::__construct();
        $this->userModel = new User();
    }

    public function showLogin()
    {
        if (Session::isLoggedIn()) {
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


            $_SESSION['user_logged_in'] = true;
            $_SESSION['username'] = $user['username'];
            $_SESSION['roles'] = UserRole::getUserRoles($user['username']);;


            $this->redirect('index.php');
        }

        $this->redirect('index.php?route=login&error=1');
    }

    public function logout()
    {
        Session::logout();
        $this->redirect('index.php?route=login');
    }

    public static function checkAuth()
    {
        if ($_SESSION['user_logged_in'] !== true) {
            header('Location: index.php?route=login');
            exit;
        }
    }
}
