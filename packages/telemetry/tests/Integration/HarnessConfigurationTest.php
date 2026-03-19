<?php declare(strict_types=1);

use Cognesy\Telemetry\Tests\Integration\Support\InteropEnv;

it('requires explicit opt-in before live backend interop tests run', function () {
    InteropEnv::requireInteropEnabled();

    expect(InteropEnv::isEnabled())->toBeTrue();
})->group('integration', 'interop');
