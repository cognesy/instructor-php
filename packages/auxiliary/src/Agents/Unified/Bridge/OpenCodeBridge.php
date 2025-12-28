<?php declare(strict_types=1);

namespace Cognesy\Auxiliary\Agents\Unified\Bridge;

use Cognesy\Auxiliary\Agents\Common\Enum\SandboxDriver;
use Cognesy\Auxiliary\Agents\Common\Execution\SandboxCommandExecutor;
use Cognesy\Auxiliary\Agents\OpenCode\Application\Builder\OpenCodeCommandBuilder;
use Cognesy\Auxiliary\Agents\OpenCode\Application\Dto\OpenCodeRequest;
use Cognesy\Auxiliary\Agents\OpenCode\Application\Parser\ResponseParser;
use Cognesy\Auxiliary\Agents\OpenCode\Domain\Dto\StreamEvent\StreamEvent;
use Cognesy\Auxiliary\Agents\OpenCode\Domain\Dto\StreamEvent\TextEvent;
use Cognesy\Auxiliary\Agents\OpenCode\Domain\Dto\StreamEvent\ToolUseEvent;
use Cognesy\Auxiliary\Agents\OpenCode\Domain\Enum\OutputFormat;
use Cognesy\Auxiliary\Agents\Unified\Contract\AgentBridge;
use Cognesy\Auxiliary\Agents\Unified\Contract\StreamHandler;
use Cognesy\Auxiliary\Agents\Unified\Dto\TokenUsage;
use Cognesy\Auxiliary\Agents\Unified\Dto\ToolCall;
use Cognesy\Auxiliary\Agents\Unified\Dto\UnifiedResponse;
use Cognesy\Auxiliary\Agents\Unified\Enum\AgentType;

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
    ) {
        $this->commandBuilder = new OpenCodeCommandBuilder();
        $this->responseParser = new ResponseParser();
    }

    #[\Override]
    public function execute(string $prompt): UnifiedResponse
    {
        return $this->executeStreaming($prompt, null);
    }

    #[\Override]
    public function executeStreaming(string $prompt, ?StreamHandler $handler): UnifiedResponse
    {
        $request = $this->buildRequest($prompt);
        $spec = $this->commandBuilder->buildRun($request);

        $executor = SandboxCommandExecutor::forOpenCode($this->sandboxDriver, $this->maxRetries);

        $collectedText = '';
        $toolCalls = [];

        $streamCallback = $handler !== null ? function (string $type, string $chunk) use ($handler, &$collectedText, &$toolCalls): void {
            if ($type !== 'out') {
                return;
            }

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
        } : null;

        $execResult = $executor->executeStreaming($spec, $streamCallback);

        // Parse response
        $response = $this->responseParser->parse($execResult, OutputFormat::Json);

        // Use parsed text if not collected via streaming
        if ($collectedText === '' && $handler === null) {
            $collectedText = $response->messageText();

            // Extract tool calls from decoded events
            foreach ($response->decoded()->all() as $event) {
                $streamEvent = StreamEvent::fromArray($event->data());
                if ($streamEvent instanceof ToolUseEvent) {
                    $toolCalls[] = new ToolCall(
                        tool: $streamEvent->tool,
                        input: $streamEvent->input,
                        output: $streamEvent->output,
                        callId: $streamEvent->callId,
                        isError: !$streamEvent->isCompleted(),
                    );
                }
            }
        }

        // Convert usage if available
        $usage = $response->usage() !== null
            ? TokenUsage::fromOpenCode($response->usage())
            : null;

        return new UnifiedResponse(
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
