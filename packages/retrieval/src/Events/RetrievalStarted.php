<?php

declare(strict_types=1);

namespace Cognesy\Retrieval\Events;

final class RetrievalStarted extends RetrievalEvent
{
    /** @param array<string, mixed> $data */
    public function __construct(
        public readonly string $executionId,
        public readonly string $requestId,
        public readonly string $driver,
        public readonly string $queryType,
        public readonly int $limit,
        array $data = [],
    ) {
        parent::__construct([...$data, ...$this->fields()]);
    }

    /** @return array<string, int|string> */
    private function fields(): array
    {
        return [
            'executionId' => $this->executionId,
            'requestId' => $this->requestId,
            'driver' => $this->driver,
            'queryType' => $this->queryType,
            'limit' => $this->limit,
        ];
    }
}
