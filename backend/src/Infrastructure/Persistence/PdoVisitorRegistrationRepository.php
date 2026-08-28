<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Application\DTO\GuestRegistrationRequest;
use PDO;

final class PdoVisitorRegistrationRepository implements VisitorRegistrationRepositoryInterface
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function existsByIdBarcode(string $idBarcode): bool
    {
        $statement = $this->pdo->prepare('SELECT id FROM visitors WHERE id_barcode = :id_barcode LIMIT 1');
        $statement->execute(['id_barcode' => $idBarcode]);

        return $statement->fetchColumn() !== false;
    }

    public function create(GuestRegistrationRequest $request): int
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO visitors '
            . '(visitor_number, qr_token, firstname, middlename, lastname, suffix, gender, birthdate, contact_no, email, house_no, street, barangay, municipality, province, purpose, purpose_other, id_type, id_barcode, photo, is_verified, verified_at, account_status) '
            . 'VALUES (NULL, :qr_token, :firstname, :middlename, :lastname, :suffix, :gender, :birthdate, :contact_no, :email, :house_no, :street, :barangay, :municipality, :province, :purpose, :purpose_other, :id_type, :id_barcode, :photo, 1, CURRENT_TIMESTAMP, \'Active\')'
        );
        $statement->execute([
            'qr_token' => bin2hex(random_bytes(16)),
            'firstname' => $request->firstname,
            'middlename' => $this->nullable($request->middlename),
            'lastname' => $request->lastname,
            'suffix' => $this->nullable($request->suffix),
            'gender' => $request->gender,
            'birthdate' => $request->birthdate,
            'contact_no' => $request->contactNo,
            'email' => $this->nullable($request->email),
            'house_no' => $request->houseNo,
            'street' => $request->street,
            'barangay' => $request->barangay,
            'municipality' => $request->municipality,
            'province' => $request->province,
            'purpose' => $request->purpose,
            'purpose_other' => $this->nullable($request->purposeOther),
            'id_type' => $request->idType,
            'id_barcode' => $request->idBarcode,
            'photo' => $request->photoData,
        ]);

        $id = (int) $this->pdo->lastInsertId();
        $visitorNumber = sprintf('VIS-%s-%06d', date('Y'), $id);
        $this->pdo->prepare('UPDATE visitors SET visitor_number = ? WHERE id = ?')->execute([$visitorNumber, $id]);

        return $id;
    }

    private function nullable(string $value): ?string
    {
        return $value === '' ? null : $value;
    }
}
