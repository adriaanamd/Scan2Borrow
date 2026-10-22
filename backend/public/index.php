<?php

declare(strict_types=1);

require dirname(__DIR__) . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';

\App\Support\Runtime::assertSupported(PHP_VERSION);
\App\Bootstrap\ApplicationFactory::create()->run();
