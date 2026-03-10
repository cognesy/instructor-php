<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Inference\Data;

use Cognesy\Messages\ToolCallId;

final class ToolCallIdByStreamIndex
{
    /** @var array<string,ToolCallId> */
    private array $byIndex = [];

    public function remember(string $index, ToolCallId $toolCallId): void {
        $this->byIndex[$index] = $toolCallId;
    }

    public function forIndex(string $index): ?ToolCallId {
        return $this->byIndex[$index] ?? null;
    }
}

