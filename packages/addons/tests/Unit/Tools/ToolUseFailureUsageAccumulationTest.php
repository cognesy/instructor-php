<?php declare(strict_types=1);

namespace Tests\Addons\Unit\Tools;

use Cognesy\Addons\StepByStep\Continuation\ContinuationCriteria;
use Cognesy\Addons\StepByStep\StateProcessing\StateProcessors;
use Cognesy\Addons\ToolUse\Collections\Tools;
use Cognesy\Addons\ToolUse\Contracts\CanExecuteToolCalls;
use Cognesy\Addons\ToolUse\Contracts\CanUseTools;
use Cognesy\Addons\ToolUse\Data\ToolUseState;
use Cognesy\Addons\ToolUse\Data\ToolUseStep;
use Cognesy\Addons\ToolUse\ToolExecutor;
use Cognesy\Addons\ToolUse\ToolUse;
use Cognesy\Messages\Messages;
use Cognesy\Polyglot\Inference\Data\Usage;

final class CountingUsage extends Usage
{
    public function withAccumulated(Usage $usage): Usage {
        return new self(
            inputTokens: $this->inputTokens + 1,
            outputTokens: $this->outputTokens,
            cacheWriteTokens: $this->cacheWriteTokens,
            cacheReadTokens: $this->cacheReadTokens,
            reasoningTokens: $this->reasoningTokens,
        );
    }
}

describe('ToolUse failure usage accumulation', function () {
    it('accumulates usage when onFailure records a step result', function () {
        $driver = new class implements CanUseTools {
            public function useTools(ToolUseState $state, Tools $tools, CanExecuteToolCalls $executor): ToolUseStep {
                throw new \RuntimeException('driver boom');
            }
        };

        $tools = new Tools();
        $toolUse = new ToolUse(
            tools: $tools,
            toolExecutor: new ToolExecutor($tools),
            processors: new StateProcessors(),
            continuationCriteria: new ContinuationCriteria(),
            driver: $driver,
            events: null,
        );

        $state = (new ToolUseState())
            ->withMessages(Messages::fromString('ping'))
            ->withUsage(new CountingUsage());
        $failedState = $toolUse->nextStep($state);

        expect($failedState->usage()->input())->toBe(1);
    });
});
