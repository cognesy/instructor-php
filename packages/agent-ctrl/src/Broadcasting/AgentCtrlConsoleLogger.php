<?php declare(strict_types=1);

namespace Cognesy\AgentCtrl\Broadcasting;

use Cognesy\AgentCtrl\Event\AgentErrorOccurred;
use Cognesy\AgentCtrl\Event\AgentExecutionCompleted;
use Cognesy\AgentCtrl\Event\AgentExecutionStarted;
use Cognesy\AgentCtrl\Event\AgentTextReceived;
use Cognesy\AgentCtrl\Event\AgentToolUsed;
use Cognesy\AgentCtrl\Event\CommandSpecCreated;
use Cognesy\AgentCtrl\Event\ExecutionAttempted;
use Cognesy\AgentCtrl\Event\ProcessExecutionCompleted;
use Cognesy\AgentCtrl\Event\ProcessExecutionStarted;
use Cognesy\AgentCtrl\Event\RequestBuilt;
use Cognesy\AgentCtrl\Event\ResponseDataExtracted;
use Cognesy\AgentCtrl\Event\ResponseParsingCompleted;
use Cognesy\AgentCtrl\Event\ResponseParsingStarted;
use Cognesy\AgentCtrl\Event\SandboxInitialized;
use Cognesy\AgentCtrl\Event\SandboxPolicyConfigured;
use Cognesy\AgentCtrl\Event\SandboxReady;
use Cognesy\AgentCtrl\Event\StreamChunkProcessed;
use Cognesy\AgentCtrl\Event\StreamProcessingCompleted;
use Cognesy\AgentCtrl\Event\StreamProcessingStarted;
use Cognesy\Events\Event;
use DateTimeImmutable;

/**
 * Simple console logger for agent-ctrl events.
 *
 * Provides developer-friendly output showing CLI agent execution stages:
 * - Execution start/end
 * - Tool usage
 * - Text output
 * - Process execution and retries
 * - Sandbox setup
 * - Stream processing
 * - Request/response pipeline
 *
 * Usage:
 *   $logger = new AgentCtrlConsoleLogger();
 *   $response = AgentCtrl::claudeCode()
 *       ->wiretap($logger->wiretap())
 *       ->execute('Fix the login bug');
 */
final class AgentCtrlConsoleLogger
{
    private bool $useColors;
    private bool $showTimestamps;
    private bool $showAgentType;
    private bool $showToolArgs;
    private bool $showStreaming;
    private bool $showSandbox;
    private bool $showPipeline;
    private int $maxArgLength;

    private ?string $currentAgentType = null;

    public function __construct(
        bool $useColors = true,
        bool $showTimestamps = true,
        bool $showAgentType = true,
        bool $showToolArgs = true,
        bool $showStreaming = false,
        bool $showSandbox = false,
        bool $showPipeline = false,
        int $maxArgLength = 100,
    ) {
        $this->useColors = $useColors && $this->supportsColors();
        $this->showTimestamps = $showTimestamps;
        $this->showAgentType = $showAgentType;
        $this->showToolArgs = $showToolArgs;
        $this->showStreaming = $showStreaming;
        $this->showSandbox = $showSandbox;
        $this->showPipeline = $showPipeline;
        $this->maxArgLength = $maxArgLength;
    }

    /**
     * Returns a wiretap callable for use with AbstractBridgeBuilder::wiretap()
     */
    public function wiretap(): callable
    {
        return function (Event $event): void {
            match (true) {
                // Core lifecycle events
                $event instanceof AgentExecutionStarted => $this->onExecutionStarted($event),
                $event instanceof AgentExecutionCompleted => $this->onExecutionCompleted($event),
                $event instanceof AgentErrorOccurred => $this->onErrorOccurred($event),
                // Tool and text events
                $event instanceof AgentToolUsed => $this->onToolUsed($event),
                $event instanceof AgentTextReceived => $this->onTextReceived($event),
                // Process execution events
                $event instanceof ProcessExecutionStarted => $this->onProcessStarted($event),
                $event instanceof ProcessExecutionCompleted => $this->onProcessCompleted($event),
                $event instanceof ExecutionAttempted => $this->onExecutionAttempted($event),
                // Sandbox events
                $event instanceof SandboxInitialized => $this->onSandboxInitialized($event),
                $event instanceof SandboxPolicyConfigured => $this->onSandboxPolicyConfigured($event),
                $event instanceof SandboxReady => $this->onSandboxReady($event),
                // Stream events
                $event instanceof StreamProcessingStarted => $this->onStreamStarted($event),
                $event instanceof StreamProcessingCompleted => $this->onStreamCompleted($event),
                $event instanceof StreamChunkProcessed => null, // skipped - too noisy
                // Pipeline events
                $event instanceof RequestBuilt => $this->onRequestBuilt($event),
                $event instanceof CommandSpecCreated => $this->onCommandSpecCreated($event),
                $event instanceof ResponseParsingStarted => $this->onResponseParsingStarted($event),
                $event instanceof ResponseDataExtracted => $this->onResponseDataExtracted($event),
                $event instanceof ResponseParsingCompleted => $this->onResponseParsingCompleted($event),
                default => null,
            };
        };
    }

