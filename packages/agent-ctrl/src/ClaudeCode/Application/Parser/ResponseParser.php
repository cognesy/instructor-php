<?php declare(strict_types=1);

namespace Cognesy\AgentCtrl\ClaudeCode\Application\Parser;

use Cognesy\AgentCtrl\ClaudeCode\Application\Dto\ClaudeResponse;
use Cognesy\AgentCtrl\ClaudeCode\Application\Dto\ClaudeEventCollection;
use Cognesy\AgentCtrl\ClaudeCode\Domain\Dto\StreamEvent\MessageEvent;
use Cognesy\AgentCtrl\ClaudeCode\Domain\Dto\StreamEvent\StreamEvent;
use Cognesy\AgentCtrl\ClaudeCode\Domain\Enum\OutputFormat;
use Cognesy\AgentCtrl\Common\Collection\DecodedObjectCollection;
use Cognesy\AgentCtrl\Common\Value\DecodedObject;
use Cognesy\Sandbox\Data\ExecResult;
use Cognesy\Utils\Json\JsonParsingException;
use JsonException;

final class ResponseParser
{
    private const MAX_PARSE_FAILURE_SAMPLES = 3;

    public function __construct(
        private bool $failFast = true,
    ) {}

    public function parse(ExecResult $result, OutputFormat $format) : ClaudeResponse {
        return match ($format) {
            OutputFormat::Json => $this->fromJson($result),
            OutputFormat::StreamJson => $this->fromStreamJson($result),
            OutputFormat::Text => new ClaudeResponse($result, DecodedObjectCollection::empty(), messageText: $result->stdout()),
        };
    }

    private function fromJson(ExecResult $result) : ClaudeResponse {
        $parseFailures = 0;
        $parseFailureSamples = [];
        $decoded = $this->decodeJson(
            payload: $result->stdout(),
            context: 'Failed to parse Claude JSON response',
            parseFailures: $parseFailures,
            parseFailureSamples: $parseFailureSamples,
        );
        if ($decoded === null) {
            return new ClaudeResponse(
                result: $result,
                decoded: DecodedObjectCollection::empty(),
                parseFailures: $parseFailures,
                parseFailureSamples: $parseFailureSamples,
            );
        }
        $items = [];
        $events = [];
        $messageText = '';
        foreach ($this->normalizeToList($decoded) as $entry) {
            if (!is_array($entry)) {
                $this->onParseError(
                    context: 'Failed to parse Claude JSON response: expected JSON object entries',
                    payload: $entry,
                    parseFailures: $parseFailures,
                    parseFailureSamples: $parseFailureSamples,
                );
                continue;
            }
            $items[] = new DecodedObject($entry);
            $event = StreamEvent::fromArray($entry);
            $events[] = $event;
            if ($event instanceof MessageEvent) {
                foreach ($event->message->textContent() as $textContent) {
                    $messageText .= $textContent->text;
                }
            }
        }
        return new ClaudeResponse(
            result: $result,
            decoded: DecodedObjectCollection::of($items),
            events: ClaudeEventCollection::of($events),
            messageText: $messageText,
            parseFailures: $parseFailures,
            parseFailureSamples: $parseFailureSamples,
        );
    }

    private function fromStreamJson(ExecResult $result) : ClaudeResponse {
        $lines = preg_split('/\r\n|\r|\n/', $result->stdout());
        $parseFailures = 0;
        $parseFailureSamples = [];
        if (!is_array($lines)) {
            $this->onParseError(
                'Failed to parse Claude stream-json response: unable to split output into lines',
                $result->stdout(),
                $parseFailures,
                $parseFailureSamples,
            );
            return new ClaudeResponse(
                result: $result,
                decoded: DecodedObjectCollection::empty(),
                parseFailures: $parseFailures,
                parseFailureSamples: $parseFailureSamples,
            );
        }
        $items = [];
        $events = [];
        $messageText = '';
        foreach ($lines as $lineIndex => $line) {
            $trimmed = trim($line);
            if ($trimmed === '') {
                continue;
            }
            $decoded = $this->decodeJson(
                payload: $trimmed,
                context: "Failed to parse Claude stream-json line " . ($lineIndex + 1),
                parseFailures: $parseFailures,
                parseFailureSamples: $parseFailureSamples,
            );
            if ($decoded === null) {
                continue;
            }
            $items[] = new DecodedObject($decoded);
            $event = StreamEvent::fromArray($decoded);
            $events[] = $event;
            if ($event instanceof MessageEvent) {
                foreach ($event->message->textContent() as $textContent) {
                    $messageText .= $textContent->text;
                }
            }
        }
        return new ClaudeResponse(
            result: $result,
            decoded: DecodedObjectCollection::of($items),
            events: ClaudeEventCollection::of($events),
            messageText: $messageText,
            parseFailures: $parseFailures,
            parseFailureSamples: $parseFailureSamples,
        );
    }

    /**
     * @return list<array<string,mixed>>
     */
    private function normalizeToList(array $decoded) : array {
        if (array_is_list($decoded)) {
            /** @var list<array<string,mixed>> $decoded */
            return $decoded;
        }
        /** @var list<array<string,mixed>> $out */
        $out = [$decoded];
        return $out;
    }

    /**
     * @param list<string> $parseFailureSamples
     */
    private function decodeJson(
        string $payload,
        string $context,
        int &$parseFailures,
        array &$parseFailureSamples,
    ): ?array {
        try {
            $decoded = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            $this->onParseError(
                context: "{$context}: {$exception->getMessage()}",
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
            context: "{$context}: expected JSON object or array",
            payload: $payload,
            parseFailures: $parseFailures,
            parseFailureSamples: $parseFailureSamples,
        );
        return null;
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

    private function normalizeMalformedPayload(mixed $payload): string {
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
