<?php

namespace App\Models;

use App\Controllers\Database;

class UserNamePool
{
    /**
     * Fetch a dispatched username + display name from DB
     */
    public static function getDispatchedUser(): array
    {
        $db = Database::getInstance();
        $statement = $db->query("SELECT * FROM dispatch_one_username()");
        $result = $statement->fetch();

        return [
            'username' => $result['r_username'] ?? '',
            'display_name' => $result['r_displayname'] ?? ''
        ];
    }
}
