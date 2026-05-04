<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Person;

use App\Domain\Person\Person;
use App\Domain\Person\PersonAlreadyExistsException;
use App\Domain\Person\PersonNotFoundException;
use App\Domain\Person\PersonRepository;
use PDO;
use PDOException;

final class PdoPersonRepository implements PersonRepository
{
    private const ALLOWED_SORT  = ['id', 'first_name', 'last_name', 'email', 'role', 'date_of_birth'];
    private const ALLOWED_ORDER = ['asc', 'desc'];
    private const DEFAULT_LIMIT = 50;
    private const MAX_LIMIT     = 200;

    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function findAll(array $criteria = []): array
    {
        $where = [];
        $params = [];

        if (!empty($criteria['role']) && in_array($criteria['role'], Person::ROLES, true)) {
            $where[] = 'role = :role';
            $params['role'] = $criteria['role'];
        }

        if (!empty($criteria['q']) && is_string($criteria['q'])) {
            $where[] = '(first_name LIKE :q OR last_name LIKE :q OR email LIKE :q)';
            $params['q'] = '%' . $this->escapeLike($criteria['q']) . '%';
        }

        $sortField = in_array($criteria['sort'] ?? null, self::ALLOWED_SORT, true)
            ? $criteria['sort']
            : 'id';

        $sortOrder = in_array(strtolower((string) ($criteria['order'] ?? '')), self::ALLOWED_ORDER, true)
            ? strtolower((string) $criteria['order'])
            : 'asc';

        $limit  = $this->normalizeLimit($criteria['limit'] ?? null);
        $offset = $this->normalizeOffset($criteria['offset'] ?? null);

        $sql = 'SELECT * FROM persons';
        if ($where !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= sprintf(' ORDER BY %s %s LIMIT %d OFFSET %d', $sortField, $sortOrder, $limit, $offset);

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return array_map([$this, 'mapRow'], $stmt->fetchAll(PDO::FETCH_ASSOC) ?: []);
    }

    public function findPersonOfId(int $id): Person
    {
        $stmt = $this->pdo->prepare('SELECT * FROM persons WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            throw new PersonNotFoundException();
        }
        return $this->mapRow($row);
    }

    public function findByEmail(string $email): ?Person
    {
        $stmt = $this->pdo->prepare('SELECT * FROM persons WHERE email = :email');
        $stmt->execute(['email' => strtolower(trim($email))]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? $this->mapRow($row) : null;
    }

    public function save(Person $person): Person
    {
        $sql = 'INSERT INTO persons (first_name, last_name, email, date_of_birth, phone_number, role, notes)
                VALUES (:first_name, :last_name, :email, :date_of_birth, :phone_number, :role, :notes)';
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                'first_name'    => $person->getFirstName(),
                'last_name'     => $person->getLastName(),
                'email'         => $person->getEmail(),
                'date_of_birth' => $person->getDateOfBirth(),
                'phone_number'  => $person->getPhoneNumber(),
                'role'          => $person->getRole(),
                'notes'         => $person->getNotes(),
            ]);
        } catch (PDOException $e) {
            if ($this->isUniqueViolation($e)) {
                throw new PersonAlreadyExistsException();
            }
            throw $e;
        }

        return $person->withId((int) $this->pdo->lastInsertId());
    }

    public function update(Person $person): Person
    {
        if ($person->getId() === null) {
            throw new PersonNotFoundException();
        }

        $sql = 'UPDATE persons SET
                    first_name    = :first_name,
                    last_name     = :last_name,
                    email         = :email,
                    date_of_birth = :date_of_birth,
                    phone_number  = :phone_number,
                    role          = :role,
                    notes         = :notes
                WHERE id = :id';

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                'id'            => $person->getId(),
                'first_name'    => $person->getFirstName(),
                'last_name'     => $person->getLastName(),
                'email'         => $person->getEmail(),
                'date_of_birth' => $person->getDateOfBirth(),
                'phone_number'  => $person->getPhoneNumber(),
                'role'          => $person->getRole(),
                'notes'         => $person->getNotes(),
            ]);
        } catch (PDOException $e) {
            if ($this->isUniqueViolation($e)) {
                throw new PersonAlreadyExistsException();
            }
            throw $e;
        }

        if ($stmt->rowCount() === 0) {
            // SQLite riporta 0 se nessun valore è cambiato; verifichiamo l'esistenza in modo esplicito.
            $this->findPersonOfId($person->getId());
        }

        return $person;
    }

    public function delete(int $id): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM persons WHERE id = :id');
        $stmt->execute(['id' => $id]);
        if ($stmt->rowCount() === 0) {
            throw new PersonNotFoundException();
        }
    }

    private function mapRow(array $row): Person
    {
        return new Person(
            isset($row['id']) ? (int) $row['id'] : null,
            (string) $row['first_name'],
            (string) $row['last_name'],
            (string) $row['email'],
            $row['date_of_birth'] !== null ? (string) $row['date_of_birth'] : null,
            $row['phone_number']  !== null ? (string) $row['phone_number']  : null,
            $row['role']          !== null ? (string) $row['role']          : null,
            $row['notes']         !== null ? (string) $row['notes']         : null
        );
    }

    private function isUniqueViolation(PDOException $e): bool
    {
        if ($e->getCode() === '23000' || $e->getCode() === 23000) {
            return true;
        }
        $msg = $e->getMessage();
        return stripos($msg, 'unique') !== false || stripos($msg, 'duplicate') !== false;
    }

    private function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }

    /** @param mixed $value */
    private function normalizeLimit($value): int
    {
        if (!is_numeric($value)) {
            return self::DEFAULT_LIMIT;
        }
        $n = (int) $value;
        if ($n <= 0) {
            return self::DEFAULT_LIMIT;
        }
        return min($n, self::MAX_LIMIT);
    }

    /** @param mixed $value */
    private function normalizeOffset($value): int
    {
        if (!is_numeric($value)) {
            return 0;
        }
        $n = (int) $value;
        return $n > 0 ? $n : 0;
    }
}
