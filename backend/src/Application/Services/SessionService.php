<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Domain\Auth\Principal;
use App\Domain\Auth\SessionIdentity;
use App\Infrastructure\Session\SessionStoreInterface;

final class SessionService
{
    private const IDENTITY_KEY = 'scan2borrow.identity';
    private const GUEST_ID_KEY = 'scan2borrow.visitor_id';
    private const REGISTRATION_BARCODE_KEY = 'scan2borrow.registration_barcode';
    private const GUEST_OTP_TOKEN_KEY = 'scan2borrow.guest_otp_token';

    public function __construct(
        private readonly SessionStoreInterface $store,
    ) {
    }

    public function start(): void
    {
        $this->store->start();
    }

    public function current(): ?SessionIdentity
    {
        $this->start();
        $identity = $this->store->get(self::IDENTITY_KEY);

        return $identity instanceof SessionIdentity ? $identity : null;
    }

    public function login(Principal $principal): void
    {
        $this->start();
        $this->store->regenerate();
        $this->store->remove(self::GUEST_ID_KEY);
        $this->store->set(
            self::IDENTITY_KEY,
            new SessionIdentity($principal->id(), $principal->role(), $this->store->id()),
        );
    }

    public function loginGuest(int $visitorId): void
    {
        $this->start();
        $this->store->regenerate();
        $this->store->remove(self::IDENTITY_KEY);
        $this->store->set(self::GUEST_ID_KEY, $visitorId);
    }

    public function currentGuestId(): ?int
    {
        $this->start();
        $value = $this->store->get(self::GUEST_ID_KEY);

        return is_int($value) ? $value : (is_string($value) && is_numeric($value) ? (int) $value : null);
    }

    public function setRegistrationBarcode(string $barcode): void
    {
        $this->store->set(self::REGISTRATION_BARCODE_KEY, $barcode);
    }

    public function registrationBarcode(): string
    {
        $value = $this->store->get(self::REGISTRATION_BARCODE_KEY);

        return is_string($value) ? $value : '';
    }

    public function setGuestOtpToken(string $token): void
    {
        $this->store->set(self::GUEST_OTP_TOKEN_KEY, $token);
    }

    public function guestOtpToken(): string
    {
        $value = $this->store->get(self::GUEST_OTP_TOKEN_KEY);

        return is_string($value) ? $value : '';
    }

    public function clearRegistrationState(): void
    {
        $this->store->remove(self::REGISTRATION_BARCODE_KEY);
        $this->store->remove(self::GUEST_OTP_TOKEN_KEY);
    }

    public function logout(): void
    {
        $this->start();
        $this->store->remove(self::IDENTITY_KEY);
        $this->store->remove(self::GUEST_ID_KEY);
        $this->clearRegistrationState();
        $this->store->regenerate();
    }
}
