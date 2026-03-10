<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Embeddings\Contracts;

use Cognesy\Http\Contracts\CanSendHttpRequests;
use Cognesy\Polyglot\Embeddings\Config\EmbeddingsConfig;
use Psr\EventDispatcher\EventDispatcherInterface;

interface CanProvideEmbeddingsDrivers
{
    public function has(string $name): bool;

    /** @return array<string> */
    public function driverNames(): array;

    public function makeDriver(
        string $name,
        EmbeddingsConfig $config,
        CanSendHttpRequests $httpClient,
        EventDispatcherInterface $events,
    ): CanHandleVectorization;
}
