<?php declare(strict_types=1);

namespace Cognesy\AgentCtrl\Bridge;

use Cognesy\AgentCtrl\Common\Execution\JsonLinesBuffer;
use Cognesy\AgentCtrl\Common\Execution\SandboxCommandExecutor;
use Cognesy\AgentCtrl\Common\Execution\CliBinaryGuard;
use Cognesy\AgentCtrl\Common\Value\PathList;
use Cognesy\AgentCtrl\Contract\AgentBridge;
use Cognesy\AgentCtrl\Contract\StreamHandler;
use Cognesy\AgentCtrl\Dto\AgentResponse;
use Cognesy\AgentCtrl\Dto\StreamError;
use Cognesy\AgentCtrl\Dto\TokenUsage;
use Cognesy\AgentCtrl\Dto\ToolCall;
use Cognesy\AgentCtrl\Enum\AgentType;
use Cognesy\AgentCtrl\Event\CommandSpecCreated;
use Cognesy\AgentCtrl\Event\ProcessExecutionStarted;
use Cognesy\AgentCtrl\Event\RequestBuilt;
use Cognesy\AgentCtrl\Event\ResponseDataExtracted;
use Cognesy\AgentCtrl\Event\ResponseParsingCompleted;
use Cognesy\AgentCtrl\Event\ResponseParsingStarted;
use Cognesy\AgentCtrl\Event\StreamChunkProcessed;
use Cognesy\AgentCtrl\Event\StreamProcessingCompleted;
use Cognesy\AgentCtrl\Event\StreamProcessingStarted;
use Cognesy\AgentCtrl\OpenAICodex\Application\Builder\CodexCommandBuilder;
use Cognesy\AgentCtrl\OpenAICodex\Application\Dto\CodexRequest;
use Cognesy\AgentCtrl\OpenAICodex\Application\Parser\ResponseParser;
use Cognesy\AgentCtrl\OpenAICodex\Domain\Dto\Item\AgentMessage;
use Cognesy\AgentCtrl\OpenAICodex\Domain\Dto\Item\CommandExecution;
use Cognesy\AgentCtrl\OpenAICodex\Domain\Dto\Item\FileChange;
use Cognesy\AgentCtrl\OpenAICodex\Domain\Dto\Item\Item;
use Cognesy\AgentCtrl\OpenAICodex\Domain\Dto\Item\McpToolCall;
use Cognesy\AgentCtrl\OpenAICodex\Domain\Dto\Item\PlanUpdate;
use Cognesy\AgentCtrl\OpenAICodex\Domain\Dto\Item\Reasoning;
use Cognesy\AgentCtrl\OpenAICodex\Domain\Dto\Item\UnknownItem;
use Cognesy\AgentCtrl\OpenAICodex\Domain\Dto\Item\WebSearch;
use Cognesy\AgentCtrl\OpenAICodex\Domain\Dto\StreamEvent\ItemCompletedEvent;
use Cognesy\AgentCtrl\OpenAICodex\Domain\Dto\StreamEvent\ErrorEvent as CodexErrorEvent;
use Cognesy\AgentCtrl\OpenAICodex\Domain\Dto\StreamEvent\StreamEvent;
use Cognesy\AgentCtrl\OpenAICodex\Domain\Enum\OutputFormat;
use Cognesy\AgentCtrl\OpenAICodex\Domain\Enum\SandboxMode;
use Cognesy\Events\Contracts\CanHandleEvents;
use Cognesy\Sandbox\Enums\SandboxDriver;
use Cognesy\Utils\Json\JsonParsingException;
use JsonException;

/**
 * Bridge implementation for OpenAI Codex CLI.
 */
final class CodexBridge implements AgentBridge
{
    private CodexCommandBuilder $commandBuilder;
    private ResponseParser $responseParser;

    public function __construct(
        private ?string $model = null,
        private ?SandboxMode $sandboxMode = null,
        private bool $fullAuto = true,
        private bool $dangerouslyBypass = false,
        private bool $skipGitRepoCheck = false,
        private ?string $resumeSessionId = null,
        private bool $resumeLast = false,
        private ?PathList $additionalDirs = null,
        /** @var list<string>|null */
        private ?array $images = null,
        private ?string $workingDirectory = null,
        private SandboxDriver $sandboxDriver = SandboxDriver::Host,
        private int $timeout = 120,
        private ?CanHandleEvents $events = null,
        private bool $failFast = true,
    ) {
        $this->commandBuilder = new CodexCommandBuilder();
        $this->responseParser = new ResponseParser($this->failFast);
    }

    private function dispatch(object $event): void
    {
        $this->events?->dispatch($event);
    }

    #[\Override]
    public function execute(string $prompt): AgentResponse
    {
        return $this->executeStreaming($prompt, null);
    }

