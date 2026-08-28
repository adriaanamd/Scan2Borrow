<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Application\DTO\AuthenticationResult;
use App\Domain\Auth\Principal;
use App\Domain\Auth\Role;
use App\Domain\Auth\UserAccount;
use App\Infrastructure\Persistence\UserRepositoryInterface;
use App\Infrastructure\Persistence\GuestIdentityRepositoryInterface;

final class AuthenticationService implements AuthenticationServiceInterface
{
    public function __construct(
        private readonly UserRepositoryInterface $users,
        private readonly SessionService $sessions,
        private readonly ?GuestIdentityRepositoryInterface $guests = null,
    ) {
    }

    public function loginBorrower(string $barcode): AuthenticationResult
    {
        $barcode = trim($barcode);
        if ($barcode === '') {
            return AuthenticationResult::failure('Please scan your Student ID barcode.');
        }

        $user = $this->users->findByBarcode($barcode);
        if ($user === null) {
            $guest = $this->guests?->findByGovernmentId($barcode);
            if ($guest !== null) {
                $this->sessions->loginGuest($guest->id());

                return AuthenticationResult::success('/guest/dashboard');
            }

            $role = preg_match('/[A-Za-z]/', $barcode) === 1 ? Role::TEACHER : Role::STUDENT;

            return AuthenticationResult::registrationRequired($role);
        }

        if ($user->status() !== 'active') {
            return AuthenticationResult::failure('This account is inactive. Please see the librarian.');
        }

        if (!in_array($user->role(), [Role::STUDENT, Role::TEACHER], true)) {
            return AuthenticationResult::failure('Staff accounts must use the Staff Login page.');
        }

        return $this->completeLogin($user, $this->borrowerHome($user->role()));
    }

    public function loginStaff(string $barcode, string $password): AuthenticationResult
    {
        $barcode = trim($barcode);
        if ($barcode === '' || $password === '') {
            return AuthenticationResult::failure('Enter your staff ID and password.');
        }

        $user = $this->users->findByBarcode($barcode);
        if ($user?->locked() === true) {
            return AuthenticationResult::failure(
                'Account temporarily locked due to too many failed attempts. Please try again later.',
            );
        }

        if ($user === null || !in_array($user->role(), [Role::ADMIN, Role::LIBRARIAN], true)) {
            $this->users->recordLoginFailure(null, $barcode);

            return AuthenticationResult::failure('No staff account found for that ID.');
        }

        if ($user->status() !== 'active') {
            $this->users->recordLoginFailure($user->id(), $barcode);

            return AuthenticationResult::failure('This account is inactive.');
        }

        if ($user->passwordHash() === null || !password_verify($password, $user->passwordHash())) {
            $this->users->recordLoginFailure($user->id(), $barcode);
            if ($user->failedAttempts() + 1 >= 5) {
                $this->users->lock($barcode, 15);

                return AuthenticationResult::failure(
                    'Too many failed attempts. Account locked for 15 minutes.',
                );
            }

            return AuthenticationResult::failure('Invalid staff password.');
        }

        $this->users->recordLoginSuccess($user->id(), $barcode);

        return $this->completeLogin($user, '/staff/dashboard');
    }

    private function completeLogin(UserAccount $user, string $redirect): AuthenticationResult
    {
        $this->sessions->login(new Principal($user->id(), $user->role()));

        return AuthenticationResult::success($redirect);
    }

    private function borrowerHome(Role $role): string
    {
        return $role === Role::TEACHER ? '/teacher/dashboard' : '/student/dashboard';
    }
}
