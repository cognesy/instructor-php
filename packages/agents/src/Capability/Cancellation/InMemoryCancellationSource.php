<?php declare(strict_types=1);

namespace Cognesy\Agents\Capability\Cancellation;

use Cognesy\Agents\Continuation\StopSignal;
use Cognesy\Agents\Data\AgentState;
use Override;

final class InMemoryCancellationSource implements CanProvideCancellationSignal
{
    private ?StopSignal $signal = null;

    public function cancel(string $message = 'Cancellation requested', array $context = []): void {
        $this->signal = StopSignal::userRequested(
            message: $message,
            context: $context,
            source: self::class,
        );
    }

    public function reset(): void {
        $this->signal = null;
    }

    public function isCancellationRequested(): bool {
        return $this->signal !== null;
    }

    #[Override]
    public function cancellationSignal(AgentState $state): ?StopSignal {
        return $this->signal;
    }
}
