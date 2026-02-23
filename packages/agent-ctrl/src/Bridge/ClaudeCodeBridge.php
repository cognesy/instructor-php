<?php declare(strict_types=1);

namespace Cognesy\AgentCtrl\Bridge;

use Cognesy\AgentCtrl\ClaudeCode\Application\Builder\ClaudeCommandBuilder;
use Cognesy\AgentCtrl\ClaudeCode\Application\Dto\ClaudeRequest;
use Cognesy\AgentCtrl\ClaudeCode\Application\Parser\ResponseParser;
use Cognesy\AgentCtrl\ClaudeCode\Domain\Dto\StreamEvent\ErrorEvent as ClaudeErrorEvent;
use Cognesy\AgentCtrl\ClaudeCode\Domain\Dto\StreamEvent\MessageEvent;
use Cognesy\AgentCtrl\ClaudeCode\Domain\Dto\StreamEvent\StreamEvent;
use Cognesy\AgentCtrl\ClaudeCode\Domain\Enum\OutputFormat;
use Cognesy\AgentCtrl\ClaudeCode\Domain\Enum\PermissionMode;
use Cognesy\AgentCtrl\Common\Execution\JsonLinesBuffer;
use Cognesy\AgentCtrl\Common\Execution\SandboxCommandExecutor;
use Cognesy\AgentCtrl\Common\Value\PathList;
use Cognesy\AgentCtrl\Contract\AgentBridge;
use Cognesy\AgentCtrl\Contract\StreamHandler;
use Cognesy\AgentCtrl\Dto\ToolCall;
use Cognesy\AgentCtrl\Dto\AgentResponse;
use Cognesy\AgentCtrl\Dto\StreamError;
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
use Cognesy\Events\Contracts\CanHandleEvents;
use Cognesy\Sandbox\Enums\SandboxDriver;
use Cognesy\Utils\Json\JsonParsingException;
use JsonException;

/**
 * Bridge implementation for Claude Code CLI.
 */
final class ClaudeCodeBridge implements AgentBridge
{
    private ClaudeCommandBuilder $commandBuilder;
    private ResponseParser $responseParser;

