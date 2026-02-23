<?php declare(strict_types=1);

namespace Cognesy\AgentCtrl\OpenAICodex\Application\Parser;

use Cognesy\AgentCtrl\Common\Collection\DecodedObjectCollection;
use Cognesy\AgentCtrl\Common\Value\DecodedObject;
use Cognesy\AgentCtrl\OpenAICodex\Application\Dto\CodexResponse;
use Cognesy\AgentCtrl\OpenAICodex\Domain\Dto\Item\AgentMessage;
use Cognesy\AgentCtrl\OpenAICodex\Domain\Dto\StreamEvent\ItemCompletedEvent;
use Cognesy\AgentCtrl\OpenAICodex\Domain\Dto\StreamEvent\StreamEvent;
use Cognesy\AgentCtrl\OpenAICodex\Domain\Dto\StreamEvent\ThreadStartedEvent;
use Cognesy\AgentCtrl\OpenAICodex\Domain\Dto\StreamEvent\TurnCompletedEvent;
use Cognesy\AgentCtrl\OpenAICodex\Domain\Enum\OutputFormat;
use Cognesy\AgentCtrl\OpenAICodex\Domain\Value\UsageStats;
use Cognesy\Sandbox\Data\ExecResult;
use Cognesy\Utils\Json\JsonParsingException;
use JsonException;

/**
 * Parses Codex CLI output into structured response
 */
final class ResponseParser
{
    private const MAX_PARSE_FAILURE_SAMPLES = 3;

    public function __construct(
        private bool $failFast = true,
    ) {}

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
        $parseFailures = 0;
        $parseFailureSamples = [];
        if (!is_array($lines)) {
            $this->onParseError(
                'Failed to parse Codex JSONL response: unable to split output into lines',
                $result->stdout(),
                $parseFailures,
                $parseFailureSamples,
            );
            return new CodexResponse(
                result: $result,
                decoded: DecodedObjectCollection::empty(),
                parseFailures: $parseFailures,
                parseFailureSamples: $parseFailureSamples,
            );
        }

        $items = [];
        $threadId = null;
        $usage = null;
        $messageText = '';

        foreach ($lines as $lineIndex => $line) {
            $trimmed = trim($line);
            if ($trimmed === '') {
                continue;
            }

            $decoded = $this->decodeJsonLine(
                payload: $trimmed,
                lineNumber: $lineIndex + 1,
                parseFailures: $parseFailures,
                parseFailureSamples: $parseFailureSamples,
            );
            if ($decoded === null) {
                continue;
            }

            $items[] = new DecodedObject($decoded);

            // Extract metadata from events
            $event = StreamEvent::fromArray($decoded);

            if ($event instanceof ThreadStartedEvent) {
                $threadId = $event->threadId();
            }

            if ($event instanceof TurnCompletedEvent && $event->usage !== null) {
                $usage = $event->usage;
            }

            if ($event instanceof ItemCompletedEvent && $event->item instanceof AgentMessage) {
                $messageText .= $event->item->text;
            }
        }

        return new CodexResponse(
            result: $result,
            decoded: DecodedObjectCollection::of($items),
            threadId: $threadId,
            usage: $usage,
            messageText: $messageText,
            parseFailures: $parseFailures,
            parseFailureSamples: $parseFailureSamples,
        );
    }

    /**
     * @param list<string> $parseFailureSamples
     */
    private function decodeJsonLine(
        string $payload,
        int $lineNumber,
        int &$parseFailures,
        array &$parseFailureSamples,
    ): ?array {
        try {
            $decoded = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            $this->onParseError(
                context: "Failed to parse Codex JSONL line {$lineNumber}: {$exception->getMessage()}",
                payload: $payload,
                parseFailures: $parseFailures,
                parseFailureSamples: $parseFailureSamples,
            );
            return null;
        }
        if (is_array($decoded)) {
            return $decoded;
        }
        $this->onParseError(
            context: "Failed to parse Codex JSONL line {$lineNumber}: expected JSON object or array",
            payload: $payload,
            parseFailures: $parseFailures,
            parseFailureSamples: $parseFailureSamples,
        );
        return null;
    }

    /**
     * Parse text output (no structured data)
     */
    private function fromText(ExecResult $result): CodexResponse
    {
        return new CodexResponse(
            result: $result,
            decoded: DecodedObjectCollection::empty(),
            messageText: $result->stdout(),
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
        foreach ($decoded->all() as $object) {
            $events[] = StreamEvent::fromArray($object->data());
        }
        return $events;
    }

    /**
     * @param list<string> $parseFailureSamples
     */
    private function onParseError(
        string $context,
        mixed $payload,
        int &$parseFailures,
        array &$parseFailureSamples,
    ): void {
        $parseFailures++;
        if (count($parseFailureSamples) < self::MAX_PARSE_FAILURE_SAMPLES) {
            $parseFailureSamples[] = $this->normalizeMalformedPayload($payload);
        }
        if (!$this->failFast) {
            return;
        }
        throw new JsonParsingException(
            message: $context,
            json: $this->normalizeMalformedPayload($payload),
        );
    }

    private function normalizeMalformedPayload(mixed $payload): string
    {
        if (is_string($payload)) {
            return mb_substr(trim($payload), 0, 200);
        }
        $encoded = json_encode($payload);
        if (!is_string($encoded)) {
            return '<unserializable>';
        }
        return mb_substr(trim($encoded), 0, 200);
    }
}
