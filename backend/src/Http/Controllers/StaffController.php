<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Application\Services\CsrfService;
use App\Application\Services\GuestApprovalService;
use App\Application\Services\BorrowerNotificationService;
use App\Application\Services\PhotoStorageInterface;
use App\Application\Services\SessionService;
use App\Domain\Auth\Role;
use App\Http\Requests\ServerRequest;
use App\Http\Responses\JsonResponse;
use App\Http\Responses\ResponseInterface;
use App\Infrastructure\Persistence\StaffRepositoryInterface;
use InvalidArgumentException;

final readonly class StaffController
{
    public function __construct(
        private SessionService $sessions,
        private StaffRepositoryInterface $staff,
        private CsrfService $csrf,
        private ?GuestApprovalService $guestApproval = null,
        private ?PhotoStorageInterface $photoStorage = null,
        private ?BorrowerNotificationService $notifications = null,
    ) {
    }

    public function dashboard(ServerRequest $request): JsonResponse
    {
        return $this->staffDataResponse(fn (): array => $this->staff->dashboard());
    }

    public function borrowers(ServerRequest $request): JsonResponse
    {
        return $this->staffDataResponse(fn (): array => ['borrowers' => $this->staff->borrowers($this->queryString($request, 'search'))]);
    }

    public function borrowerDetails(ServerRequest $request): JsonResponse
    {
        if (!$this->isStaff()) {
            return $this->unauthorized();
        }
        $id = $this->positiveInt($request->query()['id'] ?? null);
        $details = $id > 0 ? $this->staff->borrowerDetails($id) : null;
        if ($details === null) {
            return new JsonResponse(404, ['ok' => false, 'errors' => ['Borrower not found.']]);
        }

        $details['can_edit_photo'] = $this->isAdmin();

        return new JsonResponse(200, ['ok' => true, 'data' => $details]);
    }

    public function updateBorrowerPhoto(ServerRequest $request): JsonResponse
    {
        if (!$this->isAdmin()) {
            return $this->unauthorized();
        }
        $csrfFailure = $this->csrfFailure($request);
        if ($csrfFailure !== null) {
            return $csrfFailure;
        }
        $id = $this->positiveInt($request->body()['user_id'] ?? null);
        $photo = $this->bodyString($request, 'photo_data');
        if ($id < 1 || $photo === '' || $this->photoStorage === null) {
            return new JsonResponse(422, ['ok' => false, 'message' => 'Please choose a valid image file.']);
        }
        $path = $this->photoStorage->store($photo, 'borrower-' . $id);
        if ($path === null) {
            return new JsonResponse(422, ['ok' => false, 'message' => 'Please choose a valid image file (JPG, PNG or WEBP, max 4 MB).']);
        }
        $this->staff->updateBorrowerPhoto($id, $path);

        return new JsonResponse(200, ['ok' => true, 'message' => 'ID photo updated.', 'photo' => $path]);
    }

    public function notifyBorrower(ServerRequest $request): JsonResponse
    {
        if (!$this->isStaff() || $this->notifications === null) {
            return $this->unauthorized();
        }
        $csrfFailure = $this->csrfFailure($request);
        if ($csrfFailure !== null) {
            return $csrfFailure;
        }
        $result = $this->notifications->send(
            $this->positiveInt($request->body()['user_id'] ?? null),
            $this->bodyString($request, 'channel'),
        );

        return new JsonResponse($result['ok'] ? 200 : 422, $result['ok']
            ? ['ok' => true, 'message' => $result['message']]
            : ['ok' => false, 'message' => $result['message']]);
    }

    public function overdue(ServerRequest $request): JsonResponse
    {
        $rows = $this->staff->overdue();
        $total = 0.0;
        foreach ($rows as $row) {
            $value = $row['fine_amount'] ?? 0;
            $total += is_numeric($value) ? (float) $value : 0.0;
        }

        return $this->staffDataResponse(fn (): array => ['overdue' => $rows, 'total_fine' => $total]);
    }

    public function report(ServerRequest $request): JsonResponse
    {
        return $this->staffDataResponse(fn (): array => ['report' => $this->staff->report(
            $this->queryString($request, 'type'),
            $this->queryString($request, 'from'),
            $this->queryString($request, 'to'),
        )]);
    }

    public function exportReport(ServerRequest $request): ResponseInterface
    {
        if (!$this->isStaff()) {
            return $this->unauthorized();
        }
        $report = $this->staff->report(
            $this->queryString($request, 'type'),
            $this->queryString($request, 'from'),
            $this->queryString($request, 'to'),
        );
        $lines = [];
        $headers = is_array($report['headers'] ?? null) ? $report['headers'] : [];
        $data = is_array($report['data'] ?? null) ? $report['data'] : [];
        $lines[] = $this->csvLine($headers);
        foreach ($data as $row) {
            if (is_array($row)) {
                $lines[] = $this->csvLine($row);
            }
        }

        return new class(implode("\r\n", $lines) . "\r\n") implements ResponseInterface {
            public function __construct(private readonly string $body)
            {
            }

            public function statusCode(): int
            {
                return 200;
            }

            /** @return array<string, string> */
            public function headers(): array
            {
                return [
                    'Content-Type' => 'text/csv; charset=utf-8',
                    'Content-Disposition' => 'attachment; filename="scan2borrow_report.csv"',
                ];
            }

            public function toString(): string
            {
                return $this->body;
            }
        };
    }

    public function guestRequests(ServerRequest $request): JsonResponse
    {
        return $this->staffDataResponse(fn (): array => ['requests' => $this->staff->guestRequests()]);
    }

    public function adminStaff(ServerRequest $request): JsonResponse
    {
        return $this->adminResponse([
            'staff' => $this->staff->staffAccounts(),
            'borrowers' => $this->staff->borrowerCandidates($this->queryString($request, 'bsearch')),
        ]);
    }

    public function notifications(ServerRequest $request): JsonResponse
    {
        $identity = $this->sessions->current();
        if (!$this->isStaff() || $identity === null) {
            return $this->unauthorized();
        }
        $type = $this->queryString($request, 'action');
        $rows = $this->staff->notifications($identity->userId(), $type);

        return new JsonResponse(200, ['success' => true, 'count' => count($rows), 'notifications' => $rows, 'data' => $rows]);
    }

    public function borrowingAction(ServerRequest $request): JsonResponse
    {
        if (!$this->isStaff()) {
            return $this->unauthorized();
        }
        $csrfFailure = $this->csrfFailure($request);
        if ($csrfFailure !== null) {
            return $csrfFailure;
        }
        $id = $this->positiveInt($request->body()['borrowing_id'] ?? null);
        $action = $this->bodyString($request, 'action');
        $identity = $this->sessions->current();
        if ($identity === null || $id < 1 || !in_array($action, ['approve', 'reject'], true)) {
            return new JsonResponse(422, ['ok' => false, 'message' => 'Invalid borrowing decision.']);
        }
        if ($action === 'approve') {
            $this->staff->approveBorrowing($id, $identity->userId());
            $message = 'Borrow request approved successfully.';
        } else {
            $this->staff->rejectBorrowing($id, $identity->userId());
            $message = 'Borrow request rejected.';
        }

        return new JsonResponse(200, ['ok' => true, 'message' => $message]);
    }

    public function guestAction(ServerRequest $request): JsonResponse
    {
        if (!$this->isStaff() || $this->guestApproval === null) {
            return $this->unauthorized();
        }
        $csrfFailure = $this->csrfFailure($request);
        if ($csrfFailure !== null) {
            return $csrfFailure;
        }
        $id = $this->positiveInt($request->body()['id'] ?? null);
        $action = $this->bodyString($request, 'action');
        $notes = $this->bodyString($request, 'notes');
        if ($id < 1 || !in_array($action, ['approve', 'reject'], true)) {
            return new JsonResponse(422, ['ok' => false, 'message' => 'Invalid guest request decision.']);
        }
        $result = $action === 'approve'
            ? $this->guestApproval->approve($id, $notes)
            : $this->guestApproval->reject($id, $notes);
        if (!$result->isSuccessful()) {
            return new JsonResponse(422, ['ok' => false, 'message' => $result->message()]);
        }

        return new JsonResponse(200, ['ok' => true, 'message' => $result->message()]);
    }

    public function adminAction(ServerRequest $request): JsonResponse
    {
        if (!$this->isAdmin()) {
            return $this->unauthorized();
        }
        $csrfFailure = $this->csrfFailure($request);
        if ($csrfFailure !== null) {
            return $csrfFailure;
        }
        $body = $request->body();
        $id = $this->positiveInt($body['user_id'] ?? null);
        $action = $this->bodyString($request, 'action');
        $current = $this->sessions->current();
        if ($current === null || $id < 1 || $id === $current->userId()) {
            return new JsonResponse(422, ['ok' => false, 'message' => 'You cannot change your own account here.']);
        }
        $password = $this->bodyString($request, 'password');
        if (in_array($action, ['promote', 'reset_password'], true) && strlen($password) < 6) {
            return new JsonResponse(422, ['ok' => false, 'message' => 'Password must be at least 6 characters.']);
        }
        if ($action === 'promote') {
            $this->staff->promote($id, $this->bodyString($request, 'role'), $password);
            $message = 'Account promoted. They can now use the Staff Login.';
        } elseif ($action === 'reset_password') {
            $this->staff->resetPassword($id, $password);
            $message = 'Password updated.';
        } elseif ($action === 'demote') {
            $this->staff->demote($id);
            $message = 'Account changed back to Borrower.';
        } elseif ($action === 'toggle_status') {
            $this->staff->toggleStatus($id);
            $message = 'Account status updated.';
        } else {
            return new JsonResponse(422, ['ok' => false, 'message' => 'Unknown staff action.']);
        }

        return new JsonResponse(200, ['ok' => true, 'message' => $message]);
    }

    public function markNotificationViewed(ServerRequest $request): JsonResponse
    {
        $identity = $this->sessions->current();
        if (!$this->isStaff() || $identity === null) {
            return $this->unauthorized();
        }
        $csrfFailure = $this->csrfFailure($request);
        if ($csrfFailure !== null) {
            return $csrfFailure;
        }
        $this->staff->markNotificationViewed(
            $this->positiveInt($request->body()['notification_id'] ?? null),
            $identity->userId(),
            $this->bodyString($request, 'notification_type'),
        );

        return new JsonResponse(200, ['ok' => true]);
    }

    private function isStaff(): bool
    {
        $identity = $this->sessions->current();

        return $identity !== null && in_array($identity->role(), [Role::ADMIN, Role::LIBRARIAN], true);
    }

    private function isAdmin(): bool
    {
        $identity = $this->sessions->current();

        return $identity !== null && $identity->role() === Role::ADMIN;
    }

    /** @param array<string, mixed> $data */
    /** @param callable(): array<string, mixed> $loader */
    private function staffDataResponse(callable $loader): JsonResponse
    {
        if (!$this->isStaff()) {
            return $this->unauthorized();
        }

        return new JsonResponse(200, ['ok' => true, 'data' => $loader()]);
    }

    /** @param array<string, mixed> $data */
    private function adminResponse(array $data): JsonResponse
    {
        return $this->isAdmin() ? new JsonResponse(200, ['ok' => true, 'data' => $data]) : $this->unauthorized();
    }

    private function unauthorized(): JsonResponse
    {
        return new JsonResponse(401, ['ok' => false, 'errors' => ['Staff authentication required.']]);
    }

    private function csrfFailure(ServerRequest $request): ?JsonResponse
    {
        try {
            $this->csrf->assertValid($this->bodyString($request, 'csrf'));
        } catch (InvalidArgumentException $exception) {
            return new JsonResponse(419, ['ok' => false, 'errors' => [$exception->getMessage()]]);
        }

        return null;
    }

    private function queryString(ServerRequest $request, string $key): string
    {
        $value = $request->query()[$key] ?? '';

        return is_string($value) ? trim($value) : '';
    }

    private function bodyString(ServerRequest $request, string $key): string
    {
        $value = $request->body()[$key] ?? '';

        return is_string($value) ? trim($value) : '';
    }

    private function positiveInt(mixed $value): int
    {
        return is_numeric($value) ? max(0, (int) $value) : 0;
    }

    /** @param array<int|string, mixed> $row */
    private function csvLine(array $row): string
    {
        $stream = fopen('php://temp', 'r+');
        if ($stream === false) {
            return '';
        }
        fputcsv($stream, array_map(static fn (mixed $value): string => is_scalar($value) ? (string) $value : '', $row));
        rewind($stream);
        $line = stream_get_contents($stream);
        fclose($stream);

        return $line === false ? '' : rtrim($line, "\r\n");
    }
}
