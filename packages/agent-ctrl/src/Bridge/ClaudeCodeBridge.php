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
    ) {
        $this->commandBuilder = new ClaudeCommandBuilder();
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
        $spec = $this->commandBuilder->buildHeadless($request);

        $executor = SandboxCommandExecutor::forClaudeCode($this->sandboxDriver, $this->maxRetries, $this->timeout);

        $collectedText = '';
        $toolCalls = [];
        $sessionId = null;

        $streamCallback = $handler !== null ? function (string $type, string $chunk) use ($handler, &$collectedText, &$toolCalls, &$sessionId): void {
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
        } : null;

        $execResult = $executor->executeStreaming($spec, $streamCallback);

        // Parse response for non-streaming or to extract final data
        $response = $this->responseParser->parse($execResult, OutputFormat::StreamJson);

        // Extract text from decoded events if not collected via streaming
        if ($collectedText === '' && $handler === null) {
            foreach ($response->decoded()->all() as $event) {
                $streamEvent = StreamEvent::fromArray($event->data());
                if ($streamEvent instanceof MessageEvent) {
                    foreach ($streamEvent->message->textContent() as $textContent) {
                        $collectedText .= $textContent->text;
                    }
                    foreach ($streamEvent->message->toolUses() as $toolUse) {
                        $toolCalls[] = new ToolCall(
                            tool: $toolUse->name,
                            input: $toolUse->input,
                            callId: $toolUse->id,
                        );
                    }
                }
            }
        }

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
