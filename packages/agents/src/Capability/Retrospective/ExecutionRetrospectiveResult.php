<?php declare(strict_types=1);

namespace Cognesy\Agents\Capability\Retrospective;

final readonly class ExecutionRetrospectiveResult
{
    public function __construct(
        public int $checkpointId,
        public string $guidance,
    ) {}
}
