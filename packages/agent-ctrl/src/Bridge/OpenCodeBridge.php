<?php declare(strict_types=1);

namespace Cognesy\AgentCtrl\Bridge;

use Cognesy\AgentCtrl\Common\Execution\JsonLinesBuffer;
use Cognesy\AgentCtrl\Common\Execution\SandboxCommandExecutor;
use Cognesy\AgentCtrl\Common\Execution\CliBinaryGuard;
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
use Cognesy\AgentCtrl\OpenCode\Application\Builder\OpenCodeCommandBuilder;
use Cognesy\AgentCtrl\OpenCode\Application\Dto\OpenCodeRequest;
use Cognesy\AgentCtrl\OpenCode\Application\Parser\ResponseParser;
use Cognesy\AgentCtrl\OpenCode\Domain\Dto\StreamEvent\ErrorEvent as OpenCodeErrorEvent;
use Cognesy\AgentCtrl\OpenCode\Domain\Dto\StreamEvent\StreamEvent;
use Cognesy\AgentCtrl\OpenCode\Domain\Dto\StreamEvent\TextEvent;
use Cognesy\AgentCtrl\OpenCode\Domain\Dto\StreamEvent\ToolUseEvent;
use Cognesy\AgentCtrl\OpenCode\Domain\Enum\OutputFormat;
use Cognesy\Events\Contracts\CanHandleEvents;
use Cognesy\Sandbox\Enums\SandboxDriver;
use Cognesy\Utils\Json\JsonParsingException;
use JsonException;

/**
 * Bridge implementation for OpenCode CLI.
 */
final class OpenCodeBridge implements AgentBridge
{
    private OpenCodeCommandBuilder $commandBuilder;
    private ResponseParser $responseParser;

    public function __construct(
        private ?string $model = null,
        private ?string $agent = null,
        /** @var list<string>|null */
        private ?array $files = null,
        private bool $continueSession = false,
        private ?string $sessionId = null,
        private bool $share = false,
        private ?string $title = null,
        private ?string $workingDirectory = null,
        private SandboxDriver $sandboxDriver = SandboxDriver::Host,
        private int $timeout = 120,
        private ?CanHandleEvents $events = null,
        private bool $failFast = true,
    ) {
        $this->commandBuilder = new OpenCodeCommandBuilder();
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
            CliBinaryGuard::assertAvailableForDriver('opencode', AgentType::OpenCode, $this->sandboxDriver);

            // Build request with timing
            $requestStart = microtime(true);
            $request = $this->buildRequest($prompt);
            $requestDuration = (microtime(true) - $requestStart) * 1000;
            $this->dispatch(new RequestBuilt(AgentType::OpenCode, 'OpenCodeRequest', $requestDuration));

            // Build command spec with timing
            $commandStart = microtime(true);
            $spec = $this->commandBuilder->buildRun($request);
            $commandDuration = (microtime(true) - $commandStart) * 1000;
            $this->dispatch(new CommandSpecCreated(AgentType::OpenCode, count($spec->argv()->toArray()), $commandDuration));

            $executor = SandboxCommandExecutor::forOpenCode($this->sandboxDriver, $this->timeout, $this->events);

            // Emit process execution start
            $this->dispatch(new ProcessExecutionStarted(AgentType::OpenCode, count($spec->argv()->toArray())));

            $collectedText = '';
            $toolCalls = [];
            $chunkCount = 0;
            $totalBytesProcessed = 0;
            $jsonLinesBuffer = $handler !== null ? new JsonLinesBuffer() : null;

            $streamStart = $handler !== null ? microtime(true) : null;
            if ($handler !== null && $streamStart !== null) {
                $this->dispatch(new StreamProcessingStarted(AgentType::OpenCode));
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
                    AgentType::OpenCode,
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
                    AgentType::OpenCode,
                    $chunkCount,
                    $streamDuration,
                    $totalBytesProcessed
                ));
            }

            // Parse response for non-streaming or to extract final data
            $responseStart = microtime(true);
            $this->dispatch(new ResponseParsingStarted(AgentType::OpenCode, strlen($execResult->stdout()), 'json'));
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
                if (!$streamEvent instanceof ToolUseEvent) {
                    continue;
                }

                $callId = $streamEvent->callId();
                $normalizedCallId = $callId !== null ? (string) $callId : null;
                $toolCalls[] = new ToolCall(
                    tool: $streamEvent->tool,
                    input: $streamEvent->input,
                    output: $streamEvent->output,
                    callId: $normalizedCallId,
                    isError: !$streamEvent->isCompleted(),
                );
                $toolUseCount++;
            }

            $extractDuration = (microtime(true) - $extractStart) * 1000;
            $this->dispatch(new ResponseDataExtracted(
                AgentType::OpenCode,
                $eventCount,
                $toolUseCount,
                $textLength,
                $extractDuration
            ));

            $sessionId = $response->sessionId();
            $normalizedSessionId = $sessionId !== null ? (string) $sessionId : null;

            // Emit response parsing completion
            $responseDuration = (microtime(true) - $responseStart) * 1000;
            $this->dispatch(new ResponseParsingCompleted(AgentType::OpenCode, $responseDuration, $normalizedSessionId));

            // Convert usage if available
            $usage = $response->usage() !== null
                ? TokenUsage::fromOpenCode($response->usage())
                : null;

            return new AgentResponse(
                agentType: AgentType::OpenCode,
                text: $collectedText,
                exitCode: $response->exitCode(),
                sessionId: $normalizedSessionId,
                usage: $usage,
                cost: $response->cost(),
                toolCalls: $toolCalls,
                rawResponse: $response,
                parseFailures: $response->parseFailures(),
                parseFailureSamples: $response->parseFailureSamples(),
            );
        } finally {
            $this->restoreWorkingDirectory($previousDirectory);
        }
    }

    private function buildRequest(string $prompt): OpenCodeRequest
    {
        return new OpenCodeRequest(
            prompt: $prompt,
            outputFormat: OutputFormat::Json,
            model: $this->model,
            agent: $this->agent,
            files: $this->files,
            continueSession: $this->continueSession,
            sessionId: $this->sessionId,
            share: $this->share,
            title: $this->title,
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
        $decoded = $this->decodeStreamJsonLine($line, 'OpenCode stream JSON line');
        if ($decoded === null) {
            return;
        }

        $event = StreamEvent::fromArray($decoded);
        if ($event instanceof OpenCodeErrorEvent) {
            $handler->onError(new StreamError(
                message: $event->message,
                code: $event->code,
                details: $event->rawData,
            ));
            return;
        }

        if ($event instanceof TextEvent) {
            $collectedText .= $event->text;
            $handler->onText($event->text);
        }

        if (!$event instanceof ToolUseEvent) {
            return;
        }

        $callId = $event->callId();
        $normalizedCallId = $callId !== null ? (string) $callId : null;
        $toolCall = new ToolCall(
            tool: $event->tool,
            input: $event->input,
            output: $event->output,
            callId: $normalizedCallId,
            isError: !$event->isCompleted(),
        );
        $toolCalls[] = $toolCall;
        $handler->onToolUse($toolCall);
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
