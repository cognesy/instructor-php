<?php

declare(strict_types=1);

namespace Cognesy\Tell\Runtime;

use Cognesy\Agents\Capability\Cancellation\CanProvideCancellationSignal;
use Cognesy\Agents\Continuation\StopSignal;
use Cognesy\Agents\Data\AgentState;
use Override;

/** Tell-owned, optional SIGINT bridge for the public cooperative cancellation hook. */
final class TellSignalCancellationSource implements CanProvideCancellationSignal
{
    private ?StopSignal $signal = null;

    public static function isSupported(): bool {
        return function_exists('pcntl_signal') && function_exists('pcntl_async_signals') && defined('SIGINT');
    }

    public function install(): bool {
        if (!self::isSupported()) {
            return false;
        }
        pcntl_async_signals(true);
        pcntl_signal(SIGINT, function (): void {
            $this->cancel('SIGINT received');
        });

        return true;
    }

    public function cancel(string $message = 'Cancellation requested'): void {
        $this->signal ??= StopSignal::userRequested($message, source: self::class);
    }

    public function isCancelled(): bool {
        return $this->signal !== null;
    }

    #[Override]
    public function cancellationSignal(AgentState $state): ?StopSignal {
        return $this->signal;
    }
}
