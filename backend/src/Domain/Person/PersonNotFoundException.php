<?php
declare(strict_types=1);

namespace App\Domain\Person;

use App\Domain\DomainException\DomainRecordNotFoundException;

class PersonNotFoundException extends DomainRecordNotFoundException
{
    public $message = 'The person you requested does not exist.';
}