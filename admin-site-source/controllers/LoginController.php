<?php

namespace App\Controllers;

use App\Models\User;
use App\Models\UserRole;

class LoginController extends Controller
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
                $_SESSION['error'] = 2;
                $this->redirect($_SERVER['REQUEST_URI']);
            }


            $_SESSION['user_logged_in'] = true;
            $_SESSION['username'] = $user['username'];
            $_SESSION['roles'] = UserRole::getUserRoles($user['username']);;


            $this->redirect($_SERVER['REQUEST_URI']);
        }

        $_SESSION['error'] = 1;
        $this->redirect('index.php?route=login');
    }

    public function logout()
    {
        Session::logout();
        $this->redirect('index.php?route=login');
    }
}
