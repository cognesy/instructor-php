<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Inference\Contracts;

use Cognesy\Events\Contracts\CanHandleEvents;
use Cognesy\Http\HttpClient;
use Cognesy\Polyglot\Inference\Config\LLMConfig;

interface CanProvideInferenceDrivers
{
    public function has(string $name): bool;

    /** @return array<string> */
    public function driverNames(): array;

    public function makeDriver(
        string $name,
        LLMConfig $config,
        HttpClient $httpClient,
        CanHandleEvents $events,
    ): CanProcessInferenceRequest;
}
