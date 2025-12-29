<?php declare(strict_types=1);

namespace Cognesy\AgentCtrl\Dto;

use Closure;
use Cognesy\AgentCtrl\Contract\StreamHandler;

/**
 * Stream handler that delegates to callable callbacks.
 */
final class CallbackStreamHandler implements StreamHandler
{
    /** @var (Closure(string): void)|null */
    private ?Closure $textHandler;

    /** @var (Closure(ToolCall): void)|null */
    private ?Closure $toolUseHandler;

    /** @var (Closure(AgentResponse): void)|null */
    private ?Closure $completeHandler;

    /**
     * @param (Closure(string): void)|null $onText
     * @param (Closure(ToolCall): void)|null $onToolUse
     * @param (Closure(AgentResponse): void)|null $onComplete
     */
    public function __construct(
        ?Closure $onText = null,
        ?Closure $onToolUse = null,
        ?Closure $onComplete = null,
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
    public function onComplete(AgentResponse $response): void
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