    #[\Override]
    public function executeStreaming(string $prompt, ?StreamHandler $handler): AgentResponse
    {
        CliBinaryGuard::assertAvailableForDriver('codex', AgentType::Codex, $this->sandboxDriver);

        // Build request with timing
        $requestStart = microtime(true);
        $request = $this->buildRequest($prompt);
        $requestDuration = (microtime(true) - $requestStart) * 1000;
        $this->dispatch(new RequestBuilt(AgentType::Codex, 'CodexRequest', $requestDuration));

        // Build command spec with timing
        $commandStart = microtime(true);
        $spec = $this->commandBuilder->buildExec($request);
        $commandDuration = (microtime(true) - $commandStart) * 1000;
        $this->dispatch(new CommandSpecCreated(AgentType::Codex, count($spec->argv()->toArray()), $commandDuration));

        $executor = SandboxCommandExecutor::forCodex($this->sandboxDriver, $this->timeout, $this->events);

        // Emit process execution start
        $this->dispatch(new ProcessExecutionStarted(AgentType::Codex, count($spec->argv()->toArray())));

        $collectedText = '';
        $toolCalls = [];
        $chunkCount = 0;
        $totalBytesProcessed = 0;
        $jsonLinesBuffer = $handler !== null ? new JsonLinesBuffer() : null;

        $streamStart = $handler !== null ? microtime(true) : null;
        if ($handler !== null && $streamStart !== null) {
            $this->dispatch(new StreamProcessingStarted(AgentType::Codex));
        }

        $streamCallback = $handler !== null ? function (string $type, string $chunk) use ($handler, $jsonLinesBuffer, &$collectedText, &$toolCalls, &$chunkCount, &$totalBytesProcessed): void {
            if ($type !== 'out') {
                return;
            }

            $chunkStart = microtime(true);
            $chunkSize = strlen($chunk);
            $chunkCount++;
            $totalBytesProcessed += $chunkSize;

            foreach ($jsonLinesBuffer->consume($chunk) as $line) {
                $this->handleStreamJsonLine($line, $handler, $collectedText, $toolCalls);
            }

            $chunkDuration = (microtime(true) - $chunkStart) * 1000;
            $this->dispatch(new StreamChunkProcessed(
                AgentType::Codex,
                $chunkCount,
                $chunkSize,
                'json-lines',
                $chunkDuration
            ));
        } : null;

        $execResult = $executor->executeStreaming($spec, $streamCallback);

        if ($handler !== null && $jsonLinesBuffer !== null) {
            foreach ($jsonLinesBuffer->flush() as $line) {
                $this->handleStreamJsonLine($line, $handler, $collectedText, $toolCalls);
            }
        }

        // Emit stream processing completion if streaming was used
        if ($handler !== null && $streamStart !== null) {
            $streamDuration = (microtime(true) - $streamStart) * 1000;
            $this->dispatch(new StreamProcessingCompleted(
                AgentType::Codex,
                $chunkCount,
                $streamDuration,
                $totalBytesProcessed
            ));
        }

        // Parse response for non-streaming or to extract final data
        $responseStart = microtime(true);
        $this->dispatch(new ResponseParsingStarted(AgentType::Codex, strlen($execResult->stdout()), 'json'));
        $response = $this->responseParser->parse($execResult, OutputFormat::Json);

        // Always extract from parsed response to avoid stream callback data loss.
        $extractStart = microtime(true);
        $collectedText = $response->messageText();
        $textLength = strlen($collectedText);
        $toolCalls = [];
        $eventCount = 0;
        $toolUseCount = 0;

        foreach ($response->decoded()->all() as $event) {
            $eventCount++;
            $streamEvent = StreamEvent::fromArray($event->data());
            if (!$streamEvent instanceof ItemCompletedEvent) {
                continue;
            }

            $item = $streamEvent->item;
            $toolCall = $this->toolCallFromCodexItem($item);
            if ($toolCall !== null) {
                $toolCalls[] = $toolCall;
                $toolUseCount++;
            }
        }

        $extractDuration = (microtime(true) - $extractStart) * 1000;
        $this->dispatch(new ResponseDataExtracted(
            AgentType::Codex,
            $eventCount,
            $toolUseCount,
            $textLength,
            $extractDuration
        ));

        $threadId = $response->threadId();
        $normalizedSessionId = $threadId !== null ? (string) $threadId : null;

        // Emit response parsing completion
        $responseDuration = (microtime(true) - $responseStart) * 1000;
        $this->dispatch(new ResponseParsingCompleted(AgentType::Codex, $responseDuration, $normalizedSessionId));

        // Convert usage if available
        $usage = $response->usage() !== null
            ? TokenUsage::fromCodex($response->usage())
            : null;

        return new AgentResponse(
            agentType: AgentType::Codex,
            text: $collectedText,
            exitCode: $response->exitCode(),
            sessionId: $normalizedSessionId,
            usage: $usage,
            cost: null, // Codex doesn't expose cost
            toolCalls: $toolCalls,
            rawResponse: $response,
            parseFailures: $response->parseFailures(),
            parseFailureSamples: $response->parseFailureSamples(),
        );
    }

