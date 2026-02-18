<?php
/**
 * Form Helper Class - Form Processing Utilities
 * 
 * Provides utilities for form handling: value retrieval, CSRF token generation,
 * form field rendering, and error display. Simplifies form processing logic.
 * 
 * Usage:
 * - FormHelper::getValue('email') - get POST value with default
 * - FormHelper::setAttribute('email', $value) - set form attribute
 * - FormHelper::renderInput('email', 'Email Address') - render form field
 * - FormHelper::displayErrors('email') - show validation errors
 */

class FormHelper {

    // Track form field values (populated from POST or manually)
    private static $values = [];
    
    // Track form field errors
    private static $errors = [];

    /**
     * Initialize form with POST/GET data
     * 
     * @param array $source Data source (typically $_POST or $_GET)
     * @return void
     */
    public static function init($source = null) {
        if ($source === null) {
            $source = $_POST;
        }

        self::$values = $source;
    }

    /**
     * Get form field value
     * 
     * @param string $field Field name
     * @param string $default Default value if not set
     * @return string Field value or default
     */
    public static function getValue($field, $default = '') {
        return isset(self::$values[$field]) ? htmlspecialchars(self::$values[$field], ENT_QUOTES) : $default;
    }

    /**
     * Set form field value manually
     * 
     * @param string $field Field name
     * @param mixed $value Field value
     * @return void
     */
    public static function setValue($field, $value) {
        self::$values[$field] = $value;
    }

    /**
     * Check if field has error
     * 
     * @param string $field Field name
     * @return bool
     */
    public static function hasError($field) {
        return isset(self::$errors[$field]) && !empty(self::$errors[$field]);
    }

    /**
     * Get field error message
     * 
     * @param string $field Field name
     * @return string Error message or empty string
     */
    public static function getError($field) {
        return isset(self::$errors[$field]) ? self::$errors[$field] : '';
    }

    /**
     * Set field error
     * 
     * @param string $field Field name
     * @param string $message Error message
     * @return void
     */
    public static function setError($field, $message) {
        self::$errors[$field] = $message;
    }

    /**
     * Set multiple field errors
     * 
     * @param array $errors Field => Message array
     * @return void
     */
    public static function setErrors($errors) {
        self::$errors = array_merge(self::$errors, $errors);
    }

    /**
     * Clear all errors
     * 
     * @return void
     */
    public static function clearErrors() {
        self::$errors = [];
    }

    /**
     * Get all errors
     * 
     * @return array All errors
     */
    public static function getErrors() {
        return self::$errors;
    }

    /**
     * Check if form has any errors
     * 
     * @return bool
     */
    public static function hasErrors() {
        return !empty(self::$errors);
    }

    /**
     * Render text input field with error display
     * 
     * @param string $name Field name
     * @param string $label Field label
     * @param array $options Optional: type, placeholder, class, required, pattern, etc.
     * @return string HTML
     */
    public static function renderInput($name, $label = '', $options = []) {
        $type = $options['type'] ?? 'text';
        $placeholder = $options['placeholder'] ?? '';
        $class = $options['class'] ?? 'form-control';
        $required = $options['required'] ?? false;
        $pattern = $options['pattern'] ?? '';
        $value = self::getValue($name, $options['value'] ?? '');
        $errorClass = self::hasError($name) ? ' is-invalid' : '';

        $html = '<div class="mb-3">';
        if ($label) {
            $requiredHtml = $required ? '<span class="text-danger">*</span>' : '';
            $html .= '<label for="' . htmlspecialchars($name) . '" class="form-label">' . htmlspecialchars($label) . ' ' . $requiredHtml . '</label>';
        }

        $html .= '<input type="' . htmlspecialchars($type) . '" ';
        $html .= 'class="' . htmlspecialchars($class) . $errorClass . '" ';
        $html .= 'name="' . htmlspecialchars($name) . '" ';
        $html .= 'id="' . htmlspecialchars($name) . '" ';
        $html .= 'value="' . $value . '" ';

        if ($placeholder) {
            $html .= 'placeholder="' . htmlspecialchars($placeholder) . '" ';
        }
        if ($required) {
            $html .= 'required ';
        }
        if ($pattern) {
            $html .= 'pattern="' . htmlspecialchars($pattern) . '" ';
        }

        $html .= '>';

        if (self::hasError($name)) {
            $html .= '<div class="invalid-feedback d-block">' . htmlspecialchars(self::getError($name)) . '</div>';
        }

        $html .= '</div>';

        return $html;
    }

