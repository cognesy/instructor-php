<?php declare(strict_types=1);

namespace Cognesy\Instructor\Partials\Data;

use Cognesy\Polyglot\Inference\Data\ToolCall;

/**
 * Represents a tool call state update event.
 * Emitted by ToolCallState transducer.
 */
final readonly class ToolCallStateUpdate
{
    public function __construct(
        public ToolCallEvent $event,
        public ?ToolCall $toolCall,
        public string $rawArgs,
        public string $normalizedArgs,
    ) {}
}
