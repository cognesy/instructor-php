<?php declare(strict_types=1);

namespace Cognesy\Agents\Tests\Unit\Agent;

use Cognesy\Agents\Serialization\SlimSerializationConfig;

it('creates minimal serialization config', function () {
    $config = SlimSerializationConfig::minimal();

    expect($config->maxMessages)->toBe(20);
    expect($config->maxSteps)->toBe(0);
    expect($config->maxContentLength)->toBe(500);
    expect($config->includeToolResults)->toBeFalse();
    expect($config->includeSteps)->toBeFalse();
    expect($config->includeContinuationTrace)->toBeFalse();
    expect($config->redactToolArgs)->toBeFalse();
});

it('creates standard serialization config', function () {
    $config = SlimSerializationConfig::standard();

    expect($config->maxMessages)->toBe(50);
    expect($config->maxSteps)->toBe(20);
    expect($config->maxContentLength)->toBe(2000);
    expect($config->includeToolResults)->toBeTrue();
    expect($config->includeSteps)->toBeTrue();
    expect($config->includeContinuationTrace)->toBeFalse();
    expect($config->redactToolArgs)->toBeFalse();
});

it('creates full serialization config', function () {
    $config = SlimSerializationConfig::full();

    expect($config->maxMessages)->toBe(100);
    expect($config->maxSteps)->toBe(50);
    expect($config->maxContentLength)->toBe(5000);
    expect($config->includeToolResults)->toBeTrue();
    expect($config->includeSteps)->toBeTrue();
    expect($config->includeContinuationTrace)->toBeTrue();
    expect($config->redactToolArgs)->toBeFalse();
});
