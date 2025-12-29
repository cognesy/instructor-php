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
    ) {
        $this->commandBuilder = new CodexCommandBuilder();
        $this->responseParser = new ResponseParser();
    }

    #[\Override]
    public function execute(string $prompt): AgentResponse
    {
        return $this->executeStreaming($prompt, null);
    }

    #[\Override]
    public function executeStreaming(string $prompt, ?StreamHandler $handler): AgentResponse
    {
        $request = $this->buildRequest($prompt);
        $spec = $this->commandBuilder->buildExec($request);

        $executor = SandboxCommandExecutor::forCodex($this->sandboxDriver, $this->maxRetries, $this->timeout);

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
        } : null;

        $execResult = $executor->executeStreaming($spec, $streamCallback);

        // Parse response
        $response = $this->responseParser->parse($execResult, OutputFormat::Json);

        // Extract text and tools from decoded events if not streaming
        if ($collectedText === '' && $handler === null) {
            foreach ($response->decoded()->all() as $event) {
                $streamEvent = StreamEvent::fromArray($event->data());
                if ($streamEvent instanceof ItemCompletedEvent) {
                    $item = $streamEvent->item;

                    if ($item instanceof AgentMessage) {
                        $collectedText .= $item->text;
                    }

                    if ($item instanceof McpToolCall) {
                        $toolCalls[] = new ToolCall(
                            tool: $item->tool,
                            input: $item->arguments ?? [],
                            output: $item->result !== null ? json_encode($item->result) : null,
                            callId: $item->id,
                            isError: $item->hasError(),
                        );
                    }

                    if ($item instanceof CommandExecution) {
                        $toolCalls[] = new ToolCall(
                            tool: 'bash',
                            input: ['command' => $item->command],
                            output: $item->output,
                            callId: $item->id,
                            isError: $item->exitCode !== 0,
                        );
                    }
                }
            }
        }

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
