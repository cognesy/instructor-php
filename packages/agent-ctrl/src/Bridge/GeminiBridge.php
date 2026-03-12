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
use Cognesy\AgentCtrl\Gemini\Application\Builder\GeminiCommandBuilder;
use Cognesy\AgentCtrl\Gemini\Application\Dto\GeminiRequest;
use Cognesy\AgentCtrl\Gemini\Application\Parser\ResponseParser;
use Cognesy\AgentCtrl\Gemini\Domain\Dto\StreamEvent\ErrorEvent as GeminiErrorEvent;
use Cognesy\AgentCtrl\Gemini\Domain\Dto\StreamEvent\MessageEvent;
use Cognesy\AgentCtrl\Gemini\Domain\Dto\StreamEvent\StreamEvent;
use Cognesy\AgentCtrl\Gemini\Domain\Dto\StreamEvent\ToolResultEvent;
use Cognesy\AgentCtrl\Gemini\Domain\Dto\StreamEvent\ToolUseEvent;
use Cognesy\AgentCtrl\Gemini\Domain\Enum\ApprovalMode;
use Cognesy\AgentCtrl\Gemini\Domain\Enum\OutputFormat;
use Cognesy\Events\Contracts\CanHandleEvents;
use Cognesy\Sandbox\Enums\SandboxDriver;
use Cognesy\Utils\Json\JsonParsingException;
use JsonException;

/**
 * Bridge implementation for Gemini CLI.
 */
final class GeminiBridge implements AgentBridge
{
    private GeminiCommandBuilder $commandBuilder;
    private ResponseParser $responseParser;

