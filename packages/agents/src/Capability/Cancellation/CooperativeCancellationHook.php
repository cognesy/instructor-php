<?php declare(strict_types=1);

namespace Cognesy\Agents\Capability\Cancellation;

use Cognesy\Agents\Hook\Contracts\HookInterface;
use Cognesy\Agents\Hook\Data\HookContext;
use Override;

final readonly class CooperativeCancellationHook implements HookInterface
{
    public function __construct(
        private CanProvideCancellationSignal $source,
    ) {}

    #[Override]
    public function handle(HookContext $context): HookContext {
        if ($context->state()->executionContinuation()?->stopSignal() !== null) {
            return $context;
        }

        $signal = $this->source->cancellationSignal($context->state());

        return match ($signal) {
            null => $context,
            default => $context->withState($context->state()->withStopSignal($signal)),
        };
    }
}
