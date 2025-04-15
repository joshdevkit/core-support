<?php

namespace Core;

class View
{
    /**
     * Render a view with the given data.
     *
     * @param string $view The name of the view file (without extension)
     * @param array $data The data to pass to the view
     * @return void
     */
    public static function render($view, $data = [])
    {
        // Initialize errors and old input from the session
        $errors = $_SESSION['errors'] ?? [];
        $old = $_SESSION['old_input'] ?? [];

        // Clear the errors from the session after rendering
        Validator::clearErrors();

        // Merge session data with the view data
        $data = array_merge($data, [
            'errors' => $errors,
            'old' => $old,
        ]);

        // Extract the data as variables for use in the view
        extract($data);

        // Define the view file path
        $viewPath = PathResolver::viewsPath($view);
        // Check if the view file exists and include it
        if (file_exists($viewPath)) {
            include $viewPath;
        } else {
            echo "View not found: {$view}";
        }
    }
}