    public function __construct(
        private ?string $model = null,
        private ?ApprovalMode $approvalMode = null,
        private bool $sandbox = false,
        /** @var list<string>|null */
        private ?array $includeDirectories = null,
        /** @var list<string>|null */
        private ?array $extensions = null,
        /** @var list<string>|null */
        private ?array $allowedTools = null,
        /** @var list<string>|null */
        private ?array $allowedMcpServerNames = null,
        /** @var list<string>|null */
        private ?array $policy = null,
        private ?string $resumeSession = null,
        private bool $debug = false,
        private ?string $workingDirectory = null,
        private SandboxDriver $sandboxDriver = SandboxDriver::Host,
        private int $timeout = 120,
        private ?CanHandleEvents $events = null,
        private bool $failFast = true,
    ) {
        $this->commandBuilder = new GeminiCommandBuilder();
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
            CliBinaryGuard::assertAvailableForDriver('gemini', AgentType::Gemini, $this->sandboxDriver);

            // Build request with timing
            $requestStart = microtime(true);
            $request = $this->buildRequest($prompt);
            $requestDuration = (microtime(true) - $requestStart) * 1000;
            $this->dispatch(new RequestBuilt(AgentType::Gemini, 'GeminiRequest', $requestDuration));

            // Build command spec with timing
            $commandStart = microtime(true);
            $spec = $this->commandBuilder->build($request);
            $commandDuration = (microtime(true) - $commandStart) * 1000;
            $this->dispatch(new CommandSpecCreated(AgentType::Gemini, count($spec->argv()->toArray()), $commandDuration));

            $executor = SandboxCommandExecutor::forGemini($this->sandboxDriver, $this->timeout, $this->events);

            // Emit process execution start
            $this->dispatch(new ProcessExecutionStarted(AgentType::Gemini, count($spec->argv()->toArray())));

            $collectedText = '';
            $toolCalls = [];
            /** @var array<string, array{tool:string,input:array}> */
            $pendingToolUses = [];
            $chunkCount = 0;
            $totalBytesProcessed = 0;
            $jsonLinesBuffer = $handler !== null ? new JsonLinesBuffer() : null;

            $streamStart = $handler !== null ? microtime(true) : null;
            if ($handler !== null && $streamStart !== null) {
                $this->dispatch(new StreamProcessingStarted(AgentType::Gemini));
            }

            $streamCallback = $handler !== null ? function (string $type, string $chunk) use ($handler, $jsonLinesBuffer, &$collectedText, &$toolCalls, &$pendingToolUses, &$chunkCount, &$totalBytesProcessed): void {
                if ($type !== 'out') {
                    return;
                }

                $chunkStart = microtime(true);
                $chunkSize = strlen($chunk);
                $chunkCount++;
                $totalBytesProcessed += $chunkSize;

                foreach ($jsonLinesBuffer->consume($chunk) as $line) {
                    $this->handleStreamJsonLine($line, $handler, $collectedText, $toolCalls, $pendingToolUses);
                }

                $chunkDuration = (microtime(true) - $chunkStart) * 1000;
                $this->dispatch(new StreamChunkProcessed(
                    AgentType::Gemini,
                    $chunkCount,
                    $chunkSize,
                    'json-lines',
                    $chunkDuration
                ));
            } : null;

            $execResult = $executor->executeStreaming($spec, $streamCallback);

            if ($handler !== null && $jsonLinesBuffer !== null) {
                foreach ($jsonLinesBuffer->flush() as $line) {
                    $this->handleStreamJsonLine($line, $handler, $collectedText, $toolCalls, $pendingToolUses);
                }
            }

            // Emit stream processing completion if streaming was used
            if ($handler !== null && $streamStart !== null) {
                $streamDuration = (microtime(true) - $streamStart) * 1000;
                $this->dispatch(new StreamProcessingCompleted(
                    AgentType::Gemini,
                    $chunkCount,
                    $streamDuration,
                    $totalBytesProcessed
                ));
            }

            // Parse response for non-streaming or to extract final data
            $responseStart = microtime(true);
            $this->dispatch(new ResponseParsingStarted(AgentType::Gemini, strlen($execResult->stdout()), 'stream-json'));
            $response = $this->responseParser->parse($execResult);

            // Always extract from parsed response to avoid stream callback data loss.
            $extractStart = microtime(true);
            $collectedText = $response->messageText();
            $textLength = strlen($collectedText);
            $toolCalls = [];
            $eventCount = $response->decoded()->count();
            $toolUseCount = 0;

            foreach ($response->toolCalls() as $tc) {
                $toolCalls[] = new ToolCall(
                    tool: $tc['tool'],
                    input: $tc['input'],
                    output: $tc['output'],
                    callId: $tc['toolId'] !== '' ? $tc['toolId'] : null,
                    isError: $tc['isError'],
                );
                $toolUseCount++;
            }

            $extractDuration = (microtime(true) - $extractStart) * 1000;
            $this->dispatch(new ResponseDataExtracted(
                AgentType::Gemini,
                $eventCount,
                $toolUseCount,
                $textLength,
                $extractDuration
            ));

            $sessionId = $response->sessionId();
            $normalizedSessionId = $sessionId !== null ? (string) $sessionId : null;

            // Emit response parsing completion
            $responseDuration = (microtime(true) - $responseStart) * 1000;
            $this->dispatch(new ResponseParsingCompleted(AgentType::Gemini, $responseDuration, $normalizedSessionId));

            // Convert usage if available
            $usage = $response->usage() !== null
                ? TokenUsage::fromGemini($response->usage())
                : null;

            return new AgentResponse(
                agentType: AgentType::Gemini,
                text: $collectedText,
                exitCode: $response->exitCode(),
                sessionId: $normalizedSessionId,
                usage: $usage,
                cost: null, // Gemini CLI doesn't expose cost
                toolCalls: $toolCalls,
                rawResponse: $response,
                parseFailures: $response->parseFailures(),
                parseFailureSamples: $response->parseFailureSamples(),
            );
        } finally {
            $this->restoreWorkingDirectory($previousDirectory);
        }
    }

    private function buildRequest(string $prompt): GeminiRequest
    {
        return new GeminiRequest(
            prompt: $prompt,
            outputFormat: OutputFormat::StreamJson,
            model: $this->model,
            approvalMode: $this->approvalMode,
            sandbox: $this->sandbox,
            includeDirectories: $this->includeDirectories,
            extensions: $this->extensions,
            allowedTools: $this->allowedTools,
            allowedMcpServerNames: $this->allowedMcpServerNames,
            policy: $this->policy,
            resumeSession: $this->resumeSession,
            debug: $this->debug,
        );
    }

    /**
     * @param list<ToolCall> $toolCalls
     * @param array<string, array{tool:string,input:array}> $pendingToolUses
     */
    private function handleStreamJsonLine(
        string $line,
        StreamHandler $handler,
        string &$collectedText,
        array &$toolCalls,
        array &$pendingToolUses,
    ): void {
        $decoded = $this->decodeStreamJsonLine($line, 'Gemini stream JSON line');
        if ($decoded === null) {
            return;
        }

        $event = StreamEvent::fromArray($decoded);

        if ($event instanceof GeminiErrorEvent) {
            $handler->onError(new StreamError(
                message: $event->message,
                code: null,
                details: $event->rawData,
            ));
            return;
        }

        if ($event instanceof MessageEvent && $event->isAssistant() && $event->isDelta()) {
            $collectedText .= $event->content;
            $handler->onText($event->content);
        }

        if ($event instanceof ToolUseEvent) {
            $pendingToolUses[$event->toolId] = [
                'tool' => $event->toolName,
                'input' => $event->parameters,
            ];
        }

        if ($event instanceof ToolResultEvent) {
            $pending = $pendingToolUses[$event->toolId] ?? null;
            $toolCall = new ToolCall(
                tool: $pending['tool'] ?? '',
                input: $pending['input'] ?? [],
                output: $event->output,
                callId: $event->toolId !== '' ? $event->toolId : null,
                isError: $event->isError(),
            );
            $toolCalls[] = $toolCall;
            $handler->onToolUse($toolCall);
            unset($pendingToolUses[$event->toolId]);
        }
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
