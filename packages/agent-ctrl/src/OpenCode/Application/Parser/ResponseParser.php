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
use Cognesy\AgentCtrl\OpenCode\Domain\ValueObject\OpenCodeMessageId;
use Cognesy\Sandbox\Data\ExecResult;

/**
 * Parses OpenCode CLI output into structured response
 */
final class ResponseParser
{
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
        if (!is_array($lines)) {
            return new OpenCodeResponse($result, DecodedObjectCollection::empty());
        }

        $items = [];
        $sessionId = null;
        $messageId = null;
        $messageText = '';
        $totalCost = 0.0;
        $finalUsage = null;

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
            $event = StreamEvent::fromArray($decoded);

            // Extract sessionID from first event
            if ($sessionId === null && $event->sessionIdValue !== null) {
                $sessionId = $event->sessionIdValue;
            }

            // Accumulate text content
            if ($event instanceof TextEvent) {
                $messageText .= $event->text;
            }

            // Extract usage and cost from step_finish events
            if ($event instanceof StepFinishEvent) {
                if ($event->messageId !== '') {
                    $messageId = OpenCodeMessageId::fromString($event->messageId);
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
        );
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
        foreach ($decoded->toArray() as $object) {
            $events[] = StreamEvent::fromArray($object->data());
        }
        return $events;
    }
}
