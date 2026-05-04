<?php

declare(strict_types=1);

namespace App\Domain\Person;

use App\Domain\DomainException\DomainException;

final class PersonAlreadyExistsException extends DomainException
{
    public $message = 'A person with this email already exists.';
}
