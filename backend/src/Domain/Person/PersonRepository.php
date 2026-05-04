<?php

declare(strict_types=1);

namespace App\Domain\Person;

interface PersonRepository
{
    /**
     * @param array{
     *     role?: string,
     *     q?: string,
     *     sort?: string,
     *     order?: string,
     *     limit?: int,
     *     offset?: int
     * } $criteria
     * @return Person[]
     */
    public function findAll(array $criteria = []): array;

    /**
     * @throws PersonNotFoundException
     */
    public function findPersonOfId(int $id): Person;

    public function findByEmail(string $email): ?Person;

    /**
     * @throws PersonAlreadyExistsException
     */
    public function save(Person $person): Person;

    /**
     * @throws PersonAlreadyExistsException
     * @throws PersonNotFoundException
     */
    public function update(Person $person): Person;

    public function delete(int $id): void;
}
