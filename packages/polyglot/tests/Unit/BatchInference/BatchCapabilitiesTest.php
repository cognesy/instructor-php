<?php

declare(strict_types=1);

use Cognesy\Polyglot\BatchInference\BatchInference;
use Cognesy\Polyglot\BatchInference\Config\BatchConfig;
use Cognesy\Polyglot\BatchInference\Data\BatchCapabilities;
use Cognesy\Polyglot\BatchInference\Data\BatchInputSupport;
use Cognesy\Polyglot\BatchInference\Enums\BatchInputKind;
use Cognesy\Polyglot\Inference\Config\LLMConfig;

it('exposes file and inline admission limits before Gemini submission', function () {
    $config = BatchConfig::fromLLMConfig(new LLMConfig(
        apiUrl: 'https://generativelanguage.googleapis.com/v1beta',
        endpoint: '/models/{model}:generateContent',
        model: 'gemini-2.5-flash-lite',
        driver: 'gemini',
    ));
    $batches = BatchInference::fromConfig($config);
    $caps = $batches->capabilities();

    expect($caps->canCancel())->toBeTrue()
        ->and($caps->canReadResults())->toBeTrue()
        ->and($caps->inputMode(BatchInputKind::File)?->enforcedMaxBytes())->toBe(2000000000)
        ->and($caps->inputMode(BatchInputKind::Inline)?->enforcedMaxBytes())->toBe(20000000)
        ->and($caps->inputMode(BatchInputKind::Inline)?->enforcedMaxItems())->toBe(100000);
});

it('reports xAI partial-result support and validates input-mode descriptors', function () {
    $config = BatchConfig::fromLLMConfig(new LLMConfig(
        apiUrl: 'https://api.x.ai/v1',
        endpoint: '/chat/completions',
        model: 'grok-4.3',
        driver: 'xai',
    ));
    $caps = BatchInference::fromConfig($config)->capabilities();

    expect($caps->supportsPartialResults())->toBeTrue()
        ->and($caps->inputMode(BatchInputKind::File)?->enforcedMaxItems())->toBe(50000)
        ->and($caps->inputMode(BatchInputKind::Inline))->toBeNull()
        ->and(fn () => new BatchInputSupport(BatchInputKind::File, enforcedMaxBytes: 0))->toThrow(InvalidArgumentException::class)
        ->and(fn () => new BatchCapabilities(false, false, false, [
            new BatchInputSupport(BatchInputKind::File),
            new BatchInputSupport(BatchInputKind::File),
        ]))->toThrow(InvalidArgumentException::class);
});
