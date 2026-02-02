<?php declare(strict_types=1);

namespace Cognesy\Agents\Core\Stop;

use Cognesy\Agents\Core\Data\AgentStep;
use RuntimeException;
use Throwable;

/**
 * Control-flow exception to stop the loop with a StopSignal.
 */
final class AgentStopException extends RuntimeException
{
    public function __construct(
        public readonly StopSignal $signal,
        public readonly ?AgentStep $step = null,
        public array               $context = [],
        public ?string             $source = null,
        string                     $message = '',
        ?Throwable                 $previous = null,
    ) {
        parent::__construct(self::resolveMessage($signal, $message), 0, $previous);
    }

    private static function resolveMessage(StopSignal $signal, string $message): string {
        return match (true) {
            $message !== '' => $message,
            $signal->message !== '' => $signal->message,
            default => $signal->reason->value,
        };
    }
}
