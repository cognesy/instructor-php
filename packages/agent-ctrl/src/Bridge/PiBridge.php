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
use Cognesy\AgentCtrl\Pi\Application\Builder\PiCommandBuilder;
use Cognesy\AgentCtrl\Pi\Application\Dto\PiRequest;
use Cognesy\AgentCtrl\Pi\Application\Parser\ResponseParser;
use Cognesy\AgentCtrl\Pi\Domain\Dto\StreamEvent\ErrorEvent as PiErrorEvent;
use Cognesy\AgentCtrl\Pi\Domain\Dto\StreamEvent\MessageUpdateEvent;
use Cognesy\AgentCtrl\Pi\Domain\Dto\StreamEvent\StreamEvent;
use Cognesy\AgentCtrl\Pi\Domain\Dto\StreamEvent\ToolExecutionEndEvent;
use Cognesy\AgentCtrl\Pi\Domain\Enum\OutputMode;
use Cognesy\AgentCtrl\Pi\Domain\Enum\ThinkingLevel;
use Cognesy\Events\Contracts\CanHandleEvents;
use Cognesy\Sandbox\Enums\SandboxDriver;
use Cognesy\Utils\Json\JsonParsingException;
use JsonException;

/**
 * Bridge implementation for Pi CLI.
 */
final class PiBridge implements AgentBridge
{
    private PiCommandBuilder $commandBuilder;
    private ResponseParser $responseParser;

    public function __construct(
        private ?string $model = null,
        private ?string $provider = null,
        private ?ThinkingLevel $thinkingLevel = null,
        private ?string $systemPrompt = null,
        private ?string $appendSystemPrompt = null,
        /** @var list<string>|null */
        private ?array $tools = null,
        private bool $noTools = false,
        /** @var list<string>|null */
        private ?array $files = null,
        /** @var list<string>|null */
        private ?array $extensions = null,
        private bool $noExtensions = false,
        /** @var list<string>|null */
        private ?array $skills = null,
        private bool $noSkills = false,
        private ?string $apiKey = null,
        private bool $continueSession = false,
        private ?string $sessionId = null,
        private bool $noSession = false,
        private ?string $sessionDir = null,
        private bool $verbose = false,
        private ?string $workingDirectory = null,
        private SandboxDriver $sandboxDriver = SandboxDriver::Host,
        private int $timeout = 120,
        private ?CanHandleEvents $events = null,
        private bool $failFast = true,
    ) {
        $this->commandBuilder = new PiCommandBuilder();
        $this->responseParser = new ResponseParser($this->failFast);
    }

    private function dispatch(object $event): void
    {
        $this->events?->dispatch($event);
    }

    #[\Override]
    public function execute(string|\Stringable $prompt): AgentResponse
    {
        return $this->executeStreaming($prompt, null);
    }

    #[\Override]
    public function executeStreaming(string|\Stringable $prompt, ?StreamHandler $handler): AgentResponse
    {
        $prompt = (string) $prompt;
        $previousDirectory = $this->switchToWorkingDirectory();

        try {
            CliBinaryGuard::assertAvailableForDriver('pi', AgentType::Pi, $this->sandboxDriver);

            // Build request with timing
            $requestStart = microtime(true);
            $request = $this->buildRequest($prompt);
            $requestDuration = (microtime(true) - $requestStart) * 1000;
            $this->dispatch(new RequestBuilt(AgentType::Pi, 'PiRequest', $requestDuration));

            // Build command spec with timing
            $commandStart = microtime(true);
            $spec = $this->commandBuilder->build($request);
            $commandDuration = (microtime(true) - $commandStart) * 1000;
            $this->dispatch(new CommandSpecCreated(AgentType::Pi, count($spec->argv()->toArray()), $commandDuration));

            $executor = SandboxCommandExecutor::forPi($this->sandboxDriver, $this->timeout, $this->events);

            // Emit process execution start
            $this->dispatch(new ProcessExecutionStarted(AgentType::Pi, count($spec->argv()->toArray())));

            $collectedText = '';
            $toolCalls = [];
            $chunkCount = 0;
            $totalBytesProcessed = 0;
            $jsonLinesBuffer = $handler !== null ? new JsonLinesBuffer() : null;

            $streamStart = $handler !== null ? microtime(true) : null;
            if ($handler !== null && $streamStart !== null) {
                $this->dispatch(new StreamProcessingStarted(AgentType::Pi));
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
                    AgentType::Pi,
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
                    AgentType::Pi,
                    $chunkCount,
                    $streamDuration,
                    $totalBytesProcessed
                ));
            }

