<?php declare(strict_types=1);

namespace Cognesy\AgentCtrl\OpenAICodex\Application\Parser;

use Cognesy\AgentCtrl\Common\Collection\DecodedObjectCollection;
use Cognesy\AgentCtrl\Common\Value\DecodedObject;
use Cognesy\AgentCtrl\OpenAICodex\Application\Dto\CodexResponse;
use Cognesy\AgentCtrl\OpenAICodex\Domain\Dto\StreamEvent\StreamEvent;
use Cognesy\AgentCtrl\OpenAICodex\Domain\Dto\StreamEvent\ThreadStartedEvent;
use Cognesy\AgentCtrl\OpenAICodex\Domain\Dto\StreamEvent\TurnCompletedEvent;
use Cognesy\AgentCtrl\OpenAICodex\Domain\Enum\OutputFormat;
use Cognesy\AgentCtrl\OpenAICodex\Domain\Value\UsageStats;
use Cognesy\Sandbox\Data\ExecResult;

/**
 * Parses Codex CLI output into structured response
 */
final class ResponseParser
{
    public function parse(ExecResult $result, OutputFormat $format): CodexResponse
    {
        return match ($format) {
            OutputFormat::Json => $this->fromJsonLines($result),
            OutputFormat::Text => $this->fromText($result),
        };
    }

    /**
     * Parse JSONL streaming output
     *
     * Extracts thread_id from thread.started event
     * Extracts usage from turn.completed event
     */
    private function fromJsonLines(ExecResult $result): CodexResponse
    {
        $lines = preg_split('/\r\n|\r|\n/', $result->stdout());
        if (!is_array($lines)) {
            return new CodexResponse($result, DecodedObjectCollection::empty());
        }

        $items = [];
        $threadId = null;
        $usage = null;

        foreach ($lines as $line) {
            $trimmed = trim($line);
            if ($trimmed === '') {
                continue;
            }

            $decoded = json_decode($trimmed, true);
            if (!is_array($decoded)) {
                continue;
            }

            $items[] = new DecodedObject($decoded);

            // Extract metadata from events
            $event = StreamEvent::fromArray($decoded);

            if ($event instanceof ThreadStartedEvent) {
                $threadId = $event->threadId;
            }

            if ($event instanceof TurnCompletedEvent && $event->usage !== null) {
                $usage = $event->usage;
            }
        }

        return new CodexResponse(
            result: $result,
            decoded: DecodedObjectCollection::of($items),
            threadId: $threadId,
            usage: $usage,
        );
    }

    /**
     * Parse text output (no structured data)
     */
    private function fromText(ExecResult $result): CodexResponse
    {
        return new CodexResponse(
            result: $result,
            decoded: DecodedObjectCollection::empty(),
        );
    }

    /**
     * Parse stream events from decoded objects
     *
     * @return list<StreamEvent>
     */
    public function parseEvents(DecodedObjectCollection $decoded): array
    {
        $events = [];
        foreach ($decoded->toArray() as $object) {
            $events[] = StreamEvent::fromArray($object->data());
        }
        return $events;
    }
}
