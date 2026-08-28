<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Application\DTO\GuestProfileUpdateRequest;
use PDO;

final class PdoVisitorDetailsRepository implements VisitorDetailsRepositoryInterface
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function find(int $visitorId): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT firstname, middlename, lastname, suffix, gender, birthdate, contact_no, email, house_no, street, barangay, municipality, province, purpose, purpose_other, id_type, id_barcode, photo FROM visitors WHERE id = :id LIMIT 1'
        );
        $statement->execute(['id' => $visitorId]);
        /** @var array<string, mixed>|false $row */
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : $row;
    }

    public function pass(int $visitorId): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT CONCAT(firstname, \' \', lastname) AS name, id_type, id_barcode, created_at AS registered_date FROM visitors WHERE id = :id LIMIT 1'
        );
        $statement->execute(['id' => $visitorId]);
        /** @var array<string, mixed>|false $row */
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : $row;
    }

    public function updateProfile(int $visitorId, GuestProfileUpdateRequest $request): void
    {
        $statement = $this->pdo->prepare(
            'UPDATE visitors SET contact_no = :contact_no, email = :email, house_no = :house_no, street = :street, barangay = :barangay, municipality = :municipality, province = :province, purpose = :purpose, purpose_other = :purpose_other WHERE id = :id'
        );
        $statement->execute([
            'contact_no' => $request->contactNo,
            'email' => $this->nullable($request->email),
            'house_no' => $request->houseNo,
            'street' => $request->street,
            'barangay' => $request->barangay,
            'municipality' => $request->municipality,
            'province' => $request->province,
            'purpose' => $request->purpose,
            'purpose_other' => $this->nullable($request->purposeOther),
            'id' => $visitorId,
        ]);
    }

    private function nullable(string $value): ?string
    {
        return trim($value) === '' ? null : $value;
    }
}
