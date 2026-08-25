<?php

declare(strict_types=1);

namespace Cognesy\Tell\Workspace;

use Cognesy\Tell\Canonical\CanonicalHash;

/**
 * The durable outcome of explicitly replacing one canonical history with a summary.
 */
final readonly class WorkspaceCompactionResult
{
    public function __construct(
        public CanonicalHash $sourceHead,
        public CanonicalHash $head,
        public int $beforeMessageCount,
        public int $beforeTurnCount,
        public int $afterMessageCount,
        public int $afterTurnCount,
    ) {}

    /** @return array<string, array<string, int>|string|bool> */
    public function toArray(): array
    {
        return [
            'sourceHead' => $this->sourceHead->toString(),
            'head' => $this->head->toString(),
            'changed' => true,
            'before' => [
                'messageCount' => $this->beforeMessageCount,
                'turnCount' => $this->beforeTurnCount,
            ],
            'after' => [
                'messageCount' => $this->afterMessageCount,
                'turnCount' => $this->afterTurnCount,
            ],
        ];
    }
}
