<?php

declare(strict_types=1);

namespace Cognesy\Polyglot\Decision\Events;

final class DecisionStarted extends DecisionEvent
{
    /** @param array<string,mixed> $data */
    public function __construct(
        public readonly string $executionId,
        public readonly string $requestId,
        public readonly string $model,
        public readonly string $driver,
        public readonly int $primitiveCount,
        array $data = [],
    ) {
        parent::__construct([...$data, ...$this->fields()]);
    }

    private function fields(): array
    {
        return [
            'executionId' => $this->executionId,
            'requestId' => $this->requestId,
            'model' => $this->model,
            'driver' => $this->driver,
            'primitiveCount' => $this->primitiveCount,
        ];
    }
}
