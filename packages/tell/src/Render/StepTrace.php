<?php

declare(strict_types=1);

namespace Cognesy\Tell\Render;

use Cognesy\Agents\AgentLoop;
use Cognesy\Agents\Enums\ExecutionStatus;
use Cognesy\Agents\Events\AgentExecutionCompleted;
use Cognesy\Agents\Events\AgentStepStarted;
use Cognesy\Agents\Events\InferenceResponseReceived;
use Cognesy\Agents\Events\ToolCallBlocked;
use Cognesy\Agents\Events\ToolCallCompleted;
use Cognesy\Agents\Events\ToolCallStarted;
use Cognesy\Utils\Cli\Color;
use DateTimeImmutable;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * A readable account of one turn as it happens: each step, each tool call with
 * the argument that matters, and each result.
 *
 * This is the one Tell surface that shows tool arguments and results. The
 * normalized event contract is deliberately payload-free because it is a public
 * sink; a trace is the opposite - it exists only for the person who asked for
 * it, is never persisted, and never leaves the invocation's stderr.
 */
final class StepTrace
{
    private const int PREVIEW_LINES = 12;
    private const int PREVIEW_COLUMNS = 160;

    /** @var array<string, DateTimeImmutable> */
    private array $started = [];

    private int $step = 0;
    private bool $wrote = false;

    public function __construct(
        private readonly OutputInterface $stderr,
        private readonly bool $full = false,
    ) {}

    /** Whether this channel has put anything on stderr for the current turn. */
    public function wrote(): bool {
        return $this->wrote;
    }

    public function attach(AgentLoop $loop): void {
        $loop->wiretap(function (object $event): void {
            match (true) {
                $event instanceof AgentStepStarted => $this->stepStarted($event),
                $event instanceof ToolCallStarted => $this->toolStarted($event),
                $event instanceof ToolCallCompleted => $this->toolCompleted($event),
                $event instanceof ToolCallBlocked => $this->toolBlocked($event),
                $event instanceof InferenceResponseReceived => $this->inference($event),
                $event instanceof AgentExecutionCompleted => $this->completed($event),
                default => null,
            };
        });
    }

    private function stepStarted(AgentStepStarted $event): void {
        $this->step = $event->stepNumber;
        $this->line(
            $this->paint('●', Color::CYAN) . ' '
            . $this->paint('step ' . $event->stepNumber, Color::BOLD),
        );
    }

    private function toolStarted(ToolCallStarted $event): void {
        $this->started[$this->key($event->toolCallId, $event->tool)] = $event->startedAt;
        $view = ToolCallView::forCall($event->tool, is_array($event->args) ? $event->args : []);
        $this->line(
            '  ' . $this->paint('▸', Color::CYAN) . ' '
            . $this->paint($view->label, Color::BOLD)
            . ($view->detail === '' ? '' : ' ' . $this->paint($view->detail, Color::DARK_GRAY)),
        );
        $this->body($view->body);
    }

    private function toolCompleted(ToolCallCompleted $event): void {
        $key = $this->key($event->toolCallId, $event->tool);
        $duration = $this->duration($this->started[$key] ?? null, $event->completedAt);
        unset($this->started[$key]);
        $error = ToolResultText::error($event->result);
        $failed = !$event->success || $error !== null;
        $reason = $failed ? $this->reason($event->error, $error) : '';
        $this->line(
            '  ' . ($failed ? $this->paint('✘', Color::RED) : $this->paint('✔', Color::GREEN)) . ' '
            . $this->paint($event->tool, Color::DARK_GRAY)
            . ($duration === '' ? '' : ' ' . $this->paint($duration, Color::DARK_GRAY))
            . ($failed ? ' ' . $this->paint($this->clip($reason), Color::DARK_RED) : ''),
        );
        $body = ToolResultText::from($event->result);
        // Most failing tools put the same sentence in the envelope and in the
        // result text, and a trace that prints an error twice is harder to read
        // than one that prints it once.
        if ($failed && $this->repeats($reason, $body)) {
            return;
        }
        $this->body($body);
    }

