<?php declare(strict_types=1);

namespace Cognesy\AgentCtrl\OpenCode\Application\Parser;

use Cognesy\AgentCtrl\Common\Collection\DecodedObjectCollection;
use Cognesy\AgentCtrl\Common\Value\DecodedObject;
use Cognesy\AgentCtrl\OpenCode\Application\Dto\OpenCodeResponse;
use Cognesy\AgentCtrl\OpenCode\Domain\Dto\StreamEvent\StepFinishEvent;
use Cognesy\AgentCtrl\OpenCode\Domain\Dto\StreamEvent\StreamEvent;
use Cognesy\AgentCtrl\OpenCode\Domain\Dto\StreamEvent\TextEvent;
use Cognesy\AgentCtrl\OpenCode\Domain\Enum\OutputFormat;
use Cognesy\AgentCtrl\OpenCode\Domain\Value\TokenUsage;
use Cognesy\Sandbox\Data\ExecResult;
use Cognesy\Utils\Json\JsonParsingException;
use JsonException;

/**
 * Parses OpenCode CLI output into structured response
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
    public function parse(ExecResult $result, OutputFormat $format): OpenCodeResponse
    {
        return match ($format) {
            OutputFormat::Json => $this->fromJsonLines($result),
            OutputFormat::Default => $this->fromText($result),
        };
    }

    /**
     * Parse nd-JSON streaming output
     */
    private function fromJsonLines(ExecResult $result): OpenCodeResponse
    {
        $lines = preg_split('/\r\n|\r|\n/', $result->stdout());
        $parseFailures = 0;
        $parseFailureSamples = [];
        if (!is_array($lines)) {
            $this->onParseError(
                'Failed to parse OpenCode JSONL response: unable to split output into lines',
                $result->stdout(),
                $parseFailures,
                $parseFailureSamples,
            );
            return new OpenCodeResponse(
                result: $result,
                decoded: DecodedObjectCollection::empty(),
                parseFailures: $parseFailures,
                parseFailureSamples: $parseFailureSamples,
            );
        }

        $items = [];
        $sessionId = null;
        $messageId = null;
        $messageText = '';
        $totalCost = 0.0;
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

            // Extract sessionID from first event
            if ($sessionId === null && $event->sessionId() !== null) {
                $sessionId = $event->sessionId();
            }

            // Accumulate text content
            if ($event instanceof TextEvent) {
                $messageText .= $event->text;
            }

            // Extract usage and cost from step_finish events
            if ($event instanceof StepFinishEvent) {
                $eventMessageId = $event->messageId();
                if ($eventMessageId !== null) {
                    $messageId = $eventMessageId;
                }
                $totalCost += $event->cost;

                // Keep the last non-null usage (from final step)
                if ($event->tokens !== null) {
                    $finalUsage = $this->mergeUsage($finalUsage, $event->tokens);
                }
            }
        }

        return new OpenCodeResponse(
            result: $result,
            decoded: DecodedObjectCollection::of($items),
            sessionId: $sessionId,
            messageId: $messageId,
            messageText: $messageText,
            usage: $finalUsage,
            cost: $totalCost > 0 ? $totalCost : null,
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
                context: "Failed to parse OpenCode JSONL line {$lineNumber}: {$exception->getMessage()}",
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
            context: "Failed to parse OpenCode JSONL line {$lineNumber}: expected JSON object or array",
            payload: $payload,
            parseFailures: $parseFailures,
            parseFailureSamples: $parseFailureSamples,
        );
        return null;
    }

    /**
     * Parse plain text output (no structured data)
     */
    private function fromText(ExecResult $result): OpenCodeResponse
    {
        return new OpenCodeResponse(
            result: $result,
            decoded: DecodedObjectCollection::empty(),
            messageText: $result->stdout(),
        );
    }

    /**
     * Merge token usage from multiple steps
     */
    private function mergeUsage(?TokenUsage $existing, TokenUsage $new): TokenUsage
    {
        if ($existing === null) {
            return $new;
        }

        return new TokenUsage(
            input: $existing->input + $new->input,
            output: $existing->output + $new->output,
            reasoning: $existing->reasoning + $new->reasoning,
            cacheRead: $existing->cacheRead + $new->cacheRead,
            cacheWrite: $existing->cacheWrite + $new->cacheWrite,
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
