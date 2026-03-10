<?php declare(strict_types=1);

namespace Cognesy\AgentCtrl\Pi\Domain\Dto\StreamEvent;

use Cognesy\AgentCtrl\Common\Value\Normalize;
use Cognesy\AgentCtrl\Pi\Domain\Dto\PiMessage;

/**
 * Event emitted when the agent finishes — contains all messages
 *
 * Example: {"type":"agent_end","messages":[...]}
 */
final readonly class AgentEndEvent extends StreamEvent
{
    /**
     * @param list<array> $messages Raw message arrays from Pi
     */
    public function __construct(
        array $rawData,
        public array $messages,
    ) {
        parent::__construct($rawData);
    }

    #[\Override]
    public function type(): string
    {
        return 'agent_end';
    }

    /**
     * Get the last assistant message text
     */
    public function assistantText(): string
    {
        $text = '';
        foreach (array_reverse($this->messages) as $message) {
            if (($message['role'] ?? '') !== 'assistant') {
                continue;
            }
            foreach (Normalize::toArray($message['content'] ?? []) as $part) {
                if (($part['type'] ?? '') === 'text') {
                    $text .= Normalize::toString($part['text'] ?? '');
                }
            }
            break;
        }
        return $text;
    }

    /**
     * Extract usage from the last assistant message
     */
    public function usage(): ?array
    {
        foreach (array_reverse($this->messages) as $message) {
            if (($message['role'] ?? '') !== 'assistant') {
                continue;
            }
            $usage = $message['usage'] ?? null;
            if (is_array($usage)) {
                return $usage;
            }
        }
        return null;
    }

    /**
     * Extract total cost from the last assistant message usage
     */
    public function cost(): ?float
    {
        $usage = $this->usage();
        if ($usage === null) {
            return null;
        }
        $cost = $usage['cost'] ?? null;
        if (!is_array($cost)) {
            return null;
        }
        $total = $cost['total'] ?? null;
        return is_numeric($total) ? (float) $total : null;
    }

    public static function fromArray(array $data): self
    {
        return new self(
            rawData: $data,
            messages: Normalize::toArray($data['messages'] ?? []),
        );
    }
}