    /** True when the result text says nothing the failure reason has not said. */
    private function repeats(string $reason, string $body): bool {
        $body = trim($body);
        if (str_starts_with($body, 'Error:')) {
            $body = trim(substr($body, strlen('Error:')));
        }

        return $body === '' || str_contains($reason, $body);
    }

    private function toolBlocked(ToolCallBlocked $event): void {
        $this->line(
            '  ' . $this->paint('⊘', Color::YELLOW) . ' '
            . $this->paint($event->tool, Color::DARK_GRAY) . ' '
            . $this->paint('blocked: ' . $event->reason, Color::DARK_YELLOW),
        );
    }

    private function inference(InferenceResponseReceived $event): void {
        $usage = $event->usage;
        $tokens = $usage === null
            ? 'inference completed'
            : 'inference ' . $usage->inputTokens . ' in / ' . $usage->outputTokens . ' out';
        $this->line(
            '  ' . $this->paint('·', Color::DARK_GRAY) . ' '
            . $this->paint(
                $tokens . ($event->finishReason === null || $event->finishReason === '' ? '' : ', ' . $event->finishReason),
                Color::DARK_GRAY,
            ),
        );
    }

    private function completed(AgentExecutionCompleted $event): void {
        $marker = $event->status === ExecutionStatus::Completed ? Color::CYAN : Color::RED;
        $this->line(
            $this->paint('●', $marker) . ' '
            . $this->paint($event->status->value, Color::BOLD) . ' '
            . $this->paint(
                $event->totalSteps . ' steps, '
                . $event->totalUsage->inputTokens . ' in / ' . $event->totalUsage->outputTokens . ' out tokens',
                Color::DARK_GRAY,
            ),
        );
    }

    /**
     * Bodies are previewed, not dumped: a trace that scrolls a shell result off
     * the screen hides the steps it exists to show. The elided count is stated
     * so the reader knows the preview is one, and -vvv turns previewing off.
     */
    private function body(string $text): void {
        $text = rtrim($text, "\n");
        if ($text === '') {
            return;
        }
        $lines = explode("\n", $text);
        $shown = $this->full ? $lines : array_slice($lines, 0, self::PREVIEW_LINES);
        foreach ($shown as $line) {
            $this->line('    ' . $this->paint('│', Color::DARK_GRAY) . ' ' . $this->clip($line));
        }
        $hidden = count($lines) - count($shown);
        if ($hidden > 0) {
            $this->line('    ' . $this->paint('⋯ ' . $hidden . ' more line' . ($hidden === 1 ? '' : 's'), Color::DARK_GRAY));
        }
    }

    private function clip(string $line): string {
        $line = str_replace("\t", '    ', rtrim($line, "\r"));
        if ($this->full || mb_strlen($line) <= self::PREVIEW_COLUMNS) {
            return $line;
        }

        return mb_substr($line, 0, self::PREVIEW_COLUMNS - 1) . '…';
    }

    private function reason(?string $error, ?array $structured): string {
        return match (true) {
            is_string($error) && $error !== '' => $error,
            $structured !== null && $structured['message'] !== '' => $structured['code'] . ': ' . $structured['message'],
            $structured !== null => $structured['code'],
            default => 'failed',
        };
    }

    private function duration(?DateTimeImmutable $started, DateTimeImmutable $completed): string {
        if ($started === null) {
            return '';
        }
        $ms = max(0, (int) round(((float) $completed->format('U.u') - (float) $started->format('U.u')) * 1000));

        return $ms . 'ms';
    }

    /**
     * A tool call id is the reliable pairing between a start and its completion;
     * concurrent calls to the same tool would otherwise share one timer.
     */
    private function key(string $toolCallId, string $tool): string {
        return $toolCallId === '' ? 'tool:' . $tool . ':' . $this->step : $toolCallId;
    }

    private function paint(string $text, string $color): string {
        return $this->stderr->isDecorated() ? $color . $text . Color::RESET : $text;
    }

    /**
     * OUTPUT_RAW: trace lines carry their own escape sequences, and tool
     * arguments and results routinely contain angle brackets that the console
     * formatter would otherwise read as its own markup.
     */
    private function line(string $text): void {
        $this->stderr->write($text . "\n", false, OutputInterface::OUTPUT_RAW);
        $this->wrote = true;
    }
}
