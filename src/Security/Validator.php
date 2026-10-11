<?php

namespace App\Security;

use Exception;

class Validator
{
    private array $errors = [];
    private array $sanitized = [];

    /**
     * Extracts ONLY allow-listed fields from data and executes validation rules.
     * Any unknown fields are ignored or rejected.
     */
    public static function validate(array $input, array $rules): self
    {
        $instance = new self();
        
        foreach ($rules as $field => $fieldRules) {
            $value = $input[$field] ?? null;
            $isRequired = in_array('required', $fieldRules, true);

            if ($isRequired && ($value === null || $value === '')) {
                $instance->addError($field, "The field '{$field}' is required.");
                continue;
            }

            if ($value === null || $value === '') {
                $instance->sanitized[$field] = null;
                continue;
            }

            foreach ($fieldRules as $rule) {
                if ($rule === 'required') {
                    continue;
                }

                if ($rule === 'email' && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    $instance->addError($field, "Invalid email format.");
                }

                if ($rule === 'integer' && filter_var($value, FILTER_VALIDATE_INT) === false) {
                    $instance->addError($field, "Field must be an integer.");
                }

                if ($rule === 'numeric' && !is_numeric($value)) {
                    $instance->addError($field, "Field must be numeric.");
                }

                if (str_starts_with($rule, 'min_len:')) {
                    $min = (int)substr($rule, 8);
                    if (mb_strlen((string)$value) < $min) {
                        $instance->addError($field, "Must be at least {$min} characters.");
                    }
                }

                if (str_starts_with($rule, 'max_len:')) {
                    $max = (int)substr($rule, 8);
                    if (mb_strlen((string)$value) > $max) {
                        $instance->addError($field, "Cannot exceed {$max} characters.");
                    }
                }

                if (str_starts_with($rule, 'in:')) {
                    $allowed = explode(',', substr($rule, 3));
                    if (!in_array((string)$value, $allowed, true)) {
                        $instance->addError($field, "Value is not allowed.");
                    }
                }
            }

            $instance->sanitized[$field] = is_string($value) ? trim($value) : $value;
        }

        return $instance;
    }

    public function addError(string $field, string $message): void
    {
        $this->errors[$field] = $message;
    }

    public function passes(): bool
    {
        return empty($this->errors);
    }

    public function fails(): bool
    {
        return !empty($this->errors);
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    public function getFirstError(): ?string
    {
        return !empty($this->errors) ? reset($this->errors) : null;
    }

    public function getData(): array
    {
        return $this->sanitized;
    }
}
