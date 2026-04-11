<?php

namespace App\Service;

use Psr\Log\LoggerInterface;

class ValidationService
{
    private array $errors = [];

    public function __construct(
        private readonly ?LoggerInterface $logger = null
    ) {
    }

    /**
     * Validate required fields
     */
    public function validateRequired(array $data, array $requiredFields): self
    {
        foreach ($requiredFields as $field) {
            if (!isset($data[$field]) || trim($data[$field]) === '') {
                $this->errors[$field][] = "The {$field} field is required.";
                $this->logValidationError($field, 'required', "Field {$field} is required but missing");
            }
        }
        return $this;
    }

    /**
     * Validate email format
     */
    public function validateEmail(string $email, string $fieldName = 'email'): self
    {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->errors[$fieldName][] = "The {$fieldName} must be a valid email address.";
            $this->logValidationError($fieldName, 'email', "Invalid email format: {$email}");
        }
        return $this;
    }

    /**
     * Validate date format and range
     */
    public function validateDate(string $date, string $fieldName = 'date', ?string $format = 'Y-m-d'): self
    {
        $d = \DateTime::createFromFormat($format, $date);
        if (!$d || $d->format($format) !== $date) {
            $this->errors[$fieldName][] = "The {$fieldName} must be a valid date in {$format} format.";
            $this->logValidationError($fieldName, 'date', "Invalid date format: {$date}");
        }
        return $this;
    }

    /**
     * Validate date is not in the past
     */
    public function validateDateNotPast(string $date, string $fieldName = 'date', ?string $format = 'Y-m-d'): self
    {
        $d = \DateTime::createFromFormat($format, $date);
        if ($d && $d < new \DateTime('today')) {
            $this->errors[$fieldName][] = "The {$fieldName} cannot be in the past.";
            $this->logValidationError($fieldName, 'date_past', "Date {$date} is in the past");
        }
        return $this;
    }

    /**
     * Validate end date is after start date
     */
    public function validateDateRange(string $startDate, string $endDate, string $startField = 'start_date', string $endField = 'end_date', ?string $format = 'Y-m-d'): self
    {
        $start = \DateTime::createFromFormat($format, $startDate);
        $end = \DateTime::createFromFormat($format, $endDate);
        
        if ($start && $end && $end < $start) {
            $this->errors[$endField][] = "The {$endField} must be after {$startField}.";
            $this->logValidationError($endField, 'date_range', "End date {$endDate} is before start date {$startDate}");
        }
        return $this;
    }

    /**
     * Validate numeric value
     */
    public function validateNumber(mixed $value, string $fieldName = 'number', ?float $min = null, ?float $max = null): self
    {
        if (!is_numeric($value)) {
            $this->errors[$fieldName][] = "The {$fieldName} must be a number.";
            $this->logValidationError($fieldName, 'number', "Value {$value} is not numeric");
            return $this;
        }

        $num = (float) $value;
        if ($min !== null && $num < $min) {
            $this->errors[$fieldName][] = "The {$fieldName} must be at least {$min}.";
            $this->logValidationError($fieldName, 'number_min', "Value {$num} is less than minimum {$min}");
        }
        if ($max !== null && $num > $max) {
            $this->errors[$fieldName][] = "The {$fieldName} must be at most {$max}.";
            $this->logValidationError($fieldName, 'number_max', "Value {$num} is greater than maximum {$max}");
        }
        return $this;
    }

    /**
     * Validate string length
     */
    public function validateString(string $value, string $fieldName = 'string', ?int $minLength = null, ?int $maxLength = null): self
    {
        $length = strlen($value);
        
        if ($minLength !== null && $length < $minLength) {
            $this->errors[$fieldName][] = "The {$fieldName} must be at least {$minLength} characters.";
            $this->logValidationError($fieldName, 'string_min', "String length {$length} is less than minimum {$minLength}");
        }
        if ($maxLength !== null && $length > $maxLength) {
            $this->errors[$fieldName][] = "The {$fieldName} must be at most {$maxLength} characters.";
            $this->logValidationError($fieldName, 'string_max', "String length {$length} is greater than maximum {$maxLength}");
        }
        return $this;
    }

    /**
     * Validate string contains only letters
     */
    public function validateAlpha(string $value, string $fieldName = 'string'): self
    {
        if (!ctype_alpha($value)) {
            $this->errors[$fieldName][] = "The {$fieldName} must contain only letters.";
            $this->logValidationError($fieldName, 'alpha', "Value contains non-letter characters");
        }
        return $this;
    }

    /**
     * Validate string contains only letters and numbers
     */
    public function validateAlphaNum(string $value, string $fieldName = 'string'): self
    {
        if (!ctype_alnum($value)) {
            $this->errors[$fieldName][] = "The {$fieldName} must contain only letters and numbers.";
            $this->logValidationError($fieldName, 'alphanum', "Value contains special characters");
        }
        return $this;
    }

    /**
     * Custom validation rule
     */
    public function validateCustom(mixed $value, callable $validator, string $message, string $fieldName = 'field'): self
    {
        if (!$validator($value)) {
            $this->errors[$fieldName][] = $message;
            $this->logValidationError($fieldName, 'custom', "Custom validation failed: {$message}");
        }
        return $this;
    }

    /**
     * Validate price is positive
     */
    public function validatePrice(mixed $price, string $fieldName = 'price'): self
    {
        return $this->validateNumber($price, $fieldName, 0);
    }

    /**
     * Validate phone number format
     */
    public function validatePhone(string $phone, string $fieldName = 'phone'): self
    {
        // Basic phone validation - allows digits, spaces, dashes, parentheses, plus sign
        $pattern = '/^[\d\s\-\(\)\+]+$/';
        if (!preg_match($pattern, $phone) || !ctype_digit(str_replace([' ', '-', '(', ')', '+'], '', $phone))) {
            $this->errors[$fieldName][] = "The {$fieldName} must be a valid phone number.";
            $this->logValidationError($fieldName, 'phone', "Invalid phone format: {$phone}");
        }
        return $this;
    }

    /**
     * Check if validation passed
     */
    public function isValid(): bool
    {
        return empty($this->errors);
    }

    /**
     * Get all validation errors
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * Get errors for a specific field
     */
    public function getFieldErrors(string $field): array
    {
        return $this->errors[$field] ?? [];
    }

    /**
     * Clear all errors
     */
    public function clearErrors(): self
    {
        $this->errors = [];
        return $this;
    }

    /**
     * Log validation error
     */
    private function logValidationError(string $field, string $type, string $message): void
    {
        if ($this->logger) {
            $this->logger->warning("Validation error: {$field} [{$type}] - {$message}");
        }
    }

    /**
     * Validate voyage data (business rules)
     */
    public function validateVoyage(array $data): self
    {
        $this->clearErrors();

        // Required fields
        $this->validateRequired($data, ['title', 'destination', 'start_date', 'end_date', 'price']);

        // String validation
        if (isset($data['title'])) {
            $this->validateString($data['title'], 'title', 3, 200);
        }
        if (isset($data['destination'])) {
            $this->validateString($data['destination'], 'destination', 2, 100);
        }

        // Date validation
        if (isset($data['start_date'])) {
            $this->validateDate($data['start_date'], 'start_date');
            $this->validateDateNotPast($data['start_date'], 'start_date');
        }
        if (isset($data['end_date'])) {
            $this->validateDate($data['end_date'], 'end_date');
        }

        // Date range
        if (isset($data['start_date']) && isset($data['end_date'])) {
            $this->validateDateRange($data['start_date'], $data['end_date']);
        }

        // Price validation
        if (isset($data['price'])) {
            $this->validatePrice($data['price'], 'price');
        }

        return $this;
    }

    /**
     * Validate user registration data
     */
    public function validateUserRegistration(array $data): self
    {
        $this->clearErrors();

        // Required fields
        $this->validateRequired($data, ['username', 'email', 'password']);

        // Email validation
        if (isset($data['email'])) {
            $this->validateEmail($data['email']);
        }

        // String validation
        if (isset($data['username'])) {
            $this->validateString($data['username'], 'username', 3, 50);
            $this->validateAlphaNum($data['username'], 'username');
        }

        if (isset($data['password'])) {
            $this->validateString($data['password'], 'password', 6);
        }

     

        return $this;
    }

    /**
     * Validate login data
     */
    public function validateLogin(array $data): self
    {
        $this->clearErrors();

        $this->validateRequired($data, ['email', 'password']);

        if (isset($data['email'])) {
            $this->validateEmail($data['email']);
        }

        return $this;
    }
}