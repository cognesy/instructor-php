<?php declare(strict_types=1);

namespace Cognesy\Agents\Tool\Traits;

use Cognesy\Polyglot\Inference\Data\ToolCall;

trait HasToolCall
{
    protected ?ToolCall $toolCall = null;

    #[\Override]
    public function withToolCall(ToolCall $toolCall): static {
        $clone = clone $this;
        $clone->toolCall = $toolCall;
        return $clone;
    }
}
