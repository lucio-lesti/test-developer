<?php

declare(strict_types=1);

namespace App\Application\Validation;

final class ValidationResult
{
    /** @var array<string,mixed> */
    private array $values;

    /** @var array<string,string[]> */
    private array $errors;

    /**
     * @param array<string,mixed>     $values
     * @param array<string,string[]>  $errors
     */
    public function __construct(array $values, array $errors)
    {
        $this->values = $values;
        $this->errors = $errors;
    }

    public function isValid(): bool
    {
        return $this->errors === [];
    }

    /** @return array<string,mixed> */
    public function values(): array
    {
        return $this->values;
    }

    /** @return array<string,string[]> */
    public function errors(): array
    {
        return $this->errors;
    }
}
