<?php

namespace Core;

class Validator
{
    protected $errors = [];
    protected $data = [];

    // Constructor to initialize data and rules
    public function __construct($data)
    {
        $this->data = $data;
        // Only load errors from session if they exist and are for this request
        $this->errors = $_SESSION['errors'] ?? [];
    }

    // Sanitize a single field
    public function sanitize($field, $type = 'string')
    {
        if (isset($this->data[$field])) {
            $value = trim($this->data[$field]);
            switch ($type) {
                case 'email':
                    $value = filter_var($value, FILTER_SANITIZE_EMAIL);
                    $this->data[$field] = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
                    break;

                case 'string':
                default:
                    $this->data[$field] = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
                    break;
            }
        }
    }

    public function sanitizeAll()
    {
        foreach ($this->data as $key => $value) {
            $this->data[$key] = htmlspecialchars(trim($value), ENT_QUOTES, 'UTF-8');
        }
    }



    // Validate if a field is required
    public function validateRequired($field)
    {
        if (empty($this->data[$field])) {
            // Directly store the error message instead of an array of messages
            $this->errors[$field][] = ucfirst($field) . ' is required.';
        }
    }

    // Validate email field
    public function validateEmail($field)
    {
        if (!empty($this->data[$field]) && !filter_var($this->data[$field], FILTER_VALIDATE_EMAIL)) {
            if (!in_array('Invalid email format.', $this->errors[$field] ?? [])) {
                $this->errors[$field][] = 'Invalid email format.';
            }
        }
    }

    public function validatePasswordLength($field, $minLength = 6)
    {
        if (strlen($this->data[$field]) < $minLength) {
            if (!in_array('Password must be at least ' . $minLength . ' characters.', $this->errors[$field] ?? [])) {
                $this->errors[$field][] = 'Password must be at least ' . $minLength . ' characters.';
            }
        }
    }

    public function validate($rules)
    {
        foreach ($rules as $field => $fieldRules) {
            if (isset($fieldRules['sanitize'])) {
                $this->sanitize($field, $fieldRules['sanitize']);
            }

            foreach ($fieldRules['validate'] as $rule => $ruleValue) {
                if ($rule === 'required' && $ruleValue) {
                    $this->validateRequired($field);
                } elseif ($rule === 'email' && $ruleValue) {
                    $this->validateEmail($field);
                } elseif ($rule === 'password') {
                    $this->validatePasswordLength($field, $ruleValue);
                }
            }
        }

        // Store errors in session with a flash flag
        if (!empty($this->errors)) {
            $_SESSION['errors'] = $this->errors;
            $_SESSION['errors_flash'] = true; // Mark these errors as flash data
        }

        return $this->errors;
    }

    public function input()
    {
        return $this->data;
    }

    /**
     * Clear errors from the session after they've been used
     */
    public static function clearErrors()
    {
        if (
            isset($_SESSION['errors']) &&
            isset($_SESSION['errors_flash']) &&
            isset($_SESSION['old_input']) &&
            $_SESSION['errors_flash']
        ) {
            unset($_SESSION['errors']);
            unset($_SESSION['old_input']);
            unset($_SESSION['errors_flash']);
        }
    }
}