    // -- Core lifecycle events ------------------------------------------------

    private function onExecutionStarted(AgentExecutionStarted $event): void
    {
        $this->currentAgentType = $event->agentType->value;

        $details = [];
        if ($event->model) {
            $details[] = sprintf('model=%s', $event->model);
        }
        $details[] = sprintf('prompt=%s', $this->truncate($event->prompt, 80));

        $this->log('EXEC', 'cyan', sprintf(
            'Execution started [%s]',
            implode(', ', $details),
        ), $event->agentType->value);
    }

    private function onExecutionCompleted(AgentExecutionCompleted $event): void
    {
        $details = [
            sprintf('exit=%d', $event->exitCode),
            sprintf('tools=%d', $event->toolCallCount),
        ];

        if ($event->cost !== null) {
            $details[] = sprintf('cost=$%.4f', $event->cost);
        }

        $tokens = ($event->inputTokens ?? 0) + ($event->outputTokens ?? 0);
        if ($tokens > 0) {
            $details[] = sprintf('tokens=%d', $tokens);
        }

        $this->log('DONE', 'green', sprintf(
            'Execution completed [%s]',
            implode(', ', $details),
        ), $event->agentType->value);
    }

    private function onErrorOccurred(AgentErrorOccurred $event): void
    {
        $details = [
            sprintf('error=%s', $this->truncate($event->error, 100)),
        ];

        if ($event->errorClass) {
            $details[] = sprintf('class=%s', $this->shortClassName($event->errorClass));
        }

        if ($event->exitCode !== null) {
            $details[] = sprintf('exit=%d', $event->exitCode);
        }

        $this->log('FAIL', 'red', sprintf(
            'Error occurred [%s]',
            implode(', ', $details),
        ), $event->agentType->value);
    }

    // -- Tool and text events ------------------------------------------------

    private function onToolUsed(AgentToolUsed $event): void
    {
        $argsStr = '';
        if ($this->showToolArgs && $event->input !== []) {
            $argsStr = ' ' . $this->formatArgs($event->input);
        }

        $this->log('TOOL', 'yellow', sprintf(
            '%s%s',
            $event->tool,
            $argsStr,
        ), $event->agentType->value);
    }

    private function onTextReceived(AgentTextReceived $event): void
    {
        $this->log('TEXT', 'dark', sprintf(
            'Text received [length=%d]',
            strlen($event->text),
        ), $event->agentType->value);
    }

    // -- Process execution events --------------------------------------------

    private function onProcessStarted(ProcessExecutionStarted $event): void
    {
        $this->log('PROC', 'cyan', sprintf(
            'Process started [commands=%d]',
            $event->commandCount,
        ), $event->agentType->value);
    }

    private function onProcessCompleted(ProcessExecutionCompleted $event): void
    {
        $this->log('PROC', 'cyan', sprintf(
            'Process completed [attempts=%d, success=#%d, duration=%.0fms]',
            $event->totalAttempts,
            $event->successAttempt,
            $event->totalExecutionDurationMs,
        ), $event->agentType->value);
    }

    private function onExecutionAttempted(ExecutionAttempted $event): void
    {
        $hasError = $event->error !== null;
        $color = $hasError ? 'red' : 'yellow';

        $details = [
            sprintf('attempt=#%d', $event->attemptNumber),
            sprintf('duration=%.0fms', $event->executionDurationMs),
        ];

        if ($hasError) {
            $details[] = sprintf('error=%s', $this->truncate($event->error, 80));
        }

        if ($event->willRetry) {
            $details[] = 'will_retry';
        }

        $this->log('RTRY', $color, sprintf(
            'Execution attempted [%s]',
            implode(', ', $details),
        ), $event->agentType->value);
    }

    // -- Sandbox events ------------------------------------------------------

    private function onSandboxInitialized(SandboxInitialized $event): void
    {
        if (!$this->showSandbox) {
            return;
        }

        $this->log('SBOX', 'blue', sprintf(
            'Initialized [driver=%s, duration=%.0fms]',
            $event->driver,
            $event->initializationDurationMs,
        ), $event->agentType->value);
    }

    private function onSandboxPolicyConfigured(SandboxPolicyConfigured $event): void
    {
        if (!$this->showSandbox) {
            return;
        }

        $this->log('SBOX', 'blue', sprintf(
            'Policy configured [driver=%s, timeout=%ds, network=%s]',
            $event->driver,
            $event->timeout,
            $event->networkEnabled ? 'on' : 'off',
        ), $event->agentType->value);
    }

    private function onSandboxReady(SandboxReady $event): void
    {
        if (!$this->showSandbox) {
            return;
        }

        $this->log('SBOX', 'blue', sprintf(
            'Ready [driver=%s, setup=%.0fms]',
            $event->driver,
            $event->totalSetupDurationMs,
        ), $event->agentType->value);
    }

    // -- Stream events -------------------------------------------------------

