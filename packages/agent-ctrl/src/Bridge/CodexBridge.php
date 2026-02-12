<?php declare(strict_types=1);

namespace Cognesy\AgentCtrl\Bridge;

use Cognesy\AgentCtrl\Common\Enum\SandboxDriver;
use Cognesy\AgentCtrl\Common\Execution\SandboxCommandExecutor;
use Cognesy\AgentCtrl\Common\Value\PathList;
use Cognesy\AgentCtrl\Contract\AgentBridge;
use Cognesy\AgentCtrl\Contract\StreamHandler;
use Cognesy\AgentCtrl\Dto\AgentResponse;
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
use Cognesy\AgentCtrl\OpenAICodex\Domain\Dto\Item\McpToolCall;
use Cognesy\AgentCtrl\OpenAICodex\Domain\Dto\StreamEvent\ItemCompletedEvent;
use Cognesy\AgentCtrl\OpenAICodex\Domain\Dto\StreamEvent\StreamEvent;
use Cognesy\AgentCtrl\OpenAICodex\Domain\Enum\OutputFormat;
use Cognesy\AgentCtrl\OpenAICodex\Domain\Enum\SandboxMode;
use Cognesy\Events\Contracts\CanHandleEvents;

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
        private int $maxRetries = 0,
        private ?CanHandleEvents $events = null,
    ) {
        $this->commandBuilder = new CodexCommandBuilder();
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
        $this->dispatch(new RequestBuilt(AgentType::Codex, 'CodexRequest', $requestDuration));

        // Build command spec with timing
        $commandStart = microtime(true);
        $spec = $this->commandBuilder->buildExec($request);
        $commandDuration = (microtime(true) - $commandStart) * 1000;
        $this->dispatch(new CommandSpecCreated(AgentType::Codex, count($spec->argv()->toArray()), $commandDuration));

        $executor = SandboxCommandExecutor::forCodex($this->sandboxDriver, $this->maxRetries, $this->timeout, $this->events);

        // Emit process execution start
        $this->dispatch(new ProcessExecutionStarted(AgentType::Codex, count($spec->argv()->toArray())));

        $collectedText = '';
        $toolCalls = [];
        $chunkCount = 0;
        $totalBytesProcessed = 0;

        $streamStart = $handler !== null ? microtime(true) : null;
        if ($handler !== null && $streamStart !== null) {
            $this->dispatch(new StreamProcessingStarted(AgentType::Codex));
        }

        $streamCallback = $handler !== null ? function (string $type, string $chunk) use ($handler, &$collectedText, &$toolCalls, &$chunkCount, &$totalBytesProcessed): void {
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

                if ($event instanceof ItemCompletedEvent) {
                    $item = $event->item;

                    if ($item instanceof AgentMessage) {
                        $collectedText .= $item->text;
                        $handler->onText($item->text);
                    }

                    if ($item instanceof McpToolCall) {
                        $toolCall = new ToolCall(
                            tool: $item->tool,
                            input: $item->arguments ?? [],
                            output: $item->result !== null ? json_encode($item->result) : null,
                            callId: $item->id,
                            isError: $item->hasError(),
                        );
                        $toolCalls[] = $toolCall;
                        $handler->onToolUse($toolCall);
                    }

                    if ($item instanceof CommandExecution) {
                        $toolCall = new ToolCall(
                            tool: 'bash',
                            input: ['command' => $item->command],
                            output: $item->output,
                            callId: $item->id,
                            isError: $item->exitCode !== 0,
                        );
                        $toolCalls[] = $toolCall;
                        $handler->onToolUse($toolCall);
                    }
                }
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

        // Extract text and tools from decoded events if not streaming
        $eventCount = 0;
        $textLength = 0;
        $toolUseCount = count($toolCalls);

        if ($collectedText === '' && $handler === null) {
            $extractStart = microtime(true);
            foreach ($response->decoded()->all() as $event) {
                $eventCount++;
                $streamEvent = StreamEvent::fromArray($event->data());
                if ($streamEvent instanceof ItemCompletedEvent) {
                    $item = $streamEvent->item;

                    if ($item instanceof AgentMessage) {
                        $collectedText .= $item->text;
                        $textLength += strlen($item->text);
                    }

                    if ($item instanceof McpToolCall) {
                        $toolCalls[] = new ToolCall(
                            tool: $item->tool,
                            input: $item->arguments ?? [],
                            output: $item->result !== null ? json_encode($item->result) : null,
                            callId: $item->id,
                            isError: $item->hasError(),
                        );
                        $toolUseCount++;
                    }

                    if ($item instanceof CommandExecution) {
                        $toolCalls[] = new ToolCall(
                            tool: 'bash',
                            input: ['command' => $item->command],
                            output: $item->output,
                            callId: $item->id,
                            isError: $item->exitCode !== 0,
                        );
                        $toolUseCount++;
                    }
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
        } else {
            $textLength = strlen($collectedText);
            $this->dispatch(new ResponseDataExtracted(
                AgentType::Codex,
                $chunkCount,
                $toolUseCount,
                $textLength,
                0
            ));
        }

        // Emit response parsing completion
        $responseDuration = (microtime(true) - $responseStart) * 1000;
        $this->dispatch(new ResponseParsingCompleted(AgentType::Codex, $responseDuration, $response->threadId()));

        // Convert usage if available
        $usage = $response->usage() !== null
            ? TokenUsage::fromCodex($response->usage())
            : null;

        return new AgentResponse(
            agentType: AgentType::Codex,
            text: $collectedText,
            exitCode: $response->exitCode(),
            sessionId: $response->threadId(),
            usage: $usage,
            cost: null, // Codex doesn't expose cost
            toolCalls: $toolCalls,
            rawResponse: $response,
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
}
