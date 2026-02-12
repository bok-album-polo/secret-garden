<?php

namespace App\Controllers;

use App\Models\User;
use App\Models\UserRole;
use PDO;

class Controller
{
    protected Config $config;
    protected PDO $db;

    public function __construct()
    {
        self::checkAuth();
        $this->config = Config::instance();
        $this->db = Database::getInstance();
    }

    public static function checkAuth(): void
    {
        $route = $_GET['route'] ?? '';
        if (!Session::isLoggedIn() && $route != 'login') {
            header('Location: index.php?route=login');
            exit;
        }
    }

    protected function requireRole(string $role): void
    {
        $userRoles = $_SESSION['roles'] ?? [UserRole::USER];
        if (!UserRole::hasPermission($userRoles, $role)) {
            $this->renderError(403, "You need $role permission to access this page.");
            exit;
        }
    }

    private function renderError(int $statusCode, string $message): void
    {
        http_response_code($statusCode);
        // If not, you can fallback to a simple HTML output:
        echo "<h1>Error $statusCode</h1><p>" . htmlspecialchars($message) . "</p>";
        exit;
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


    protected function redirect($url)
    {
        header("Location: {$url}");
        exit;
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

    public static function renderForm(
        array   $fields,
        array   $defaults = [],
        bool    $form_readonly = false,
        ?string $actionUrl = null   // new parameter
    ): string
    {
        $csrfToken = self::getCsrfToken();
        $formAction = $actionUrl ? htmlspecialchars($actionUrl, ENT_QUOTES, 'UTF-8') : '';

        $html = '<form method="POST" enctype="multipart/form-data" class="needs-validation" novalidate';
        if ($formAction) {
            $html .= ' action="' . $formAction . '"';
        }
        $html .= '>';

        $html .= '<input type="hidden" name="csrf_token" value="'
            . htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') . '">';

        foreach ($fields as $field) {
            $name = htmlspecialchars($field['name'], ENT_QUOTES, 'UTF-8');
            $label = htmlspecialchars($field['label'] ?? ucfirst($name), ENT_QUOTES, 'UTF-8');
            $type = htmlspecialchars($field['html_type'] ?? 'text', ENT_QUOTES, 'UTF-8');
            $value = $defaults[$field['name']] ?? '';
            $required = !empty($field['required']) ? ' required' : '';
            $maxlength = isset($field['maxlength']) ? ' maxlength="' . (int)$field['maxlength'] . '"' : '';
            $readonly = $form_readonly ? ' readonly' : (!empty($field['readonly']) ? ' readonly' : '');


            // Hidden fields: no label or wrapper
            if ($type === 'hidden') {
                $html .= "<input type=\"hidden\" name=\"{$name}\" value=\"" . htmlspecialchars($value, ENT_QUOTES, 'UTF-8') . "\">";
                continue;
            }

            $html .= '<div class="mb-3">';
            $html .= "<label for=\"{$name}\" class=\"form-label\">{$label}</label>";

            switch ($type) {
                case 'textarea':
                    $html .= "<textarea class=\"form-control\" id=\"{$name}\" name=\"{$name}\"{$required}{$maxlength}{$readonly}>"
                        . htmlspecialchars($value, ENT_QUOTES, 'UTF-8') . "</textarea>";
                    break;

                case 'file':
                    $html .= "<input type=\"file\" class=\"form-control\" id=\"{$name}\" name=\"{$name}\"{$required}>";
                    break;

                case 'select': // dropdown
                    $html .= "<select class=\"form-select\" id=\"{$name}\" name=\"{$name}\"{$required}>";
                    foreach ($field['options'] ?? [] as $optValue => $optLabel) {
                        $optValueEsc = htmlspecialchars($optValue, ENT_QUOTES, 'UTF-8');
                        $optLabelEsc = htmlspecialchars($optLabel, ENT_QUOTES, 'UTF-8');
                        $selected = ($value == $optValue) ? ' selected' : '';
                        $html .= "<option value=\"{$optValueEsc}\"{$selected}>{$optLabelEsc}</option>";
                    }
                    $html .= "</select>";
                    break;

                case 'radio':
                    foreach ($field['options'] ?? [] as $optValue => $optLabel) {
                        $optValueEsc = htmlspecialchars($optValue, ENT_QUOTES, 'UTF-8');
                        $optLabelEsc = htmlspecialchars($optLabel, ENT_QUOTES, 'UTF-8');
                        $checked = ($value == $optValue) ? ' checked' : '';
                        $html .= "<div class=\"form-check\">
                                <input class=\"form-check-input\" type=\"radio\" name=\"{$name}\" id=\"{$name}_{$optValueEsc}\" value=\"{$optValueEsc}\"{$checked}{$required}>
                                <label class=\"form-check-label\" for=\"{$name}_{$optValueEsc}\">{$optLabelEsc}</label>
                              </div>";
                    }
                    break;

                case 'checkbox':
                    foreach ($field['options'] ?? [] as $optValue => $optLabel) {
                        $optValueEsc = htmlspecialchars($optValue, ENT_QUOTES, 'UTF-8');
                        $optLabelEsc = htmlspecialchars($optLabel, ENT_QUOTES, 'UTF-8');
                        $checked = (is_array($value) && in_array($optValue, $value)) ? ' checked' : '';
                        $html .= "<div class=\"form-check\">
                                <input class=\"form-check-input\" type=\"checkbox\" name=\"{$name}[]\" id=\"{$name}_{$optValueEsc}\" value=\"{$optValueEsc}\"{$checked}{$required}>
                                <label class=\"form-check-label\" for=\"{$name}_{$optValueEsc}\">{$optLabelEsc}</label>
                              </div>";
                    }
                    break;

                default: // text, number, email, etc.
                    $html .= "<input type=\"{$type}\" class=\"form-control\" id=\"{$name}\" name=\"{$name}\" value=\""
                        . htmlspecialchars($value, ENT_QUOTES, 'UTF-8') . "\"{$required}{$maxlength}{$readonly}>";
            }

            if (!empty($field['help_text'])) {
                $help = htmlspecialchars($field['help_text'], ENT_QUOTES, 'UTF-8');
                $html .= "<div class=\"form-text\">{$help}</div>";
            }

            $html .= '</div>';
        }
        if (!$form_readonly) {
            $html .= '<div>';
            $html .= '<button type="submit" class="btn btn-primary mt-2">Submit</button>';
            $html .= '</div>';
        }
        $html .= '</form>';
        if ($form_readonly) {
            $record_id = $defaults["id"] ?? 0;
            $html .= <<<EDIT_FORM

            <a href="index.php?route=submission-edit&id=$record_id" class="btn btn-warning mt-2">Edit</a>
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
     * @return bool
     */
    protected function recordSubmission(array $fields, array $data, bool $isSecretRoom = false): bool
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
            $dbColumns = array_column($fields, 'name');

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
                    $stmt->bindValue(':' . $col, $value, PDO::PARAM_LOB);
                } elseif (($fieldTypes[$col] ?? null) === 'int') {
                    $stmt->bindValue(':' . $col, (int)$value, PDO::PARAM_INT);
                } else {
                    $stmt->bindValue(':' . $col, $value, PDO::PARAM_STR);
                }
            }

            return $stmt->execute();
        } catch (\PDOException $e) {
            error_log($e->getMessage());
        }
        return false;
    }

}
