<?php

declare(strict_types=1);

namespace App\Domain\Person;

use JsonSerializable;

final class Person implements JsonSerializable
{
    public const ROLES = ['admin', 'user', 'moderator', 'guest'];

    private ?int $id;
    private string $firstName;
    private string $lastName;
    private string $email;
    private ?string $dateOfBirth;
    private ?string $phoneNumber;
    private ?string $role;
    private ?string $notes;

    public function __construct(
        ?int $id,
        string $firstName,
        string $lastName,
        string $email,
        ?string $dateOfBirth = null,
        ?string $phoneNumber = null,
        ?string $role = null,
        ?string $notes = null
    ) {
        $this->id = $id;
        $this->firstName = $firstName;
        $this->lastName = $lastName;
        $this->email = strtolower($email);
        $this->dateOfBirth = $dateOfBirth;
        $this->phoneNumber = $phoneNumber;
        $this->role = $role;
        $this->notes = $notes;
    }

    public function getId(): ?int { return $this->id; }
    public function getFirstName(): string { return $this->firstName; }
    public function getLastName(): string { return $this->lastName; }
    public function getEmail(): string { return $this->email; }
    public function getDateOfBirth(): ?string { return $this->dateOfBirth; }
    public function getPhoneNumber(): ?string { return $this->phoneNumber; }
    public function getRole(): ?string { return $this->role; }
    public function getNotes(): ?string { return $this->notes; }

    public function withId(int $id): self
    {
        $clone = clone $this;
        $clone->id = $id;
        return $clone;
    }

    /**
     * @return array<string,mixed>
     */
    #[\ReturnTypeWillChange]
    public function jsonSerialize(): array
    {
        return [
            'id'            => $this->id,
            'first_name'    => $this->firstName,
            'last_name'     => $this->lastName,
            'email'         => $this->email,
            'date_of_birth' => $this->dateOfBirth,
            'phone_number'  => $this->phoneNumber,
            'role'          => $this->role,
            'notes'         => $this->notes,
        ];
    }
}
