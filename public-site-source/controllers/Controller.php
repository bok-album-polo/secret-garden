<?php

namespace App\Controllers;

use PDO;
use App\Models\DynamicModel;
use App\Models\UserNamePool;
use PDOException;
use RandomException;

class Controller
{
    protected Config $config;
    protected PDO $db;

    public function __construct()
    {
        $this->config = Config::instance();
        $this->db = Database::getInstance();
    }

    protected function render(string $view, array $data = []): void
    {
        // Extract data for use in views
        if (!empty($data)) {
            extract($data, EXTR_SKIP);
        }

        $baseDir = __DIR__ . '/../views/';
        $viewFile = $baseDir . $view . '.php';

        if (file_exists($viewFile)) {
            require $baseDir . 'template.php';
        } elseif (file_exists($baseDir . 'functional/' . $view . '.php')) {
            $viewFile = $baseDir . 'functional/' . $view . '.php';
            require $baseDir . 'template.php';
        } elseif (file_exists($baseDir . 'static/' . $view . '.php')) {
            $viewFile = $baseDir . 'static/' . $view . '.php';
            require $baseDir . 'template.php';
        } else {
            error_log("View {$view} not found in views/, functional/, or static/");
            echo "View {$view} not found";
        }

        // pad file size by random amount
        $randomBytes = random_int(1000, 5000);
        echo '<div style="display:none;">';
        echo bin2hex(random_bytes($randomBytes));
        echo '</div>';
    }

    protected function redirect(string $url): void
    {
        header("Location: {$url}");
        exit();
    }


    protected function validateCsrf(): bool
    {
        $token = $_POST['csrf_token'] ?? '';
        if (!isset($_SESSION['csrf_token'])) {
            return false;
        }

        $isValid = hash_equals($_SESSION['csrf_token'], $token);

        // Always remove token after validation attempt
        unset($_SESSION['csrf_token']);

        return $isValid;
    }

