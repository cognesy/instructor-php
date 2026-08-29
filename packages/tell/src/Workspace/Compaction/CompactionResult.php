<?php

declare(strict_types=1);

namespace Cognesy\Tell\Workspace\Compaction;

use Cognesy\Tell\Workspace\Arena\ObjectHash;

/**
 * The durable outcome of explicitly replacing one canonical history with a summary.
 */
final readonly class CompactionResult
{
    public function __construct(
        public ObjectHash $sourceHead,
        public ObjectHash $head,
        public int $beforeMessageCount,
        public int $beforeTurnCount,
        public int $afterMessageCount,
        public int $afterTurnCount,
    ) {}

    /** @return array<string, array<string, int>|string|bool> */
    public function toArray(): array {
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
