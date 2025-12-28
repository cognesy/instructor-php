<?php declare(strict_types=1);

namespace Cognesy\Auxiliary\Agents\Unified\Dto;

use Cognesy\Auxiliary\Agents\Unified\Contract\StreamHandler;

/**
 * Stream handler that delegates to callable callbacks.
 */
final class CallbackStreamHandler implements StreamHandler
{
    /** @var callable(string): void|null */
    private $textHandler;

    /** @var callable(ToolCall): void|null */
    private $toolUseHandler;

    /** @var callable(UnifiedResponse): void|null */
    private $completeHandler;

    public function __construct(
        ?callable $onText = null,
        ?callable $onToolUse = null,
        ?callable $onComplete = null,
    ) {
        $this->textHandler = $onText;
        $this->toolUseHandler = $onToolUse;
        $this->completeHandler = $onComplete;
    }

    #[\Override]
    public function onText(string $text): void
    {
        if ($this->textHandler !== null) {
            ($this->textHandler)($text);
        }
    }

    #[\Override]
    public function onToolUse(ToolCall $toolCall): void
    {
        if ($this->toolUseHandler !== null) {
            ($this->toolUseHandler)($toolCall);
        }
    }

    #[\Override]
    public function onComplete(UnifiedResponse $response): void
    {
        if ($this->completeHandler !== null) {
            ($this->completeHandler)($response);
        }
    }

    public function hasTextHandler(): bool
    {
        return $this->textHandler !== null;
    }

    public function hasToolUseHandler(): bool
    {
        return $this->toolUseHandler !== null;
    }

    public function hasCompleteHandler(): bool
    {
        return $this->completeHandler !== null;
    }

    public function hasAnyHandler(): bool
    {
        return $this->hasTextHandler() || $this->hasToolUseHandler() || $this->hasCompleteHandler();
    }
}
