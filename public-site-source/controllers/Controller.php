<?php

namespace App\Controllers;

class Controller
{
    protected function render(string $view, array $data = []): void
    {
        // Extract data for use in views
        if (!empty($data)) {
            extract($data, EXTR_SKIP);
        }

        $viewFile = __DIR__ . "/../views/{$view}.php";

        // Pass along both the view file and any dynamic form HTML
        if (file_exists($viewFile)) {
            // Use layout wrapper
            require __DIR__ . "/../views/layout/template.php";
        } elseif (!empty($formHtml)) {
            // No static view file, but we have dynamic form fields
            $title = $title ?? 'Secret Garden';
            require __DIR__ . "/../views/layout/template.php";
        } else {
            // Fallback if neither a view file nor formHtml is available
            error_log("View {$viewFile} not found and no dynamic form provided");
            echo "<p>No content available.</p>";
        }

        // Pad file size by random amount
        $randomBytes = random_int(1000, 5000);
        echo "<div style=\"display:none;\">";
        echo bin2hex(random_bytes($randomBytes));
        echo "</div>";
    }

    protected function renderFields(array $fields): string
    {
        $html = '';

        foreach ($fields as $field) {
            $required = $field['required'] ? 'required' : '';
            $maxlength = isset($field['maxlength']) ? 'maxlength="' . $field['maxlength'] . '"' : '';
            $helpText = $field['help_text'] ? '<small>' . htmlspecialchars($field['help_text']) . '</small>' : '';

            $html .= '<div class="form-field" style="margin-bottom:1em;">';

            switch ($field['html_type']) {
                case 'textarea':
                    $html .= sprintf(
                        '<label>%s</label><br><textarea name="%s" %s %s></textarea><br>%s',
                        htmlspecialchars($field['label']),
                        htmlspecialchars($field['name']),
                        $required,
                        $maxlength,
                        $helpText
                    );
                    break;

                case 'file':
                    $html .= sprintf(
                        '<label>%s</label><br><input type="file" name="%s" %s><br>%s',
                        htmlspecialchars($field['label']),
                        htmlspecialchars($field['name']),
                        $required,
                        $helpText
                    );
                    break;

                default: // text, email, etc.
                    $html .= sprintf(
                        '<label>%s</label><br><input type="%s" name="%s" %s %s><br>%s',
                        htmlspecialchars($field['label']),
                        htmlspecialchars($field['html_type']),
                        htmlspecialchars($field['name']),
                        $required,
                        $maxlength,
                        $helpText
                    );
                    break;
            }

            $html .= '</div>';
        }

        return $html;
    }

    protected function redirect(string $url): void
    {
        header("Location: {$url}");
        exit();
    }


}