    /**
     * Render textarea field with error display
     * 
     * @param string $name Field name
     * @param string $label Field label
     * @param array $options Optional: rows, placeholder, class, required, etc.
     * @return string HTML
     */
    public static function renderTextarea($name, $label = '', $options = []) {
        $rows = $options['rows'] ?? 4;
        $placeholder = $options['placeholder'] ?? '';
        $class = $options['class'] ?? 'form-control';
        $required = $options['required'] ?? false;
        $value = self::getValue($name, $options['value'] ?? '');
        $errorClass = self::hasError($name) ? ' is-invalid' : '';

        $html = '<div class="mb-3">';
        if ($label) {
            $requiredHtml = $required ? '<span class="text-danger">*</span>' : '';
            $html .= '<label for="' . htmlspecialchars($name) . '" class="form-label">' . htmlspecialchars($label) . ' ' . $requiredHtml . '</label>';
        }

        $html .= '<textarea ';
        $html .= 'class="' . htmlspecialchars($class) . $errorClass . '" ';
        $html .= 'name="' . htmlspecialchars($name) . '" ';
        $html .= 'id="' . htmlspecialchars($name) . '" ';
        $html .= 'rows="' . intval($rows) . '" ';

        if ($placeholder) {
            $html .= 'placeholder="' . htmlspecialchars($placeholder) . '" ';
        }
        if ($required) {
            $html .= 'required ';
        }

        $html .= '>' . htmlspecialchars($value) . '</textarea>';

        if (self::hasError($name)) {
            $html .= '<div class="invalid-feedback d-block">' . htmlspecialchars(self::getError($name)) . '</div>';
        }

        $html .= '</div>';

        return $html;
    }

    /**
     * Render select dropdown with error display
     * 
     * @param string $name Field name
     * @param array $options Select options: value => label
     * @param string $label Field label
     * @param array $attributes Optional: class, required, etc.
     * @return string HTML
     */
    public static function renderSelect($name, $options = [], $label = '', $attributes = []) {
        $class = $attributes['class'] ?? 'form-control';
        $required = $attributes['required'] ?? false;
        $selected = self::getValue($name, $attributes['value'] ?? '');
        $errorClass = self::hasError($name) ? ' is-invalid' : '';

        $html = '<div class="mb-3">';
        if ($label) {
            $requiredHtml = $required ? '<span class="text-danger">*</span>' : '';
            $html .= '<label for="' . htmlspecialchars($name) . '" class="form-label">' . htmlspecialchars($label) . ' ' . $requiredHtml . '</label>';
        }

        $html .= '<select ';
        $html .= 'class="' . htmlspecialchars($class) . $errorClass . '" ';
        $html .= 'name="' . htmlspecialchars($name) . '" ';
        $html .= 'id="' . htmlspecialchars($name) . '" ';

        if ($required) {
            $html .= 'required ';
        }

        $html .= '>';

        foreach ($options as $value => $label) {
            $isSelected = ($value === $selected) ? 'selected' : '';
            $html .= '<option value="' . htmlspecialchars($value) . '" ' . $isSelected . '>' . htmlspecialchars($label) . '</option>';
        }

        $html .= '</select>';

        if (self::hasError($name)) {
            $html .= '<div class="invalid-feedback d-block">' . htmlspecialchars(self::getError($name)) . '</div>';
        }

        $html .= '</div>';

        return $html;
    }

    /**
     * Render checkbox field
     * 
     * @param string $name Field name
     * @param string $label Field label
     * @param mixed $value Checkbox value
     * @param bool $checked Pre-checked state
     * @return string HTML
     */
    public static function renderCheckbox($name, $label = '', $value = '1', $checked = false) {
        $isChecked = $checked || (self::getValue($name) === $value);
        $checkedHtml = $isChecked ? 'checked' : '';

        $html = '<div class="mb-3 form-check">';
        $html .= '<input type="checkbox" class="form-check-input" ';
        $html .= 'name="' . htmlspecialchars($name) . '" ';
        $html .= 'id="' . htmlspecialchars($name) . '" ';
        $html .= 'value="' . htmlspecialchars($value) . '" ';
        $html .= $checkedHtml . '>';

        if ($label) {
            $html .= '<label class="form-check-label" for="' . htmlspecialchars($name) . '">' . htmlspecialchars($label) . '</label>';
        }

        $html .= '</div>';

        return $html;
    }

    /**
     * Render form section for displaying all errors
     * 
     * @return string HTML alert or empty string
     */
    public static function renderErrors() {
        if (empty(self::$errors)) {
            return '';
        }

        $html = '<div class="alert alert-danger alert-dismissible fade show" role="alert">';
        $html .= '<strong>Please fix the following errors:</strong>';
        $html .= '<ul class="mb-0 mt-2">';

        foreach (self::$errors as $field => $message) {
            $html .= '<li>' . htmlspecialchars($message) . '</li>';
        }

        $html .= '</ul>';
        $html .= '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>';
        $html .= '</div>';

        return $html;
    }

    /**
     * Get old input from session (for repopulation after redirect)
     * 
     * @param string $field Field name
     * @param string $default Default value
     * @return string Field value from old input or default
     */
    public static function old($field, $default = '') {
        if (isset($_SESSION['old_input'][$field])) {
            $value = $_SESSION['old_input'][$field];
            unset($_SESSION['old_input'][$field]);
            return htmlspecialchars($value, ENT_QUOTES);
        }

        return $default;
    }

    /**
     * Store old input in session for retrieval after redirect
     * 
     * @param array $input Input to store (typically $_POST)
     * @return void
     */
    public static function storeOldInput($input) {
        $_SESSION['old_input'] = $input;
    }

}

?>
