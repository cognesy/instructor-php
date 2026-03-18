<?php declare(strict_types=1);

use Cognesy\Telemetry\Application\Projector\Support\EventData;

it('converts boolean values to strings when requested as strings', function () {
    expect(EventData::string(['isRetry' => true], 'isRetry'))->toBe('true')
        ->and(EventData::string(['isRetry' => false], 'isRetry'))->toBe('false');
});
