<?php

namespace App\Models;

use App\Core\Database;
use PDO;

class UserRole
{
    public const string USER = 'user';
    public const string ADMIN = 'admin';
    public const string GROUP_ADMIN = 'group_admin'; //Used on the public site
    public const string SUPERADMIN = 'superadmin';

    public static function getAll()
    {
        return [
            self::ADMIN,
            self::GROUP_ADMIN,
            self::SUPERADMIN,
        ];
    }

    public static function isValid($role)
    {
        return in_array($role, self::getAll());
    }

    public static function getHighestRole($userRoles)
    {
        if (!is_array($userRoles)) {
            $userRoles = [$userRoles];
        }

        if (empty($userRoles)) {
            return self::USER;
        }

        $hierarchy = [
            self::USER => 1,
            self::ADMIN => 2,
            self::SUPERADMIN => 3,
        ];

        $highestRole = self::USER;
        $maxLevel = $hierarchy[self::USER];

        foreach ($userRoles as $role) {
            $level = $hierarchy[$role] ?? 0;
            if ($level > $maxLevel) {
                $maxLevel = $level;
                $highestRole = $role;
            }
        }

        return $highestRole;
    }

    public static function hasPermission($userRoles, $requiredRole)
    {
        $highestRole = self::getHighestRole($userRoles);

        $hierarchy = [
            self::USER => 1,
            self::ADMIN => 2,
            self::SUPERADMIN => 3,
        ];

        $userLevel = $hierarchy[$highestRole] ?? 0;
        $requiredLevel = $hierarchy[$requiredRole] ?? 999;

        return $userLevel >= $requiredLevel;
    }

    public static function getUserRoles($username)
    {
        $db = Database::getInstance();
        $stmt = $db->prepare("SELECT role FROM user_roles WHERE username = :username");
        $stmt->execute(['username' => $username]);
        $results = $stmt->fetchAll(PDO::FETCH_COLUMN);

        // If no roles found, default to USER
        if (empty($results)) {
            return [self::USER];
        }

        return $results;
    }
}
