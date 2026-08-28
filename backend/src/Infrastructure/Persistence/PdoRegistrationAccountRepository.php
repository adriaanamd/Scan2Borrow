<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use PDO;

final class PdoRegistrationAccountRepository implements RegistrationAccountRepositoryInterface
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function existsByBarcode(string $barcode): bool
    {
        $statement = $this->pdo->prepare('SELECT id FROM users WHERE barcode = :barcode LIMIT 1');
        $statement->execute(['barcode' => $barcode]);

        return $statement->fetchColumn() !== false;
    }

    public function createAccount(array $payload, ?string $photoPath): int
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO users '
            . '(barcode, firstname, middlename, lastname, course, year_level, email, contact_no, role, photo, status, department, position, password_hash) '
            . 'VALUES (:barcode, :firstname, :middlename, :lastname, :course, :year_level, :email, :contact_no, :role, :photo, \'active\', :department, :position, NULL)'
        );
        $statement->execute([
            'barcode' => $payload['barcode'] ?? '',
            'firstname' => $payload['firstname'] ?? '',
            'middlename' => $this->nullable($payload['middlename'] ?? ''),
            'lastname' => $payload['lastname'] ?? '',
            'course' => $this->nullable($payload['course'] ?? ''),
            'year_level' => $this->nullable($payload['year_level'] ?? ''),
            'email' => $this->nullable($payload['email'] ?? ''),
            'contact_no' => $this->nullable($payload['contact_no'] ?? ''),
            'role' => $payload['role'] ?? '',
            'photo' => $photoPath,
            'department' => $this->nullable($payload['department'] ?? ''),
            'position' => $this->nullable($payload['position'] ?? ''),
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    private function nullable(string $value): ?string
    {
        return $value === '' ? null : $value;
    }
}
