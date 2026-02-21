<?php declare(strict_types=1);

namespace Cognesy\AgentCtrl\Bridge;

use Cognesy\Sandbox\Enums\SandboxDriver;
use Cognesy\AgentCtrl\Common\Execution\SandboxCommandExecutor;
use Cognesy\AgentCtrl\Contract\AgentBridge;
use Cognesy\AgentCtrl\Contract\StreamHandler;
use Cognesy\AgentCtrl\Dto\TokenUsage;
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
use Cognesy\AgentCtrl\OpenCode\Application\Builder\OpenCodeCommandBuilder;
use Cognesy\AgentCtrl\OpenCode\Application\Dto\OpenCodeRequest;
use Cognesy\AgentCtrl\OpenCode\Application\Parser\ResponseParser;
use Cognesy\AgentCtrl\OpenCode\Domain\Dto\StreamEvent\StreamEvent;
use Cognesy\AgentCtrl\OpenCode\Domain\Dto\StreamEvent\TextEvent;
use Cognesy\AgentCtrl\OpenCode\Domain\Dto\StreamEvent\ToolUseEvent;
use Cognesy\AgentCtrl\OpenCode\Domain\Enum\OutputFormat;
use Cognesy\Events\Contracts\CanHandleEvents;

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
        private SandboxDriver $sandboxDriver = SandboxDriver::Host,
        private int $timeout = 120,
        private int $maxRetries = 0,
        private ?CanHandleEvents $events = null,
    ) {
        $this->commandBuilder = new OpenCodeCommandBuilder();
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
        $this->dispatch(new RequestBuilt(AgentType::OpenCode, 'OpenCodeRequest', $requestDuration));

        // Build command spec with timing
        $commandStart = microtime(true);
        $spec = $this->commandBuilder->buildRun($request);
        $commandDuration = (microtime(true) - $commandStart) * 1000;
        $this->dispatch(new CommandSpecCreated(AgentType::OpenCode, count($spec->argv()->toArray()), $commandDuration));

        $executor = SandboxCommandExecutor::forOpenCode($this->sandboxDriver, $this->maxRetries, $this->timeout, $this->events);

        // Emit process execution start
        $this->dispatch(new ProcessExecutionStarted(AgentType::OpenCode, count($spec->argv()->toArray())));

        $collectedText = '';
        $toolCalls = [];
        $chunkCount = 0;
        $totalBytesProcessed = 0;

        $streamStart = $handler !== null ? microtime(true) : null;
        if ($handler !== null && $streamStart !== null) {
            $this->dispatch(new StreamProcessingStarted(AgentType::OpenCode));
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

                if ($event instanceof TextEvent) {
                    $collectedText .= $event->text;
                    $handler->onText($event->text);
                }

                if ($event instanceof ToolUseEvent) {
                    $toolCall = new ToolCall(
                        tool: $event->tool,
                        input: $event->input,
                        output: $event->output,
                        callId: $event->callId,
                        isError: !$event->isCompleted(),
                    );
                    $toolCalls[] = $toolCall;
                    $handler->onToolUse($toolCall);
                }
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

        // Extract text and tools from decoded events if not streaming
        $eventCount = 0;
        $textLength = 0;
        $toolUseCount = count($toolCalls);

        if ($collectedText === '' && $handler === null) {
            $extractStart = microtime(true);
            $collectedText = $response->messageText();
            $textLength = strlen($collectedText);

            foreach ($response->decoded()->all() as $event) {
                $eventCount++;
                $streamEvent = StreamEvent::fromArray($event->data());
                if ($streamEvent instanceof ToolUseEvent) {
                    $toolCalls[] = new ToolCall(
                        tool: $streamEvent->tool,
                        input: $streamEvent->input,
                        output: $streamEvent->output,
                        callId: $streamEvent->callId,
                        isError: !$streamEvent->isCompleted(),
                    );
                    $toolUseCount++;
                }
            }
            $extractDuration = (microtime(true) - $extractStart) * 1000;
            $this->dispatch(new ResponseDataExtracted(
                AgentType::OpenCode,
                $eventCount,
                $toolUseCount,
                $textLength,
                $extractDuration
            ));
        } else {
            $textLength = strlen($collectedText);
            $this->dispatch(new ResponseDataExtracted(
                AgentType::OpenCode,
                $chunkCount,
                $toolUseCount,
                $textLength,
                0
            ));
        }

        // Emit response parsing completion
        $responseDuration = (microtime(true) - $responseStart) * 1000;
        $this->dispatch(new ResponseParsingCompleted(AgentType::OpenCode, $responseDuration, $response->sessionId()));

        // Convert usage if available
        $usage = $response->usage() !== null
            ? TokenUsage::fromOpenCode($response->usage())
            : null;

        return new AgentResponse(
            agentType: AgentType::OpenCode,
            text: $collectedText,
            exitCode: $response->exitCode(),
            sessionId: $response->sessionId(),
            usage: $usage,
            cost: $response->cost(),
            toolCalls: $toolCalls,
            rawResponse: $response,
        );
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
}
