<?php declare(strict_types=1);

namespace Tests\Addons\Unit\Tools;

use Cognesy\Addons\StepByStep\Continuation\ContinuationCriteria;
use Cognesy\Addons\StepByStep\Continuation\ContinuationDecision;
use Cognesy\Addons\StepByStep\Continuation\StopReason;
use Cognesy\Addons\StepByStep\StateProcessing\StateProcessors;
use Cognesy\Addons\ToolUse\Collections\Tools;
use Cognesy\Addons\ToolUse\Contracts\CanExecuteToolCalls;
use Cognesy\Addons\ToolUse\Contracts\CanUseTools;
use Cognesy\Addons\ToolUse\Data\ToolUseState;
use Cognesy\Addons\ToolUse\Data\ToolUseStep;
use Cognesy\Addons\ToolUse\Enums\ToolUseStatus;
use Cognesy\Addons\ToolUse\ToolExecutor;
use Cognesy\Addons\ToolUse\ToolUse;
use Cognesy\Messages\Messages;

describe('ToolUse continuation evaluation failures', function () {
    it('records a failure outcome when continuation evaluation throws', function () {
        $driver = new class implements CanUseTools {
            public function useTools(ToolUseState $state, Tools $tools, CanExecuteToolCalls $executor): ToolUseStep {
                return new ToolUseStep();
            }
        };

        $criterion = ContinuationCriteria::when(
            static function (ToolUseState $state): ContinuationDecision {
                throw new \RuntimeException('criteria boom');
            }
        );
        $continuationCriteria = new ContinuationCriteria($criterion);

        $tools = new Tools();
        $toolUse = new ToolUse(
            tools: $tools,
            toolExecutor: new ToolExecutor($tools),
            processors: new StateProcessors(),
            continuationCriteria: $continuationCriteria,
            driver: $driver,
            events: null,
        );

        $state = (new ToolUseState())->withMessages(Messages::fromString('ping'));
        $failedState = $toolUse->nextStep($state);
        $outcome = $failedState->continuationOutcome();

        expect($failedState->status())->toBe(ToolUseStatus::Failed);
        expect($failedState->stepCount())->toBe(1);
        expect($failedState->stepResults())->toHaveCount(1);
        expect($failedState->currentStep()?->errorsAsString())->toContain('criteria boom');
        expect($outcome)->not->toBeNull();
        expect($outcome?->stopReason())->toBe(StopReason::ErrorForbade);
        expect($outcome?->shouldContinue())->toBeFalse();
        expect($toolUse->hasNextStep($failedState))->toBeFalse();
    });
});
