<?php

declare(strict_types=1);

namespace Cognesy\Polyglot\Decision\Contracts;

use Cognesy\Http\Contracts\CanSendHttpRequests;
use Cognesy\Polyglot\Decision\Config\DecisionConfig;
use Psr\EventDispatcher\EventDispatcherInterface;

interface CanProvideDecisionDrivers
{
    public function has(string $name): bool;

    /** @return list<string> */
    public function driverNames(): array;

    public function makeDriver(
        string $name,
        DecisionConfig $config,
        CanSendHttpRequests $httpClient,
        EventDispatcherInterface $events,
    ): CanProcessDecisionRequest;
}
