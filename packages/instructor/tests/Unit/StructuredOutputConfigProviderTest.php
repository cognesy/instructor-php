<?php declare(strict_types=1);

use Cognesy\Instructor\Config\StructuredOutputConfig;
use Cognesy\Instructor\StructuredOutputRuntime;

it('fromDefaults applies explicit structured config defaults', function () {
    $runtime = StructuredOutputRuntime::fromDefaults(
        structuredConfig: (new StructuredOutputConfig())->withMaxRetries(7),
    );

    expect($runtime->config()->maxRetries())->toBe(7);
});