    public function __construct(
        private ?string $model = null,
        private ?string $systemPrompt = null,
        private ?string $appendSystemPrompt = null,
        private ?int $maxTurns = null,
        private PermissionMode $permissionMode = PermissionMode::BypassPermissions,
        private bool $includePartialMessages = true,
        private bool $verbose = true,  // Required for stream-json with --print
        private ?string $resumeSessionId = null,
        private bool $continueMostRecent = false,
        private ?PathList $additionalDirs = null,
        private ?string $workingDirectory = null,
        private SandboxDriver $sandboxDriver = SandboxDriver::Host,
        private int $timeout = 120,
        private ?CanHandleEvents $events = null,
        private bool $failFast = true,
    ) {
        $this->commandBuilder = new ClaudeCommandBuilder();
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
        $previousDirectory = $this->switchToWorkingDirectory();

        try {
            // Build request with timing
            $requestStart = microtime(true);
            $request = $this->buildRequest($prompt);
            $requestDuration = (microtime(true) - $requestStart) * 1000;
            $this->dispatch(new RequestBuilt(AgentType::ClaudeCode, 'ClaudeRequest', $requestDuration));

            // Build command spec with timing
            $commandStart = microtime(true);
            $spec = $this->commandBuilder->buildHeadless($request);
            $commandDuration = (microtime(true) - $commandStart) * 1000;
            $this->dispatch(new CommandSpecCreated(AgentType::ClaudeCode, count($spec->argv()->toArray()), $commandDuration));

            $executor = SandboxCommandExecutor::forClaudeCode($this->sandboxDriver, $this->timeout, $this->events);

            // Emit process execution start
            $this->dispatch(new ProcessExecutionStarted(AgentType::ClaudeCode, count($spec->argv()->toArray())));

            $collectedText = '';
            $toolCalls = [];
            $sessionId = null;
            $chunkCount = 0;
            $totalBytesProcessed = 0;
            $jsonLinesBuffer = $handler !== null ? new JsonLinesBuffer() : null;

            $streamStart = $handler !== null ? microtime(true) : null;
            if ($handler !== null && $streamStart !== null) {
                $this->dispatch(new StreamProcessingStarted(AgentType::ClaudeCode));
            }

            $streamCallback = $handler !== null ? function (string $type, string $chunk) use ($handler, $jsonLinesBuffer, &$collectedText, &$toolCalls, &$sessionId, &$chunkCount, &$totalBytesProcessed): void {
                if ($type !== 'out') {
                    return;
                }

                $chunkStart = microtime(true);
                $chunkSize = strlen($chunk);
                $chunkCount++;
                $totalBytesProcessed += $chunkSize;

                foreach ($jsonLinesBuffer->consume($chunk) as $line) {
                    $this->handleStreamJsonLine($line, $handler, $collectedText, $toolCalls, $sessionId);
                }

                $chunkDuration = (microtime(true) - $chunkStart) * 1000;
                $this->dispatch(new StreamChunkProcessed(
                    AgentType::ClaudeCode,
                    $chunkCount,
                    $chunkSize,
                    'json-lines',
                    $chunkDuration
                ));
            } : null;

            $execResult = $executor->executeStreaming($spec, $streamCallback);

            if ($handler !== null && $jsonLinesBuffer !== null) {
                foreach ($jsonLinesBuffer->flush() as $line) {
                    $this->handleStreamJsonLine($line, $handler, $collectedText, $toolCalls, $sessionId);
                }
            }

            // Emit stream processing completion if streaming was used
            if ($handler !== null && $streamStart !== null) {
                $streamDuration = (microtime(true) - $streamStart) * 1000;
                $this->dispatch(new StreamProcessingCompleted(
                    AgentType::ClaudeCode,
                    $chunkCount,
                    $streamDuration,
                    $totalBytesProcessed
                ));
            }

            // Parse response for non-streaming or to extract final data
            $responseStart = microtime(true);
            $this->dispatch(new ResponseParsingStarted(AgentType::ClaudeCode, strlen($execResult->stdout()), 'stream-json'));
            $response = $this->responseParser->parse($execResult, OutputFormat::StreamJson);

            // Always extract from parsed response to avoid stream callback data loss.
            $extractStart = microtime(true);
            $collectedText = $response->messageText();
            $textLength = strlen($collectedText);
            $toolCalls = [];
            $eventCount = 0;
            $toolUseCount = 0;
            $sessionIdFromResponse = null;

            foreach ($response->decoded()->all() as $event) {
                $eventCount++;
                $data = $event->data();

                $sessionCandidate = $event->getNonEmptyString('session_id');
                if ($sessionCandidate !== null) {
                    $sessionIdFromResponse = $sessionCandidate;
                }

                $streamEvent = StreamEvent::fromArray($data);
                if (!$streamEvent instanceof MessageEvent) {
                    continue;
                }

                foreach ($streamEvent->message->toolUses() as $toolUse) {
                    $toolCalls[] = new ToolCall(
                        tool: $toolUse->name,
                        input: $toolUse->input,
                        callId: $toolUse->id,
                    );
                    $toolUseCount++;
                }

                foreach ($streamEvent->message->toolResults() as $toolResult) {
                    $toolCalls[] = new ToolCall(
                        tool: 'tool_result',
                        input: ['tool_use_id' => $toolResult->toolUseId],
                        output: $toolResult->content,
                        callId: $toolResult->toolUseId,
                        isError: $toolResult->isError,
                    );
                    $toolUseCount++;
                }
            }

            $sessionId = $sessionIdFromResponse ?? $sessionId;
            $extractDuration = (microtime(true) - $extractStart) * 1000;
            $this->dispatch(new ResponseDataExtracted(
                AgentType::ClaudeCode,
                $eventCount,
                $toolUseCount,
                $textLength,
                $extractDuration
            ));

            // Emit response parsing completion
            $responseDuration = (microtime(true) - $responseStart) * 1000;
            $this->dispatch(new ResponseParsingCompleted(AgentType::ClaudeCode, $responseDuration, $sessionId));

            return new AgentResponse(
                agentType: AgentType::ClaudeCode,
                text: $collectedText,
                exitCode: $execResult->exitCode(),
                sessionId: $sessionId,
                usage: null, // ClaudeCode doesn't expose token usage
                cost: null,  // ClaudeCode doesn't expose cost
                toolCalls: $toolCalls,
                rawResponse: $response,
                parseFailures: $response->parseFailures(),
                parseFailureSamples: $response->parseFailureSamples(),
            );
        } finally {
            $this->restoreWorkingDirectory($previousDirectory);
        }
    }

