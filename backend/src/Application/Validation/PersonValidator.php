<?php

declare(strict_types=1);

namespace App\Application\Validation;

use App\Domain\Person\Person;
use DateTimeImmutable;

final class PersonValidator
{
    public const ALLOWED_FIELDS = [
        'first_name', 'last_name', 'email',
        'date_of_birth', 'phone_number', 'role', 'notes',
    ];

    private const REQUIRED_FIELDS = ['first_name', 'last_name', 'email'];

    public const MAX_NAME       = 100;
    public const MAX_EMAIL      = 255;
    public const MAX_PHONE      = 20;
    public const MAX_NOTES      = 1000;

    /**
     * Valida un payload per la creazione della risorsa (validazione completa).
     *
     * @param mixed $input
     */
    public function validateCreate($input): ValidationResult
    {
        return $this->validate($input, false);
    }

    /**
     * Valida un payload per l'aggiornamento parziale (PATCH).
     * Vengono validati solo i campi effettivamente presenti.
     *
     * @param mixed $input
     */
    public function validatePatch($input): ValidationResult
    {
        return $this->validate($input, true);
    }

    /**
     * @param mixed $input
     */
    private function validate($input, bool $partial): ValidationResult
    {
        $errors = [];
        $values = [];

        if (!is_array($input)) {
            return new ValidationResult([], ['_body' => ['Il corpo della richiesta deve essere un oggetto JSON.']]);
        }

        $clean = [];
        foreach (self::ALLOWED_FIELDS as $field) {
            if (array_key_exists($field, $input)) {
                $clean[$field] = $input[$field];
            }
        }

        foreach (self::REQUIRED_FIELDS as $field) {
            if ($partial) {
                continue;
            }
            if (!array_key_exists($field, $clean)) {
                $errors[$field][] = 'Campo obbligatorio.';
            }
        }

        foreach ($clean as $field => $raw) {
            switch ($field) {
                case 'first_name':
                case 'last_name':
                    $err = $this->validateName($raw);
                    if ($err !== null) {
                        $errors[$field][] = $err;
                    } else {
                        $values[$field] = trim((string) $raw);
                    }
                    break;

                case 'email':
                    $err = $this->validateEmail($raw);
                    if ($err !== null) {
                        $errors[$field][] = $err;
                    } else {
                        $values[$field] = strtolower(trim((string) $raw));
                    }
                    break;

                case 'date_of_birth':
                    if ($raw === null) {
                        $values[$field] = null;
                        break;
                    }
                    $err = $this->validateDateOfBirth($raw);
                    if ($err !== null) {
                        $errors[$field][] = $err;
                    } else {
                        $values[$field] = trim((string) $raw);
                    }
                    break;

                case 'phone_number':
                    if ($raw === null) {
                        $values[$field] = null;
                        break;
                    }
                    $err = $this->validatePhone($raw);
                    if ($err !== null) {
                        $errors[$field][] = $err;
                    } else {
                        $values[$field] = trim((string) $raw);
                    }
                    break;

                case 'role':
                    if ($raw === null) {
                        $values[$field] = null;
                        break;
                    }
                    $err = $this->validateRole($raw);
                    if ($err !== null) {
                        $errors[$field][] = $err;
                    } else {
                        $values[$field] = strtolower(trim((string) $raw));
                    }
                    break;

                case 'notes':
                    if ($raw === null) {
                        $values[$field] = null;
                        break;
                    }
                    $err = $this->validateNotes($raw);
                    if ($err !== null) {
                        $errors[$field][] = $err;
                    } else {
                        $values[$field] = trim((string) $raw);
                    }
                    break;
            }
        }

        return new ValidationResult($values, $errors);
    }


    /** @param mixed $value */
    private function validateName($value): ?string
    {
        if (!is_string($value)) {
            return 'Deve essere una stringa.';
        }
        $trimmed = trim($value);
        if ($trimmed === '') {
            return 'Non può essere vuoto.';
        }
        if (mb_strlen($trimmed) > self::MAX_NAME) {
            return 'Lunghezza massima ' . self::MAX_NAME . ' caratteri.';
        }
        return null;
    }

    
    /** @param mixed $value */
    private function validateEmail($value): ?string
    {
        if (!is_string($value)) {
            return 'Deve essere una stringa.';
        }
        $trimmed = trim($value);
        if ($trimmed === '') {
            return 'Non può essere vuoto.';
        }
        if (mb_strlen($trimmed) > self::MAX_EMAIL) {
            return 'Lunghezza massima ' . self::MAX_EMAIL . ' caratteri.';
        }
        if (filter_var($trimmed, FILTER_VALIDATE_EMAIL) === false) {
            return 'Indirizzo email non valido.';
        }
        return null;
    }

    /** @param mixed $value */
    private function validateDateOfBirth($value): ?string
    {
        if (!is_string($value)) {
            return 'Deve essere una data in formato ISO-8601.';
        }
        $trimmed = trim($value);
        if ($trimmed === '') {
            return 'Non può essere vuoto.';
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $trimmed) !== 1) {
            return 'Deve essere una data ISO-8601 (AAAA-MM-GG).';
        }
        $date = DateTimeImmutable::createFromFormat('Y-m-d', $trimmed);
        if (!$date || $date->format('Y-m-d') !== $trimmed) {
            return 'Data non valida.';
        }
        $today = new DateTimeImmutable('today');
        if ($date > $today) {
            return 'Non può essere una data futura.';
        }
        return null;
    }

    /** @param mixed $value */
    private function validatePhone($value): ?string
    {
        if (!is_string($value)) {
            return 'Deve essere una stringa.';
        }
        $trimmed = trim($value);
        if ($trimmed === '') {
            return 'Non può essere vuoto.';
        }
        if (mb_strlen($trimmed) > self::MAX_PHONE) {
            return 'Lunghezza massima ' . self::MAX_PHONE . ' caratteri.';
        }
        if (preg_match('/^\+?[0-9 .\-()]{4,}$/', $trimmed) !== 1) {
            return 'Numero di telefono non valido.';
        }
        return null;
    }

    /** @param mixed $value */
    private function validateRole($value): ?string
    {
        if (!is_string($value)) {
            return 'Deve essere una stringa.';
        }
        $trimmed = strtolower(trim($value));
        if ($trimmed === '') {
            return 'Non può essere vuoto.';
        }
        if (!in_array($trimmed, Person::ROLES, true)) {
            return 'Deve essere uno tra: ' . implode(', ', Person::ROLES) . '.';
        }
        return null;
    }

    /** @param mixed $value */
    private function validateNotes($value): ?string
    {
        if (!is_string($value)) {
            return 'Deve essere una stringa.';
        }
        if (mb_strlen($value) > self::MAX_NOTES) {
            return 'Lunghezza massima ' . self::MAX_NOTES . ' caratteri.';
        }
        return null;
    }
}
