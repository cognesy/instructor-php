<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/Pest.php';

use Cognesy\Agents\Exceptions\AgentException;
use Cognesy\Http\Exceptions\TimeoutException;
use Cognesy\Polyglot\Inference\Exceptions\ProviderTransientException;
use Cognesy\Tell\Data\TellExecutionError;

it('classifies provider and transport failures without exposing their messages', function (Throwable $cause, string $code, string $category): void {
    $wrapped = new AgentException('agent wrapper secret', previous: $cause);
    $error = TellExecutionError::fromThrowable($wrapped);
    $serialized = json_encode($error->toArray(), JSON_THROW_ON_ERROR);

    expect($error->code)->toBe($code)
        ->and($error->category)->toBe($category)
        ->and($error->phase)->toBe('inference')
        ->and($error->cause())->toBe($wrapped)
        ->and($serialized)->not->toContain('provider detail secret')
        ->not->toContain('transport detail secret')
        ->not->toContain('agent wrapper secret');
})->with([
    'provider transient' => [
        new ProviderTransientException('provider detail secret'),
        'provider_transient_failure',
        'provider',
    ],
    'transport timeout' => [
        new TimeoutException('transport detail secret'),
        'transport_timeout',
        'transport',
    ],
]);
