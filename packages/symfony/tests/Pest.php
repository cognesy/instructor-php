<?php

declare(strict_types=1);

require_once __DIR__.'/Support/SymfonyTestApp.php';
require_once __DIR__.'/Support/TestKernel.php';
require_once __DIR__.'/Support/MockHttpClientFactory.php';
require_once __DIR__.'/Support/SymfonyTestServiceRegistry.php';
require_once __DIR__.'/Support/SymfonyTestLogger.php';

use Cognesy\Instructor\Symfony\Tests\Support\SymfonyTestApp;

afterEach(static fn (): bool => SymfonyTestApp::shutdownAll());
