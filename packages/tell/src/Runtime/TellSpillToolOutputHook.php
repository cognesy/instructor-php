<?php

declare(strict_types=1);

namespace Cognesy\Tell\Runtime;

use Cognesy\Agents\Data\ToolExecution;
use Cognesy\Agents\Hook\Contracts\HookInterface;
use Cognesy\Agents\Hook\Data\HookContext;
use Cognesy\Agents\Hook\Enums\HookTrigger;
use Cognesy\Utils\Result\Result;
use Override;

/**
 * Spills an oversized tool result to a blob before anything else sees it.
 *
 * Registered above the execution-budget hook on purpose: the budget hook's job
 * is to cut a result down to `maxToolOutputChars`, and it cannot be allowed to
 * do that first, or the bytes worth keeping would already be gone by the time
 * this hook looked at them.
 */
final class TellSpillToolOutputHook implements HookInterface
{
    /**
     * Set on the context when this hook has replaced a result, so the budget
     * hook further down the stack knows the value it is looking at is already
     * a stub and leaves it whole.
     */
    public const string SPILLED = 'tell.toolOutputSpilled';

    public function __construct(private readonly ToolOutputSpill $spill) {}

    #[Override]
    public function handle(HookContext $context): HookContext
    {
        if ($context->triggerType() !== HookTrigger::AfterToolUse) {
            return $context;
        }
        $execution = $context->toolExecution();
        if ($execution === null || ! $execution->result()->isSuccess()) {
            return $context;
        }
        $replacement = $this->spill->replace($execution->value());
        if ($replacement === null) {
            return $context;
        }

        return $context->withToolExecution(new ToolExecution(
            toolCall: $execution->toolCall(),
            result: Result::success($replacement),
            startedAt: $execution->startedAt(),
            completedAt: $execution->completedAt(),
            id: $execution->id(),
        ))->withMetadataEntry(self::SPILLED, true);
    }
}