    private function onStreamStarted(StreamProcessingStarted $event): void
    {
        if (!$this->showStreaming) {
            return;
        }

        $this->log('STRM', 'dark', 'Stream processing started', $event->agentType->value);
    }

    private function onStreamCompleted(StreamProcessingCompleted $event): void
    {
        if (!$this->showStreaming) {
            return;
        }

        $this->log('STRM', 'dark', sprintf(
            'Stream completed [chunks=%d, bytes=%d, duration=%.0fms]',
            $event->totalChunks,
            $event->bytesProcessed,
            $event->totalDurationMs,
        ), $event->agentType->value);
    }

    // -- Pipeline events -----------------------------------------------------

    private function onRequestBuilt(RequestBuilt $event): void
    {
        if (!$this->showPipeline) {
            return;
        }

        $this->log('REQT', 'dark', sprintf(
            'Request built [type=%s, duration=%.0fms]',
            $event->requestType,
            $event->buildDurationMs,
        ), $event->agentType->value);
    }

    private function onCommandSpecCreated(CommandSpecCreated $event): void
    {
        if (!$this->showPipeline) {
            return;
        }

        $this->log('CMD ', 'dark', sprintf(
            'Command spec created [args=%d, duration=%.0fms]',
            $event->argvCount,
            $event->commandDurationMs,
        ), $event->agentType->value);
    }

    private function onResponseParsingStarted(ResponseParsingStarted $event): void
    {
        if (!$this->showPipeline) {
            return;
        }

        $this->log('RESP', 'dark', sprintf(
            'Parsing started [format=%s, size=%d]',
            $event->format,
            $event->responseSize,
        ), $event->agentType->value);
    }

    private function onResponseDataExtracted(ResponseDataExtracted $event): void
    {
        if (!$this->showPipeline) {
            return;
        }

        $this->log('RESP', 'dark', sprintf(
            'Data extracted [events=%d, tools=%d, text=%d chars, duration=%.0fms]',
            $event->eventCount,
            $event->toolUseCount,
            $event->textLength,
            $event->extractDurationMs,
        ), $event->agentType->value);
    }

    private function onResponseParsingCompleted(ResponseParsingCompleted $event): void
    {
        if (!$this->showPipeline) {
            return;
        }

        $sessionInfo = $event->sessionId ? sprintf(', session=%s', $event->sessionId) : '';

        $this->log('RESP', 'dark', sprintf(
            'Parsing completed [duration=%.0fms%s]',
            $event->totalDurationMs,
            $sessionInfo,
        ), $event->agentType->value);
    }

    // -- Output helpers -------------------------------------------------------

    private function log(string $label, string $color, string $message, ?string $agentType = null): void
    {
        $timestamp = '';
        if ($this->showTimestamps) {
            $timestamp = (new DateTimeImmutable())->format('H:i:s.v') . ' ';
        }

        $agentInfo = '';
        if ($this->showAgentType && ($agentType ?? $this->currentAgentType) !== null) {
            $agentInfo = sprintf('[%s] ', $agentType ?? $this->currentAgentType);
        }

        $labelFormatted = $this->colorize(str_pad($label, 4), $color);

        echo sprintf("%s%s[%s] %s\n", $timestamp, $agentInfo, $labelFormatted, $message);
    }

    private function colorize(string $text, string $color): string
    {
        if (!$this->useColors) {
            return $text;
        }

        $codes = [
            'red' => '31',
            'green' => '32',
            'yellow' => '33',
            'blue' => '34',
            'magenta' => '35',
            'cyan' => '36',
            'dark' => '90',
        ];

        $code = $codes[$color] ?? '0';
        return "\033[{$code}m{$text}\033[0m";
    }

    private function supportsColors(): bool
    {
        if (DIRECTORY_SEPARATOR === '\\') {
            return false !== getenv('ANSICON')
                || 'ON' === getenv('ConEmuANSI')
                || str_starts_with((string) getenv('TERM'), 'xterm');
        }

        return function_exists('posix_isatty') && @posix_isatty(STDOUT);
    }

    private function truncate(string $text, int $maxLength): string
    {
        if (strlen($text) <= $maxLength) {
            return $text;
        }
        return substr($text, 0, $maxLength - 3) . '...';
    }

    private function formatArgs(array $args): string
    {
        $parts = [];
        foreach (array_slice($args, 0, 5, true) as $key => $value) {
            $valueStr = match (true) {
                is_string($value) => $this->truncate($value, $this->maxArgLength),
                is_bool($value) => $value ? 'true' : 'false',
                is_null($value) => 'null',
                is_array($value) => '[array]',
                is_object($value) => '[object]',
                default => (string) $value,
            };
            $parts[] = "{$key}={$valueStr}";
        }

        $more = count($args) > 5 ? sprintf(' (+%d more)', count($args) - 5) : '';
        return '{' . implode(', ', $parts) . $more . '}';
    }

    private function shortClassName(string $fqcn): string
    {
        $parts = explode('\\', $fqcn);
        return end($parts);
    }
}
