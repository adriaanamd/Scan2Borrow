<?php

declare(strict_types=1);

namespace Tests\Unit\Profile;

use App\Application\Services\PhotoStorageInterface;
use App\Application\Services\ProfileChangeRequestService;
use App\Application\Validators\ProfileChangeRequestValidator;
use App\Infrastructure\Persistence\ProfileChangeNotificationInterface;
use App\Infrastructure\Persistence\ProfileChangeRequestRepositoryInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class ProfileChangeRequestServiceTest extends TestCase
{
    /** @var ProfileChangeRequestRepositoryInterface&MockObject */
    private ProfileChangeRequestRepositoryInterface $repository;

    /** @var ProfileChangeNotificationInterface&MockObject */
    private ProfileChangeNotificationInterface $notifications;

    /** @var PhotoStorageInterface&MockObject */
    private PhotoStorageInterface $photos;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(ProfileChangeRequestRepositoryInterface::class);
        $this->notifications = $this->createMock(ProfileChangeNotificationInterface::class);
        $this->photos = $this->createMock(PhotoStorageInterface::class);
    }

    public function testSubmitStoresOnlyChangedValuesAndStagesPhoto(): void
    {
        $this->repository->expects(self::once())->method('profile')->with(7)->willReturn($this->profile());
        $this->repository->expects(self::once())->method('create')->with(
            7,
            ['firstname' => 'Ada'],
            ['firstname' => 'Grace'],
            'uploads/ada.jpg',
            'uploads/request.jpg',
        )->willReturn(41);
        $this->photos->expects(self::once())->method('store')->with('data:image/jpeg;base64,abc', 'profile-request-7')->willReturn('uploads/request.jpg');
        $this->notifications->expects(self::once())->method('notifyAdministrators')->with(41, self::stringContains('Ada Lovelace'));

        $service = new ProfileChangeRequestService($this->repository, $this->notifications, new ProfileChangeRequestValidator(), $this->photos);
        $id = $service->submit(7, ['firstname' => 'Grace', 'lastname' => 'Lovelace', 'photo_data' => 'data:image/jpeg;base64,abc']);

        self::assertSame(41, $id);
    }

    public function testDecisionNotifiesBorrowerAfterRepositoryDecision(): void
    {
        $this->repository->expects(self::once())->method('decide')->with(41, 1, 'approve', 'Verified.')->willReturn([
            'id' => 41,
            'user_id' => 7,
            'status' => 'approved',
            'user_name' => 'Ada Lovelace',
            'requested_values' => ['firstname' => 'Grace'],
        ]);
        $this->notifications->expects(self::once())->method('notifyBorrower')->with(7, 41, 'Profile change approved', self::stringContains('approved'));

        $service = new ProfileChangeRequestService($this->repository, $this->notifications, new ProfileChangeRequestValidator(), $this->photos);
        $result = $service->decide(41, 1, 'approve', 'Verified.');

        self::assertSame('approved', $result['status']);
    }

    /** @return array<string, mixed> */
    private function profile(): array
    {
        return [
            'id' => 7, 'barcode' => 'STU-7', 'firstname' => 'Ada', 'middlename' => '', 'lastname' => 'Lovelace',
            'email' => 'ada@example.test', 'contact_no' => '0917', 'course' => 'Math', 'year_level' => '4',
            'department' => 'Science', 'position' => 'Student', 'photo' => 'uploads/ada.jpg', 'role' => 'student', 'status' => 'active',
        ];
    }
}
