<?php declare(strict_types=1);

namespace Cognesy\AgentCtrl\Bridge;

use Cognesy\AgentCtrl\ClaudeCode\Application\Builder\ClaudeCommandBuilder;
use Cognesy\AgentCtrl\ClaudeCode\Application\Dto\ClaudeRequest;
use Cognesy\AgentCtrl\ClaudeCode\Application\Parser\ResponseParser;
use Cognesy\AgentCtrl\ClaudeCode\Domain\Dto\StreamEvent\MessageEvent;
use Cognesy\AgentCtrl\ClaudeCode\Domain\Dto\StreamEvent\StreamEvent;
use Cognesy\AgentCtrl\ClaudeCode\Domain\Enum\OutputFormat;
use Cognesy\AgentCtrl\ClaudeCode\Domain\Enum\PermissionMode;
use Cognesy\AgentCtrl\Common\Enum\SandboxDriver;
use Cognesy\AgentCtrl\Common\Execution\SandboxCommandExecutor;
use Cognesy\AgentCtrl\Common\Value\PathList;
use Cognesy\AgentCtrl\Contract\AgentBridge;
use Cognesy\AgentCtrl\Contract\StreamHandler;
use Cognesy\AgentCtrl\Dto\ToolCall;
use Cognesy\AgentCtrl\Dto\AgentResponse;
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
        private SandboxDriver $sandboxDriver = SandboxDriver::Host,
        private int $timeout = 120,
        private int $maxRetries = 0,
        private ?CanHandleEvents $events = null,
    ) {
        $this->commandBuilder = new ClaudeCommandBuilder();
        $this->responseParser = new ResponseParser();
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

        $executor = SandboxCommandExecutor::forClaudeCode($this->sandboxDriver, $this->maxRetries, $this->timeout, $this->events);

        // Emit process execution start
        $this->dispatch(new ProcessExecutionStarted(AgentType::ClaudeCode, count($spec->argv()->toArray())));

        $collectedText = '';
        $toolCalls = [];
        $sessionId = null;
        $chunkCount = 0;
        $totalBytesProcessed = 0;

        $streamStart = $handler !== null ? microtime(true) : null;
        if ($handler !== null && $streamStart !== null) {
            $this->dispatch(new StreamProcessingStarted(AgentType::ClaudeCode));
        }

        $streamCallback = $handler !== null ? function (string $type, string $chunk) use ($handler, &$collectedText, &$toolCalls, &$sessionId, &$chunkCount, &$totalBytesProcessed): void {
            if ($type !== 'out') {
                return;
            }

            $chunkStart = microtime(true);
            $chunkSize = strlen($chunk);
            $chunkCount++;
            $totalBytesProcessed += $chunkSize;

            $lines = preg_split('/\r\n|\r|\n/', $chunk);
            foreach ($lines as $line) {
                $trimmed = trim($line);
                if ($trimmed === '') {
                    continue;
                }

                $decoded = json_decode($trimmed, true);
                if (!is_array($decoded)) {
                    continue;
                }

                $event = StreamEvent::fromArray($decoded);

                if ($event instanceof MessageEvent) {
                    // Handle text content
                    foreach ($event->message->textContent() as $textContent) {
                        $collectedText .= $textContent->text;
                        $handler->onText($textContent->text);
                    }

                    // Handle tool uses
                    foreach ($event->message->toolUses() as $toolUse) {
                        $toolCall = new ToolCall(
                            tool: $toolUse->name,
                            input: $toolUse->input,
                            callId: $toolUse->id,
                        );
                        $toolCalls[] = $toolCall;
                        $handler->onToolUse($toolCall);
                    }
                }

                // Try to extract session ID from result events
                if (isset($decoded['session_id'])) {
                    $sessionId = $decoded['session_id'];
                }
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

        // Extract text from decoded events if not collected via streaming
        $eventCount = 0;
        $textLength = 0;
        $toolUseCount = count($toolCalls);

        if ($collectedText === '' && $handler === null) {
            $extractStart = microtime(true);
            foreach ($response->decoded()->all() as $event) {
                $eventCount++;
                $streamEvent = StreamEvent::fromArray($event->data());
                if ($streamEvent instanceof MessageEvent) {
                    foreach ($streamEvent->message->textContent() as $textContent) {
                        $collectedText .= $textContent->text;
                        $textLength += strlen($textContent->text);
                    }
                    foreach ($streamEvent->message->toolUses() as $toolUse) {
                        $toolCalls[] = new ToolCall(
                            tool: $toolUse->name,
                            input: $toolUse->input,
                            callId: $toolUse->id,
                        );
                        $toolUseCount++;
                    }
                }
            }
            $extractDuration = (microtime(true) - $extractStart) * 1000;
            $this->dispatch(new ResponseDataExtracted(
                AgentType::ClaudeCode,
                $eventCount,
                $toolUseCount,
                $textLength,
                $extractDuration
            ));
        } else {
            $textLength = strlen($collectedText);
            $this->dispatch(new ResponseDataExtracted(
                AgentType::ClaudeCode,
                $chunkCount,
                $toolUseCount,
                $textLength,
                0 // No extraction time since it was done during streaming
            ));
        }

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
        );
    }

    private function buildRequest(string $prompt): ClaudeRequest
    {
        return new ClaudeRequest(
            prompt: $prompt,
            outputFormat: OutputFormat::StreamJson,
            permissionMode: $this->permissionMode,
            maxTurns: $this->maxTurns,
            model: $this->model,
            systemPrompt: $this->systemPrompt,
            appendSystemPrompt: $this->appendSystemPrompt,
            additionalDirs: $this->additionalDirs,
            includePartialMessages: $this->includePartialMessages,
            verbose: $this->verbose,
            resumeSessionId: $this->resumeSessionId,
            continueMostRecent: $this->continueMostRecent,
        );
    }
}
