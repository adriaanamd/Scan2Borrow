<?php

declare(strict_types=1);

use App\Application\Services\HoldExpiryService;
use App\Application\Services\ReservationAvailabilityService;
use App\Application\Services\SystemClock;
use App\Infrastructure\Database\DatabaseConfig;
use App\Infrastructure\Database\PdoConnectionFactory;
use App\Infrastructure\Persistence\PdoCirculationNotificationRepository;
use App\Infrastructure\Persistence\PdoHoldRepository;
use App\Infrastructure\Persistence\PdoReservationCopyRepository;

require dirname(__DIR__) . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';

$pdo = (new PdoConnectionFactory())->create(DatabaseConfig::fromEnvironment());
$holds = new PdoHoldRepository($pdo);
$notifications = new PdoCirculationNotificationRepository($pdo);
$count = (new HoldExpiryService(
    $holds,
    new PdoReservationCopyRepository($pdo),
    new ReservationAvailabilityService($holds, $notifications),
    new SystemClock(),
))->run();

fwrite(STDOUT, sprintf("Expired %d reservation offer(s).%s", $count, PHP_EOL));
