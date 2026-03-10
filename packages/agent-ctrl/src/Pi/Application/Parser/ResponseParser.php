<?php declare(strict_types=1);

namespace Cognesy\AgentCtrl\Pi\Application\Parser;

use Cognesy\AgentCtrl\Common\Collection\DecodedObjectCollection;
use Cognesy\AgentCtrl\Common\Value\DecodedObject;
use Cognesy\AgentCtrl\Pi\Application\Dto\PiResponse;
use Cognesy\AgentCtrl\Pi\Domain\Dto\StreamEvent\AgentEndEvent;
use Cognesy\AgentCtrl\Pi\Domain\Dto\StreamEvent\MessageEndEvent;
use Cognesy\AgentCtrl\Pi\Domain\Dto\StreamEvent\MessageUpdateEvent;
use Cognesy\AgentCtrl\Pi\Domain\Dto\StreamEvent\SessionEvent;
use Cognesy\AgentCtrl\Pi\Domain\Dto\StreamEvent\StreamEvent;
use Cognesy\AgentCtrl\Pi\Domain\Enum\OutputMode;
use Cognesy\AgentCtrl\Pi\Domain\Value\TokenUsage;
use Cognesy\Sandbox\Data\ExecResult;
use Cognesy\Utils\Json\JsonParsingException;
use JsonException;

/**
 * Parses Pi CLI output into structured response
 */
final class ResponseParser
{
    private const MAX_PARSE_FAILURE_SAMPLES = 3;

    public function __construct(
        private bool $failFast = true,
    ) {}

    /**
     * Parse execution result into structured response
     */
    public function parse(ExecResult $result, OutputMode $mode): PiResponse
    {
        return match ($mode) {
            OutputMode::Json => $this->fromJsonLines($result),
            OutputMode::Rpc => $this->fromText($result),
        };
    }

    /**
     * Parse JSONL streaming output
     */
    private function fromJsonLines(ExecResult $result): PiResponse
    {
        $lines = preg_split('/\r\n|\r|\n/', $result->stdout());
        $parseFailures = 0;
        $parseFailureSamples = [];
        if (!is_array($lines)) {
            $this->onParseError(
                'Failed to parse Pi JSONL response: unable to split output into lines',
                $result->stdout(),
                $parseFailures,
                $parseFailureSamples,
            );
            return new PiResponse(
                result: $result,
                decoded: DecodedObjectCollection::empty(),
                parseFailures: $parseFailures,
                parseFailureSamples: $parseFailureSamples,
            );
        }

        $items = [];
        $sessionId = null;
        $messageText = '';
        $totalCost = null;
        $finalUsage = null;

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
            $event = StreamEvent::fromArray($decoded);

            // Extract session ID from session header event
            if ($sessionId === null && $event instanceof SessionEvent) {
                $sessionId = $event->sessionId();
            }

            // Accumulate text from message_update text_delta events
            if ($event instanceof MessageUpdateEvent && $event->isTextDelta()) {
                $delta = $event->textDelta();
                if ($delta !== null) {
                    $messageText .= $delta;
                }
            }

            // Extract usage and cost from the last assistant message_end
            if ($event instanceof MessageEndEvent && $event->isAssistant()) {
                $usageData = $event->usage();
                if ($usageData !== null) {
                    $finalUsage = TokenUsage::fromArray($usageData);
                    $costData = $usageData['cost'] ?? null;
                    if (is_array($costData) && isset($costData['total'])) {
                        $totalCost = (float) $costData['total'];
                    }
                }
            }

            // Also extract from agent_end as fallback
            if ($event instanceof AgentEndEvent) {
                $agentUsage = $event->usage();
                if ($agentUsage !== null && $finalUsage === null) {
                    $finalUsage = TokenUsage::fromArray($agentUsage);
                }
                $agentCost = $event->cost();
                if ($agentCost !== null && $totalCost === null) {
                    $totalCost = $agentCost;
                }
            }
        }

        return new PiResponse(
            result: $result,
            decoded: DecodedObjectCollection::of($items),
            sessionId: $sessionId !== null ? $sessionId->toString() : null,
            messageText: $messageText,
            usage: $finalUsage,
            cost: $totalCost,
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
                context: "Failed to parse Pi JSONL line {$lineNumber}: {$exception->getMessage()}",
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
            context: "Failed to parse Pi JSONL line {$lineNumber}: expected JSON object or array",
            payload: $payload,
            parseFailures: $parseFailures,
            parseFailureSamples: $parseFailureSamples,
        );
        return null;
    }

    /**
     * Parse plain text output (fallback for non-JSON modes)
     */
    private function fromText(ExecResult $result): PiResponse
    {
        return new PiResponse(
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
