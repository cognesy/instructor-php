<?php declare(strict_types=1);

namespace Cognesy\Experimental\RLM;

use Cognesy\Addons\StepByStep\Continuation\ContinuationCriteria;
use Cognesy\Addons\StepByStep\Continuation\Criteria\ExecutionTimeLimit;
use Cognesy\Addons\StepByStep\Continuation\Criteria\StepsLimit;
use Cognesy\Addons\StepByStep\Continuation\Criteria\TokenUsageLimit;
use Cognesy\Addons\StepByStep\State\Contracts\HasSteps;
use Cognesy\Addons\StepByStep\State\Contracts\HasUsage;
use Cognesy\Addons\StepByStep\StateProcessing\Processors\AccumulateTokenUsage;
use Cognesy\Addons\StepByStep\StateProcessing\Processors\AppendStepMessages;
use Cognesy\Addons\StepByStep\StateProcessing\StateProcessors;
use Cognesy\Addons\ToolUse\Collections\Tools as ToolUseTools;
use Cognesy\Experimental\RLM\Continuation\StopOnFinalOrAwait;
use Cognesy\Experimental\RLM\Contracts\ReplEnvironment;
use Cognesy\Experimental\RLM\Contracts\Toolset;
use Cognesy\Experimental\RLM\Data\Policy;
use Cognesy\Experimental\RLM\Drivers\StrictRlmDriver;
use Cognesy\Experimental\RLM\Env\BasicReplEnvironment;
use Cognesy\Experimental\RLM\Env\ToolUseToolset;
use Cognesy\Experimental\RLM\State\RlmState;
use Cognesy\Messages\Messages;

final class RlmFactory
{
    public static function make(
        Policy $policy,
        ?Toolset $tools = null,
        ?ReplEnvironment $repl = null,
        ?StrictRlmDriver $driver = null,
    ): array {
        $tools = $tools ?? ToolUseToolset::fromTools(new ToolUseTools());
        $repl = $repl ?? new BasicReplEnvironment();
        $driver = $driver ?? new StrictRlmDriver();

        $processors = new StateProcessors(
            new AccumulateTokenUsage(),
            new AppendStepMessages(),
        );

        $criteria = new ContinuationCriteria(
            new StepsLimit($policy->maxSteps, fn(HasSteps $s) => $s->stepCount()),
            new TokenUsageLimit($policy->maxTokensIn + $policy->maxTokensOut, fn(HasUsage $s) => $s->usage()->total()),
            new ExecutionTimeLimit($policy->maxWallClockSec, fn(object $s) => method_exists($s, 'startedAt') ? $s->startedAt() : (new \DateTimeImmutable())),
            new StopOnFinalOrAwait(),
        );

        $process = new RlmProcess(
            driver: $driver,
            tools: $tools,
            repl: $repl,
            processors: $processors,
            criteria: $criteria,
        );

        $state = RlmState::start($policy)->withMessages(Messages::empty());
        return [$process, $state];
    }
}
