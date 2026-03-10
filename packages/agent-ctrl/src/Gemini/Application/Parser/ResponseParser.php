<?php declare(strict_types=1);

namespace Cognesy\AgentCtrl\Gemini\Application\Parser;

use Cognesy\AgentCtrl\Common\Collection\DecodedObjectCollection;
use Cognesy\AgentCtrl\Common\Value\DecodedObject;
use Cognesy\AgentCtrl\Gemini\Application\Dto\GeminiResponse;
use Cognesy\AgentCtrl\Gemini\Domain\Dto\StreamEvent\InitEvent;
use Cognesy\AgentCtrl\Gemini\Domain\Dto\StreamEvent\MessageEvent;
use Cognesy\AgentCtrl\Gemini\Domain\Dto\StreamEvent\ResultEvent;
use Cognesy\AgentCtrl\Gemini\Domain\Dto\StreamEvent\StreamEvent;
use Cognesy\AgentCtrl\Gemini\Domain\Dto\StreamEvent\ToolResultEvent;
use Cognesy\AgentCtrl\Gemini\Domain\Dto\StreamEvent\ToolUseEvent;
use Cognesy\AgentCtrl\Gemini\Domain\Value\TokenUsage;
use Cognesy\Sandbox\Data\ExecResult;
use Cognesy\Utils\Json\JsonParsingException;
use JsonException;

/**
 * Parses Gemini CLI stream-json output into structured response
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
    public function parse(ExecResult $result): GeminiResponse
    {
        $lines = preg_split('/\r\n|\r|\n/', $result->stdout());
        $parseFailures = 0;
        $parseFailureSamples = [];
        if (!is_array($lines)) {
            $this->onParseError(
                'Failed to parse Gemini JSONL response: unable to split output into lines',
                $result->stdout(),
                $parseFailures,
                $parseFailureSamples,
            );
            return new GeminiResponse(
                result: $result,
                decoded: DecodedObjectCollection::empty(),
                parseFailures: $parseFailures,
                parseFailureSamples: $parseFailureSamples,
            );
        }

        $items = [];
        $sessionId = null;
        $messageText = '';
        $finalUsage = null;
        /** @var array<string, array{tool:string,input:array}> */
        $pendingToolCalls = [];
        /** @var list<array{tool:string,input:array,output:?string,isError:bool,toolId:string}> */
        $toolCalls = [];

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

            // Extract session ID from init event
            if ($sessionId === null && $event instanceof InitEvent) {
                $sessionId = $event->sessionId();
            }

            // Accumulate text from assistant message deltas
            if ($event instanceof MessageEvent && $event->isAssistant() && $event->isDelta()) {
                $messageText .= $event->content;
            }

            // Track tool_use events for pairing with tool_result
            if ($event instanceof ToolUseEvent) {
                $pendingToolCalls[$event->toolId] = [
                    'tool' => $event->toolName,
                    'input' => $event->parameters,
                ];
            }

            // Pair tool_result with tool_use
            if ($event instanceof ToolResultEvent) {
                $pending = $pendingToolCalls[$event->toolId] ?? null;
                $toolCalls[] = [
                    'tool' => $pending['tool'] ?? '',
                    'input' => $pending['input'] ?? [],
                    'output' => $event->output,
                    'isError' => $event->isError(),
                    'toolId' => $event->toolId,
                ];
                unset($pendingToolCalls[$event->toolId]);
            }

            // Extract token usage from result event
            if ($event instanceof ResultEvent && $event->stats !== []) {
                $finalUsage = TokenUsage::fromArray($event->stats);
            }
        }

        // If no JSONL events found, fall back to raw text
        if ($items === [] && trim($result->stdout()) !== '') {
            $messageText = $result->stdout();
        }

        return new GeminiResponse(
            result: $result,
            decoded: DecodedObjectCollection::of($items),
            sessionId: $sessionId !== null ? $sessionId->toString() : null,
            messageText: $messageText,
            usage: $finalUsage,
            toolCalls: $toolCalls,
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
                context: "Failed to parse Gemini JSONL line {$lineNumber}: {$exception->getMessage()}",
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
            context: "Failed to parse Gemini JSONL line {$lineNumber}: expected JSON object or array",
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
