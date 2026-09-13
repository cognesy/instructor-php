<?php

declare(strict_types=1);

namespace Cognesy\Tell\Core\Contract\Observation;

interface CanInspectTellRuns
{
    /**
     * @param array{status?: string, reason?: string, agent?: string, session?: string, branch?: string, workspace?: string, since?: string} $filters
     * @return list<array<string, mixed>>
     */
    public function list(array $filters, int $limit): array;

    /** @return array<string, mixed>|null */
    public function show(string $executionId, bool $includePayloads = false): ?array;
}