    private function buildRequest(string $prompt): ClaudeRequest
    {
        return ClaudeRequest::builder()
            ->withPrompt($prompt)
            ->withOutputFormat(OutputFormat::StreamJson)
            ->withPermissionMode($this->permissionMode)
            ->withMaxTurns($this->maxTurns)
            ->withModel($this->model)
            ->withSystemPrompt($this->systemPrompt)
            ->withAppendSystemPrompt($this->appendSystemPrompt)
            ->withAdditionalDirs($this->additionalDirs)
            ->withIncludePartialMessages($this->includePartialMessages)
            ->withVerbose($this->verbose)
            ->withResumeSessionId($this->resumeSessionId)
            ->withContinueMostRecent($this->continueMostRecent)
            ->build();
    }

    /**
     * @param list<ToolCall> $toolCalls
     */
    private function handleStreamJsonLine(
        string $line,
        StreamHandler $handler,
        string &$collectedText,
        array &$toolCalls,
        ?string &$sessionId,
    ): void {
        $decoded = $this->decodeStreamJsonLine($line, 'Claude stream JSON line');
        if ($decoded === null) {
            return;
        }

        $event = StreamEvent::fromArray($decoded);
        if ($event instanceof ClaudeErrorEvent) {
            $handler->onError(new StreamError($event->error));
            return;
        }

        if ($event instanceof MessageEvent) {
            foreach ($event->message->textContent() as $textContent) {
                $collectedText .= $textContent->text;
                $handler->onText($textContent->text);
            }

            foreach ($event->message->toolUses() as $toolUse) {
                $toolCall = new ToolCall(
                    tool: $toolUse->name,
                    input: $toolUse->input,
                    callId: $toolUse->id,
                );
                $toolCalls[] = $toolCall;
                $handler->onToolUse($toolCall);
            }

            foreach ($event->message->toolResults() as $toolResult) {
                $toolCall = new ToolCall(
                    tool: 'tool_result',
                    input: ['tool_use_id' => $toolResult->toolUseId],
                    output: $toolResult->content,
                    callId: $toolResult->toolUseId,
                    isError: $toolResult->isError,
                );
                $toolCalls[] = $toolCall;
                $handler->onToolUse($toolCall);
            }
        }

        $sessionCandidate = $decoded['session_id'] ?? null;
        if (!is_string($sessionCandidate) || $sessionCandidate === '') {
            return;
        }
        $sessionId = $sessionCandidate;
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

    private function switchToWorkingDirectory(): ?string
    {
        $workingDirectory = $this->workingDirectory;
        if ($workingDirectory === null || $workingDirectory === '') {
            return null;
        }
        if (!is_dir($workingDirectory)) {
            throw new \InvalidArgumentException("Working directory does not exist: {$workingDirectory}");
        }

        $currentDirectory = getcwd();
        if ($currentDirectory === false) {
            throw new \RuntimeException('Unable to resolve current working directory');
        }
        if (chdir($workingDirectory)) {
            return $currentDirectory;
        }
        throw new \RuntimeException("Unable to change working directory to: {$workingDirectory}");
    }

    private function restoreWorkingDirectory(?string $directory): void
    {
        if ($directory === null) {
            return;
        }
        chdir($directory);
    }
}