    private function buildRequest(string $prompt): CodexRequest
    {
        return new CodexRequest(
            prompt: $prompt,
            outputFormat: OutputFormat::Json,
            sandboxMode: $this->sandboxMode,
            model: $this->model,
            images: $this->images,
            workingDirectory: $this->workingDirectory,
            additionalDirs: $this->additionalDirs,
            fullAuto: $this->fullAuto,
            dangerouslyBypass: $this->dangerouslyBypass,
            skipGitRepoCheck: $this->skipGitRepoCheck,
            resumeSessionId: $this->resumeSessionId,
            resumeLast: $this->resumeLast,
        );
    }

    /**
     * @param list<ToolCall> $toolCalls
     */
    private function handleStreamJsonLine(
        string $line,
        StreamHandler $handler,
        string &$collectedText,
        array &$toolCalls,
    ): void {
        $decoded = $this->decodeStreamJsonLine($line, 'Codex stream JSON line');
        if ($decoded === null) {
            return;
        }

        $event = StreamEvent::fromArray($decoded);
        if ($event instanceof CodexErrorEvent) {
            $handler->onError(new StreamError($event->message, $event->code));
            return;
        }
        if (!$event instanceof ItemCompletedEvent) {
            return;
        }

        $item = $event->item;
        if ($item instanceof AgentMessage) {
            $collectedText .= $item->text;
            $handler->onText($item->text);
        }

        $toolCall = $this->toolCallFromCodexItem($item);
        if ($toolCall === null) {
            return;
        }
        $toolCalls[] = $toolCall;
        $handler->onToolUse($toolCall);
    }

    private function toolCallFromCodexItem(Item $item): ?ToolCall
    {
        return match (true) {
            $item instanceof McpToolCall => new ToolCall(
                tool: $item->tool,
                input: $item->arguments ?? [],
                output: $this->encodeJsonOrNull($item->result),
                callId: $item->id,
                isError: $item->hasError() || $this->isItemStatusError($item->status),
            ),
            $item instanceof CommandExecution => new ToolCall(
                tool: 'bash',
                input: ['command' => $item->command],
                output: $item->output,
                callId: $item->id,
                isError: $item->exitCode !== 0 || $this->isItemStatusError($item->status),
            ),
            $item instanceof FileChange => new ToolCall(
                tool: 'file_change',
                input: [
                    'path' => $item->path,
                    'action' => $item->action,
                ],
                output: $item->diff ?? $item->content,
                callId: $item->id,
                isError: $this->isItemStatusError($item->status),
            ),
            $item instanceof WebSearch => new ToolCall(
                tool: 'web_search',
                input: ['query' => $item->query],
                output: $this->encodeJsonOrNull($item->results),
                callId: $item->id,
                isError: $this->isItemStatusError($item->status),
            ),
            $item instanceof PlanUpdate => new ToolCall(
                tool: 'plan_update',
                input: [],
                output: $item->plan,
                callId: $item->id,
                isError: $this->isItemStatusError($item->status),
            ),
            $item instanceof Reasoning => new ToolCall(
                tool: 'reasoning',
                input: [],
                output: $item->text,
                callId: $item->id,
                isError: $this->isItemStatusError($item->status),
            ),
            $item instanceof UnknownItem => new ToolCall(
                tool: $item->itemType(),
                input: [],
                output: $this->encodeJsonOrNull($item->rawData),
                callId: $item->id,
                isError: $this->isItemStatusError($item->status),
            ),
            default => null,
        };
    }

    private function isItemStatusError(string $status): bool
    {
        return match (strtolower($status)) {
            'error', 'failed', 'cancelled', 'canceled' => true,
            default => false,
        };
    }

    private function encodeJsonOrNull(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $encoded = json_encode($value);
        return is_string($encoded) ? $encoded : null;
    }

    private function decodeStreamJsonLine(string $line, string $context): ?array
    {
        try {
            $decoded = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            if (!$this->failFast) {
                return null;
            }
            throw new JsonParsingException(
                message: "{$context}: {$exception->getMessage()}",
                json: $this->normalizeMalformedPayload($line),
            );
        }
        if (is_array($decoded)) {
            return $decoded;
        }
        if (!$this->failFast) {
            return null;
        }
        throw new JsonParsingException(
            message: "{$context}: expected JSON object or array",
            json: $this->normalizeMalformedPayload($line),
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
