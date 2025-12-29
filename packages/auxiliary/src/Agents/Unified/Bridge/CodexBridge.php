<?php declare(strict_types=1);

namespace Cognesy\Auxiliary\Agents\Unified\Bridge;

use Cognesy\Auxiliary\Agents\Common\Enum\SandboxDriver;
use Cognesy\Auxiliary\Agents\Common\Execution\SandboxCommandExecutor;
use Cognesy\Auxiliary\Agents\Common\Value\PathList;
use Cognesy\Auxiliary\Agents\OpenAICodex\Application\Builder\CodexCommandBuilder;
use Cognesy\Auxiliary\Agents\OpenAICodex\Application\Dto\CodexRequest;
use Cognesy\Auxiliary\Agents\OpenAICodex\Application\Parser\ResponseParser;
use Cognesy\Auxiliary\Agents\OpenAICodex\Domain\Dto\StreamEvent\MessageEvent;
use Cognesy\Auxiliary\Agents\OpenAICodex\Domain\Dto\StreamEvent\StreamEvent;
use Cognesy\Auxiliary\Agents\OpenAICodex\Domain\Dto\StreamEvent\ToolCallEvent;
use Cognesy\Auxiliary\Agents\OpenAICodex\Domain\Enum\OutputFormat;
use Cognesy\Auxiliary\Agents\OpenAICodex\Domain\Enum\SandboxMode;
use Cognesy\Auxiliary\Agents\Unified\Contract\AgentBridge;
use Cognesy\Auxiliary\Agents\Unified\Contract\StreamHandler;
use Cognesy\Auxiliary\Agents\Unified\Dto\TokenUsage;
use Cognesy\Auxiliary\Agents\Unified\Dto\ToolCall;
use Cognesy\Auxiliary\Agents\Unified\Dto\AgentResponse;
use Cognesy\Auxiliary\Agents\Unified\Enum\AgentType;

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

        $executor = SandboxCommandExecutor::forCodex($this->sandboxDriver, $this->maxRetries);

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

                if ($event instanceof MessageEvent) {
                    $collectedText .= $event->content;
                    $handler->onText($event->content);
                }

                if ($event instanceof ToolCallEvent) {
                    $toolCall = new ToolCall(
                        tool: $event->name,
                        input: $event->parameters,
                        output: $event->result,
                        callId: $event->id,
                        isError: $event->error !== null,
                    );
                    $toolCalls[] = $toolCall;
                    $handler->onToolUse($toolCall);
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
                if ($streamEvent instanceof MessageEvent) {
                    $collectedText .= $streamEvent->content;
                }
                if ($streamEvent instanceof ToolCallEvent) {
                    $toolCalls[] = new ToolCall(
                        tool: $streamEvent->name,
                        input: $streamEvent->parameters,
                        output: $streamEvent->result,
                        callId: $streamEvent->id,
                        isError: $streamEvent->error !== null,
                    );
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
