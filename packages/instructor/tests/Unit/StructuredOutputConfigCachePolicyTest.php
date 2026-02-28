<?php declare(strict_types=1);

use Cognesy\Instructor\Config\StructuredOutputConfig;
use Cognesy\Polyglot\Inference\Enums\ResponseCachePolicy;

it('defaults response cache policy to none', function () {
    $config = new StructuredOutputConfig();

    expect($config->responseCachePolicy())->toBe(ResponseCachePolicy::None);
});

it('uses none when response cache policy is omitted in fromArray', function () {
    $config = StructuredOutputConfig::fromArray([]);

    expect($config->responseCachePolicy())->toBe(ResponseCachePolicy::None);
});

