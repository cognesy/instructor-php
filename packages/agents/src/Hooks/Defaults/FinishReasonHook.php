<?php declare(strict_types=1);

namespace Cognesy\Agents\Hooks\Defaults;

use Closure;
use Cognesy\Agents\Core\Stop\StopReason;
use Cognesy\Agents\Core\Stop\StopSignal;
use Cognesy\Agents\Hooks\HookContext;
use Cognesy\Agents\Hooks\HookInterface;
use Cognesy\Polyglot\Inference\Enums\InferenceFinishReason;

final readonly class FinishReasonHook implements HookInterface
{
    /** @var Closure(mixed): ?InferenceFinishReason */
    private Closure $finishReasonResolver;
    /** @var list<InferenceFinishReason> */
    private array $stopReasons;

    /**
     * @param list<InferenceFinishReason> $stopReasons
     * @param callable(mixed): ?InferenceFinishReason $finishReasonResolver
     */
    public function __construct(array $stopReasons, callable $finishReasonResolver)
    {
        $this->stopReasons = array_values($stopReasons);
        $this->finishReasonResolver = Closure::fromCallable($finishReasonResolver);
    }

    #[\Override]
    public function handle(HookContext $context): HookContext
    {
        if ($this->stopReasons === []) {
            return $context;
        }

        $state = $context->state();
        $reason = ($this->finishReasonResolver)($state);
        if ($reason === null) {
            return $context;
        }

        if (!in_array($reason, $this->stopReasons, true)) {
            return $context;
        }

        $reasonText = sprintf('Finish reason "%s" matched stop condition', $reason->value);

        return $context->withState($state->withStopSignal(new StopSignal(
            reason: StopReason::FinishReasonReceived,
            message: $reasonText,
            context: [
                'finishReason' => $reason->value,
                'stopReasons' => $this->stopReasonsAsStrings(),
            ],
            source: self::class,
        )));
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