            // Parse response for non-streaming or to extract final data
            $responseStart = microtime(true);
            $this->dispatch(new ResponseParsingStarted(AgentType::Pi, strlen($execResult->stdout()), 'json'));
            $response = $this->responseParser->parse($execResult, OutputMode::Json);

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
                if (!$streamEvent instanceof ToolExecutionEndEvent) {
                    continue;
                }

                $toolCalls[] = new ToolCall(
                    tool: $streamEvent->toolName,
                    input: is_array($streamEvent->result) ? $streamEvent->result : [],
                    output: $streamEvent->resultAsString(),
                    callId: $streamEvent->toolCallId !== '' ? $streamEvent->toolCallId : null,
                    isError: $streamEvent->isError,
                );
                $toolUseCount++;
            }

            $extractDuration = (microtime(true) - $extractStart) * 1000;
            $this->dispatch(new ResponseDataExtracted(
                AgentType::Pi,
                $eventCount,
                $toolUseCount,
                $textLength,
                $extractDuration
            ));

            $sessionId = $response->sessionId();
            $normalizedSessionId = $sessionId !== null ? (string) $sessionId : null;

            // Emit response parsing completion
            $responseDuration = (microtime(true) - $responseStart) * 1000;
            $this->dispatch(new ResponseParsingCompleted(AgentType::Pi, $responseDuration, $normalizedSessionId));

            // Convert usage if available
            $usage = $response->usage() !== null
                ? TokenUsage::fromPi($response->usage())
                : null;

            return new AgentResponse(
                agentType: AgentType::Pi,
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

    private function buildRequest(string $prompt): PiRequest
    {
        return new PiRequest(
            prompt: $prompt,
            outputMode: OutputMode::Json,
            model: $this->model,
            provider: $this->provider,
            thinkingLevel: $this->thinkingLevel,
            systemPrompt: $this->systemPrompt,
            appendSystemPrompt: $this->appendSystemPrompt,
            tools: $this->tools,
            noTools: $this->noTools,
            files: $this->files,
            extensions: $this->extensions,
            noExtensions: $this->noExtensions,
            skills: $this->skills,
            noSkills: $this->noSkills,
            apiKey: $this->apiKey,
            continueSession: $this->continueSession,
            sessionId: $this->sessionId,
            noSession: $this->noSession,
            sessionDir: $this->sessionDir,
            verbose: $this->verbose,
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
        $decoded = $this->decodeStreamJsonLine($line, 'Pi stream JSON line');
        if ($decoded === null) {
            return;
        }

        $event = StreamEvent::fromArray($decoded);

        if ($event instanceof PiErrorEvent) {
            $handler->onError(new StreamError(
                message: $event->message,
                code: $event->code,
                details: $event->rawData,
            ));
            return;
        }

        if ($event instanceof MessageUpdateEvent && $event->isTextDelta()) {
            $delta = $event->textDelta();
            if ($delta !== null) {
                $collectedText .= $delta;
                $handler->onText($delta);
            }
        }

        if (!$event instanceof ToolExecutionEndEvent) {
            return;
        }

        $toolCall = new ToolCall(
            tool: $event->toolName,
            input: is_array($event->result) ? $event->result : [],
            output: $event->resultAsString(),
            callId: $event->toolCallId !== '' ? $event->toolCallId : null,
            isError: $event->isError,
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
