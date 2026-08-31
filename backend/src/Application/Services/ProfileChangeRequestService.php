<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Application\Validators\ProfileChangeRequestValidator;
use App\Infrastructure\Persistence\ProfileChangeNotificationInterface;
use App\Infrastructure\Persistence\ProfileChangeRequestRepositoryInterface;
use App\Domain\Profile\ProfileFieldPolicy;
use InvalidArgumentException;
use RuntimeException;

final class ProfileChangeRequestService
{
    public function __construct(
        private readonly ProfileChangeRequestRepositoryInterface $repository,
        private readonly ProfileChangeNotificationInterface $notifications,
        private readonly ProfileChangeRequestValidator $validator,
        private readonly PhotoStorageInterface $photoStorage,
    ) {
    }

    /** @return array<string, mixed> */
    public function show(int $userId): array
    {
        $profile = $this->repository->profile($userId);
        if ($profile === null) {
            throw new RuntimeException('Profile not found.');
        }

        return [
            'profile' => $profile,
            'requestable_fields' => array_keys(ProfileFieldPolicy::requestable()),
            'pending_request' => $this->repository->pendingForUser($userId),
        ];
    }

    /** @param array<string, mixed> $input */
    public function submit(int $userId, array $input): int
    {
        $profile = $this->repository->profile($userId);
        if ($profile === null || !in_array((string) ($profile['role'] ?? ''), ['student', 'teacher'], true)) {
            throw new RuntimeException('Only student and teacher profiles can be changed here.');
        }

        $current = [];
        foreach (array_keys(ProfileFieldPolicy::requestable()) as $field) {
            if ($field !== 'photo') {
                $current[$field] = is_string($profile[$field] ?? null) ? $profile[$field] : '';
            }
        }
        $textChanged = $this->validateTextChanges($input, $current);
        $requestedPhoto = null;
        $photoData = $input['photo_data'] ?? null;
        if (is_string($photoData) && trim($photoData) !== '') {
            $requestedPhoto = $this->photoStorage->store(trim($photoData), 'profile-request-' . $userId);
            if ($requestedPhoto === null) {
                throw new InvalidArgumentException('Please choose a valid JPG or PNG photo.');
            }
        }
        if ($textChanged === [] && $requestedPhoto === null) {
            throw new InvalidArgumentException('Make at least one profile change before submitting.');
        }

        $originalValues = [];
        foreach (array_keys($textChanged) as $field) {
            $originalValues[$field] = $current[$field];
        }
        $requestId = $this->repository->create(
            $userId,
            $originalValues,
            $textChanged,
            $this->nullableString($profile['photo'] ?? null),
            $requestedPhoto,
        );
        try {
            $name = trim((string) ($profile['firstname'] ?? '') . ' ' . (string) ($profile['lastname'] ?? ''));
            $this->notifications->notifyAdministrators($requestId, $name . ' submitted a profile change request.');
        } catch (\Throwable) {
            // The persisted request is authoritative when notification storage is unavailable.
        }

        return $requestId;
    }

    /** @return array<string, mixed> */
    public function decide(int $requestId, int $reviewerId, string $decision, string $reviewNote): array
    {
        $note = $this->validator->validateReview($decision, $reviewNote);
        $result = $this->repository->decide($requestId, $reviewerId, $decision, $note);
        if ($result === null) {
            throw new RuntimeException('This profile change request was already reviewed or no longer exists.');
        }

        try {
            $userId = is_numeric($result['user_id'] ?? null) ? (int) $result['user_id'] : 0;
            $title = $decision === 'approve' ? 'Profile change approved' : 'Profile change rejected';
            $message = $decision === 'approve'
                ? 'Your requested profile changes were approved.'
                : 'Your requested profile changes were rejected.';
            if ($note !== '') {
                $message .= ' Admin note: ' . $note;
            }
            $this->notifications->notifyBorrower($userId, $requestId, $title, $message);
        } catch (\Throwable) {
            // The decision has already been committed and remains authoritative.
        }

        return $result;
    }

    /** @param array<string, mixed> $input @param array<string, string> $current @return array<string, string> */
    private function validateTextChanges(array $input, array $current): array
    {
        $textInput = array_filter(
            $input,
            static fn (mixed $value, string|int $key): bool => is_string($key) && !in_array($key, ['csrf', 'photo_data'], true),
            ARRAY_FILTER_USE_BOTH,
        );
        if ($textInput === []) {
            return [];
        }

        try {
            return $this->validator->validate($textInput, $current);
        } catch (InvalidArgumentException $exception) {
            if ($exception->getMessage() === 'Make at least one profile change before submitting.') {
                return [];
            }
            throw $exception;
        }
    }

    private function nullableString(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }
}
