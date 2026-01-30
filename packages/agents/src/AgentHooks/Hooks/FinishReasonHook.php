<?php declare(strict_types=1);

namespace Cognesy\Agents\AgentHooks\Hooks;

use Cognesy\Agents\AgentHooks\Contracts\Hook;
use Cognesy\Agents\AgentHooks\Enums\HookType;
use Closure;
use Cognesy\Agents\Core\Continuation\Data\ContinuationEvaluation;
use Cognesy\Agents\Core\Continuation\Enums\ContinuationDecision;
use Cognesy\Agents\Core\Continuation\Enums\StopReason;
use Cognesy\Agents\Core\Data\AgentState;
use Cognesy\Polyglot\Inference\Enums\InferenceFinishReason;

/**
 * Hook adapter for finish reason continuation checks.
 */
final readonly class FinishReasonHook implements Hook
{
    /** @var Closure(AgentState): ?InferenceFinishReason */
    private Closure $finishReasonResolver;
    /** @var list<InferenceFinishReason> */
    private array $stopReasons;

    /**
     * @param list<InferenceFinishReason> $stopReasons
     * @param callable(AgentState): ?InferenceFinishReason $finishReasonResolver
     */
    public function __construct(array $stopReasons, callable $finishReasonResolver)
    {
        $this->stopReasons = array_values($stopReasons);
        $this->finishReasonResolver = Closure::fromCallable($finishReasonResolver);
    }

    #[\Override]
    public function appliesTo(): array
    {
        return [HookType::AfterStep];
    }

    #[\Override]
    public function process(AgentState $state, HookType $event): AgentState
    {
        if ($this->stopReasons === []) {
            return $state->withEvaluation(new ContinuationEvaluation(
                criterionClass: self::class,
                decision: ContinuationDecision::AllowContinuation,
                reason: 'No stop reasons configured',
                context: ['finishReason' => null, 'stopReasons' => []],
            ));
        }

        $reason = ($this->finishReasonResolver)($state);
        if ($reason === null) {
            return $state->withEvaluation(new ContinuationEvaluation(
                criterionClass: self::class,
                decision: ContinuationDecision::AllowContinuation,
                reason: 'No finish reason available',
                context: ['finishReason' => null, 'stopReasons' => $this->stopReasonsAsStrings()],
            ));
        }

        $shouldStop = in_array($reason, $this->stopReasons, true);
        $decision = $shouldStop
            ? ContinuationDecision::ForbidContinuation
            : ContinuationDecision::AllowContinuation;

        $reasonText = $shouldStop
            ? sprintf('Finish reason "%s" matched stop condition', $reason->value)
            : sprintf('Finish reason "%s" does not match stop conditions', $reason->value);

        $evaluation = new ContinuationEvaluation(
            criterionClass: self::class,
            decision: $decision,
            reason: $reasonText,
            context: [
                'finishReason' => $reason->value,
                'stopReasons' => $this->stopReasonsAsStrings(),
            ],
            stopReason: $shouldStop ? StopReason::FinishReasonReceived : null,
        );

        return $state->withEvaluation($evaluation);
    }

    /**
     * @return list<string>
     */
    private function stopReasonsAsStrings(): array
    {
        return array_map(
            static fn(InferenceFinishReason $reason): string => $reason->value,
            $this->stopReasons,
        );
    }
}
