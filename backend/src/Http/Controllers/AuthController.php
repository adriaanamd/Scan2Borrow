<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Application\DTO\AuthenticationResult;
use App\Application\Services\AuthenticationServiceInterface;
use App\Application\Services\CsrfService;
use App\Application\Services\SessionService;
use App\Domain\Auth\SessionIdentity;
use App\Http\Requests\ServerRequest;
use App\Http\Responses\JsonResponse;
use App\Http\Responses\ResponseInterface;
use InvalidArgumentException;

final readonly class AuthController
{
    public function __construct(
        private SessionService $sessions,
        private CsrfService $csrf,
        private AuthenticationServiceInterface $authentication,
    ) {
    }

    public function session(ServerRequest $request): JsonResponse
    {
        $identity = $this->sessions->current();

        return new JsonResponse(200, [
            'ok' => true,
            'data' => $this->sessionData($identity),
        ]);
    }

    public function logout(ServerRequest $request): JsonResponse
    {
        try {
            $this->csrf->assertValid($this->submittedToken($request));
        } catch (InvalidArgumentException $exception) {
            return new JsonResponse(419, [
                'ok' => false,
                'errors' => [$exception->getMessage()],
            ]);
        }

        $this->sessions->logout();

        return new JsonResponse(200, ['ok' => true, 'data' => []]);
    }

    public function logoutLegacy(ServerRequest $request): JsonResponse
    {
        $this->sessions->logout();

        return new JsonResponse(200, ['ok' => true, 'data' => ['redirect' => $request->applicationPath('/login')]]);
    }

    public function loginBorrower(ServerRequest $request): JsonResponse
    {
        $csrfError = $this->csrfFailure($request);
        if ($csrfError !== null) {
            return $csrfError;
        }

        return $this->loginResult(
            $this->authentication->loginBorrower($this->stringBodyValue($request, 'barcode')),
            $request->applicationPrefix(),
        );
    }

    public function loginStaff(ServerRequest $request): JsonResponse
    {
        $csrfError = $this->csrfFailure($request);
        if ($csrfError !== null) {
            return $csrfError;
        }

        return $this->loginResult(
            $this->authentication->loginStaff(
                $this->stringBodyValue($request, 'barcode'),
                $this->stringBodyValue($request, 'password'),
            ),
            $request->applicationPrefix(),
        );
    }

    /**
     * @return array{authenticated: bool, user_id?: int, role?: string}
     */
    private function sessionData(?SessionIdentity $identity): array
    {
        if ($identity === null) {
            return ['authenticated' => false];
        }

        return [
            'authenticated' => true,
            'user_id' => $identity->userId(),
            'role' => $identity->role()->value,
        ];
    }

    private function submittedToken(ServerRequest $request): string
    {
        return $this->stringBodyValue($request, 'csrf');
    }

    private function stringBodyValue(ServerRequest $request, string $key): string
    {
        $value = $request->body()[$key] ?? '';

        return is_string($value) ? $value : '';
    }

    private function csrfFailure(ServerRequest $request): ?JsonResponse
    {
        try {
            $this->csrf->assertValid($this->submittedToken($request));
        } catch (InvalidArgumentException $exception) {
            return new JsonResponse(419, [
                'ok' => false,
                'errors' => [$exception->getMessage()],
            ]);
        }

        return null;
    }

    private function loginResult(AuthenticationResult $result, string $prefix): JsonResponse
    {
        if (!$result->successful()) {
            if ($result->registrationRole() !== null) {
                return new JsonResponse(422, [
                    'ok' => false,
                    'data' => [
                        'registration_required' => true,
                        'role' => $result->registrationRole()->value,
                    ],
                    'errors' => [],
                ]);
            }

            $errors = $result->message() === '' ? ['Registration required.'] : [$result->message()];

            return new JsonResponse(422, ['ok' => false, 'errors' => $errors]);
        }

        return new JsonResponse(200, [
            'ok' => true,
            'data' => ['redirect' => $prefix . $result->redirectPath()],
        ]);
    }
}
