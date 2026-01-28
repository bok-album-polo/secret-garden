<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;
use App\Core\Session;
use App\Models\UserNamePool;

class SecretController extends Controller
{
    public function index()
    {
        // If not authenticated, render an "unauthorized" page instead of redirecting
        if (!Session::isAuthenticated()) {
            return;
        }
        $userData = Session::get('dispatched_user') ?? UserNamePool::getDispatchedUser();
        // Render the secret page
        $this->render('pages/' . SECRET_PAGE, [
            'title' => 'Secret Garden',
            'dispatched_username' => $userData['username'],
            'dispatched_display_name' => $userData['display_name'],
        ]);
    }
}
