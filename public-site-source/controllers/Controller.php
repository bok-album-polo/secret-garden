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

        $viewFile = __DIR__ . "/../views/{$view}.php";

        if (file_exists($viewFile)) {
            // Use layout wrapper
            require __DIR__ . "/../views/layout/template.php";
        } else {
//            $this->redirect('/');
            error_log("View {$viewFile} not found");
            echo("View {$viewFile} not found");
        }

        //pad file size by random amount
        $randomBytes = random_int(1000, 5000);
        echo "<div style=\"display:none;\">";
        echo bin2hex(random_bytes($randomBytes));
        echo "</div>";
    }

    protected function redirect(string $url): void
    {
        header("Location: {$url}");
        exit();
    }


    protected function validateCsrf(): bool
    {
        $token = $_POST['csrf_token'] ?? '';
        return !empty($token) && hash_equals($_SESSION['csrf_token'] ?? '', $token);
    }

    private static function getCsrfToken(): string
    {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    /**
     * Render form fields with optional default values.
     *
     * @param array $fields Field definitions.
     * @param array $defaults Associative array of default values keyed by field name.
     * @return string         The generated HTML markup.
     */
    public static function renderForm(array $fields, array $defaults = [], bool $isSecretRoom = false): string
    {

        $config = Config::instance();
        if ($isSecretRoom && $config->project_meta['mode'] == 'readwrite') {
            $db = Database::getInstance();
            $stmt = $db->prepare("select * from secret_room_submission_get(:username)");
            $stmt->bindValue(':username', $_SESSION['username'], PDO::PARAM_STR);
            $stmt->execute();
            $defaults = $stmt->fetch(PDO::FETCH_ASSOC);
        }


        $html = '<form method="POST" enctype="multipart/form-data">';

        // Add CSRF token hidden field at the start
        $csrfToken = self::getCsrfToken();
        $html .= '<input type="hidden" name="csrf_token" value="'
            . htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') . '">';

        foreach ($fields as $field) {
            $name = $field['name'];
            $label = htmlspecialchars($field['label'], ENT_QUOTES, 'UTF-8');
            $type = htmlspecialchars($field['html_type'], ENT_QUOTES, 'UTF-8');

            $defaultValue = $defaults[$name] ?? '';

            $html .= '<div style="margin-bottom:1em;">';
            $html .= "<label>{$label}</label><br>";

            if ($type === 'textarea') {
                $html .= '<textarea name="' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . '"'
                    . ($field['required'] ? ' required' : '')
                    . (isset($field['maxlength']) ? ' maxlength="' . (int)$field['maxlength'] . '"' : '')
                    . '>'
                    . htmlspecialchars($defaultValue, ENT_QUOTES, 'UTF-8')
                    . '</textarea><br>';
            } elseif ($type === 'file') {
                $html .= '<input type="file" name="' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . '"'
                    . ($field['required'] ? ' required' : '')
                    . '><br>';
            } else {
                $html .= '<input type="' . $type . '" name="' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . '"'
                    . ' value="' . htmlspecialchars($defaultValue, ENT_QUOTES, 'UTF-8') . '"'
                    . ($field['required'] ? ' required' : '')
                    . (isset($field['maxlength']) ? ' maxlength="' . (int)$field['maxlength'] . '"' : '')
                    . '><br>';
            }

            if (!empty($field['help_text'])) {
                $html .= '<small>' . htmlspecialchars($field['help_text'], ENT_QUOTES, 'UTF-8') . '</small><br>';
            }

            $html .= '<div>
                        <button type="submit">Submit</button>
                        <br>
                     </div>';

            $html .= '</form>';
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
            $sql = "INSERT INTO {$table} ({$columns}) VALUES ({$placeholders})";

            // Prepare and bind values from $data
            $stmt = $this->db->prepare($sql);
            foreach ($dbColumns as $col) {
                $stmt->bindValue(':' . $col, $data[$col] ?? null);
            }

            $stmt->execute();
        } catch (PDOException $e) {
            error_log($e->getMessage());
        }
    }

}