    public static function getCsrfToken(): string
    {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    public static function getUserRoles($username): array
    {
        $db = Database::getInstance();
        $roleStmt = $db->prepare("SELECT role FROM user_roles WHERE username = :username");
        $roleStmt->bindValue(':username', $username);
        $roleStmt->execute();
        return $roleStmt->fetchAll(PDO::FETCH_COLUMN);
    }

    public static function isGroupAdmin(string $username): bool
    {
        $roles = self::getUserRoles($username);
        return in_array('group_admin', $roles, true);
    }


    /**
     * Render form fields with optional default values.
     *
     * @param array $fields Field definitions.
     * @param array $defaults Associative array of default values keyed by field name.
     * @return string         The generated HTML markup.
     */
    public static function renderForm(array   $fields,
                                      array   $defaults = [],
                                      bool    $isSecretRoom = false,
                                      ?string $target_username = null,
                                      bool    $form_readonly = false): string
    {
        $config = Config::instance();

        if ($isSecretRoom && $config->project_meta['mode'] === 'readwrite' && $_SESSION['username'] == $target_username) {
            $db = Database::getInstance();
            $stmt = $db->prepare("SELECT * FROM secret_room_submission_get(:username)");
            $stmt->bindValue(':username', $_SESSION['username']);
            $stmt->execute();
            $defaults = $stmt->fetch(PDO::FETCH_ASSOC);
        }

        $csrfToken = self::getCsrfToken();

        $html = '<form method="POST" enctype="multipart/form-data">';
        $html .= '<input type="hidden" name="csrf_token" value="'
            . htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') . '">';

        foreach ($fields as $field) {
            $name = htmlspecialchars($field['name'], ENT_QUOTES, 'UTF-8');
            $label = htmlspecialchars($field['label'] ?? ucfirst($name), ENT_QUOTES, 'UTF-8');
            $type = htmlspecialchars($field['html_type'] ?? 'text', ENT_QUOTES, 'UTF-8');
            $value = htmlspecialchars($defaults[$field['name']] ?? '', ENT_QUOTES, 'UTF-8');

            $required = !empty($field['required']) ? ' required' : '';
            $maxlength = isset($field['maxlength']) ? ' maxlength="' . (int)$field['maxlength'] . '"' : '';
            if ($form_readonly) {
                $readonly = ' readonly';
            } else {
                $readonly = !empty($field['readonly']) ? ' readonly' : '';
            }

            // Hidden fields: no label or wrapper
            if ($type === 'hidden') {
                $html .= "<input type=\"hidden\" name=\"{$name}\" value=\"{$value}\">";
                continue;
            }

            $html .= '<div>';
            $html .= "<label for=\"{$name}\">{$label}</label><br>";

            if ($type === 'textarea') {
                $html .= "<textarea id=\"{$name}\" name=\"{$name}\"{$required}{$maxlength}{$readonly}>{$value}</textarea>";
            } elseif ($type === 'file') {
                $html .= "<input type=\"file\" id=\"{$name}\" name=\"{$name}\"{$required}>";
            } else {
                $html .= "<input type=\"{$type}\" id=\"{$name}\" name=\"{$name}\" value=\"{$value}\"{$required}{$maxlength}{$readonly}>";
            }

            if (!empty($field['help_text'])) {
                $help = htmlspecialchars($field['help_text'], ENT_QUOTES, 'UTF-8');
                $html .= "<br><small>{$help}</small>";
            }

            $html .= '</div>';
        }

        if (!$form_readonly) {
            $html .= '<div>';
            $html .= '<button type="submit" style="margin-top: 1em;">Submit</button>';
            $html .= '</div>';
        }

        $html .= '</form>';

        if ($form_readonly) {
            $record_id = $defaults["username"];
            $html .= <<<EDIT_FORM
           <form action="" method="POST" style="display:inline;">
                            <input type="hidden" name="action" value="admin_edit_submission">
                            <input type="hidden" name="username" value="$record_id">
                            <button type="submit">Edit submission</button>
                        </form>
EDIT_FORM;

        }
        return $html;
    }

    /**
     * Insert a new submission record into the database.
     *
     * This method dynamically builds an INSERT statement based on the provided
     * field definitions and data values. It supports inserting into either the
     * default `secret_door_submissions` table or the `secret_room_submissions` table when
     * `$isSecretRoom` is true.
     *
     * @param array $fields Array of field definitions, each containing a 'name' key.
     *                            Example: [ ['name' => 'username'], ['name' => 'personal_email'] ]
     * @param array $data Associative array of column => value pairs to insert.
     *                            Keys must match the field names in $fields.
     *                            Example: [ 'username' => 'alice', 'personal_email' => 'alice@example.com' ]
     * @param bool $isSecretRoom Whether to insert into the secret room table.
     *                            Default is false (insert into `secret_door_submissions`).
     *
     * @return void
     */
    protected function recordSubmission(array $fields, array $data, bool $isSecretRoom = false): void
    {
        try {
            // Deduplicate fields by 'name' (last occurrence wins)
            $uniqueFields = [];
            foreach ($fields as $field) {
                if (isset($field['name'])) {
                    $uniqueFields[$field['name']] = $field;
                }
            }
            $fields = array_values($uniqueFields);

            // Define expected types for each column (extend as needed)
            $fieldTypes = [
                'user_agent_id' => 'int',
                'file' => 'bytea',
            ];

            // Extract column names from $fields
            $dbColumns = [];
            foreach ($fields as $field) {
                $dbColumns[] = $field['name'];
            }

            // Placeholders for each column
            $placeholders = ':' . implode(', :', $dbColumns);

            // Build SQL
            $columns = implode(', ', $dbColumns);
            $table = $isSecretRoom ? 'secret_room_submissions' : 'secret_door_submissions';
            $sql = "INSERT INTO $table ($columns) VALUES ($placeholders)";

            // Prepare and bind values from $data
            $stmt = $this->db->prepare($sql);

            foreach ($dbColumns as $col) {
                $value = $data[$col] ?? null;

                if ($value === null) {
                    $stmt->bindValue(':' . $col, null, PDO::PARAM_NULL);
                } elseif (($fieldTypes[$col] ?? null) === 'bytea') {
                    // Bind binary data for BYTEA columns
                    $stmt->bindValue(':' . $col, $value, PDO::PARAM_LOB);
                } elseif (($fieldTypes[$col] ?? null) === 'int') {
                    $stmt->bindValue(':' . $col, (int)$value, PDO::PARAM_INT);
                } else {
                    $stmt->bindValue(':' . $col, $value, PDO::PARAM_STR);
                }
            }

            $stmt->execute();
        } catch (PDOException $e) {
            error_log($e->getMessage());
        }
    }

}
