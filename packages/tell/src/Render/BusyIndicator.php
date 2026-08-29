<?php

declare(strict_types=1);

namespace Cognesy\Tell\Render;

use Cognesy\Agents\AgentLoop;
use Cognesy\Agents\Events\AgentExecutionCompleted;
use Cognesy\Agents\Events\AgentExecutionStarted;
use Cognesy\Agents\Events\AgentStepStarted;
use Cognesy\Agents\Events\InferenceRequestStarted;
use Cognesy\Agents\Events\InferenceResponseReceived;
use Cognesy\Agents\Events\ToolCallCompleted;
use Cognesy\Agents\Events\ToolCallStarted;
use Cognesy\Utils\Cli\Color;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Terminal;

/**
 * One self-erasing line saying what the turn is doing right now.
 *
 * A reader at a terminal who asked for no progress channel still needs to know
 * the turn is alive and what it is waiting on. This is that, and only that: it
 * occupies a single line, it names the current step and tool, and it leaves the
 * terminal exactly as it found it so the answer prints into clean space.
 *
 * The frames animate from a forked drawer because a PHP turn spends most of its
 * wall clock blocked inside the inference request, where no in-process timer,
 * signal handler, or event can run. A ticker driven by events alone would stop
 * moving during precisely the wait that needs an indicator.
 */
final class BusyIndicator
{
    /** @var list<string> */
    private const array FRAMES = ['⠋', '⠙', '⠹', '⠸', '⠼', '⠴', '⠦', '⠧', '⠇', '⠏'];

    private const int TICK_MICROSECONDS = 100_000;
    private const string STOP = "\x04";

    private string $status = 'starting';
    private int $step = 0;

    /** @var resource|null */
    private mixed $channel = null;

    private int $drawer = 0;
    private float $startedAt = 0.0;
    private int $frame = 0;
    private bool $isDrawer = false;
    private bool $suspended = false;
    private int $width = 0;
    private readonly bool $decorated;

    public function __construct(private readonly OutputInterface $stderr) {
        $this->decorated = $stderr->isDecorated();
    }

    /**
     * Animation needs a real terminal to draw on and a process to draw from.
     * Without either, the indicator still reports status; it just does not spin.
     */
    public static function canAnimate(): bool {
        return function_exists('pcntl_fork')
            && function_exists('pcntl_waitpid')
            && function_exists('posix_kill')
            && function_exists('posix_getppid')
            && defined('SIGKILL')
            && stream_isatty(STDERR);
    }

    public function attach(AgentLoop $loop): void {
        $this->startedAt = microtime(true);
        $this->spawn();
        $loop->wiretap(function (object $event): void {
            match (true) {
                $event instanceof AgentExecutionStarted => $this->show('starting'),
                $event instanceof AgentStepStarted => $this->step($event),
                $event instanceof InferenceRequestStarted => $this->show('thinking'),
                $event instanceof InferenceResponseReceived => $this->show('deciding'),
                $event instanceof ToolCallStarted => $this->toolStarted($event),
                $event instanceof ToolCallCompleted => $this->toolCompleted($event),
                $event instanceof AgentExecutionCompleted => $this->stop(),
                default => null,
            };
        });
    }

    /**
     * Stopping is idempotent: the turn may end on a completion event, on an
     * error, or on a cancellation, and every one of those paths has to leave the
     * line erased rather than stranded mid-frame.
     */
    public function stop(): void {
        if ($this->drawer !== 0) {
            $this->send(self::STOP);
            $this->reap();
        }
        if ($this->channel !== null) {
            fclose($this->channel);
            $this->channel = null;
        }
        $this->erase();
    }

    private function step(AgentStepStarted $event): void {
        $this->step = $event->stepNumber;
        $this->show('thinking');
    }

    /**
     * A tool that talks to the person owns the terminal while it does, so the
     * indicator gets out of the way rather than drawing over a question.
     */
    private function toolStarted(ToolCallStarted $event): void {
        if ($event->tool === 'ask_user') {
            $this->suspended = true;
            $this->stop();

            return;
        }
        $this->show($this->tool($event));
    }

    private function toolCompleted(ToolCallCompleted $event): void {
        if ($event->tool === 'ask_user' && $this->suspended) {
            $this->suspended = false;
            $this->spawn();
        }
        $this->show('working');
    }

    private function tool(ToolCallStarted $event): string {
        $view = ToolCallView::forCall($event->tool, is_array($event->args) ? $event->args : []);
        $detail = trim($view->detail, " \t`[]");

        return $detail === '' ? $view->label : $view->label . ': ' . $detail;
    }

    private function show(string $status): void {
        $this->status = $status;
        if ($this->suspended) {
            return;
        }
        if ($this->drawer !== 0) {
            $this->send($this->label() . "\n");

            return;
        }
        // Without a drawer the line is still worth painting; it simply advances
        // when something happens rather than on a clock.
        $this->frame = ($this->frame + 1) % count(self::FRAMES);
        $this->paint(self::FRAMES[$this->frame], $this->label(), microtime(true) - $this->startedAt);
    }

    private function label(): string {
        return $this->step === 0 ? $this->status : 'step ' . $this->step . ' · ' . $this->status;
    }

    /**
     * The parent owns the words and the child owns the clock, so the two are
     * passed over a socket rather than shared: a forked child cannot see any
     * state the parent changes after the fork.
     */
    private function spawn(): void {
        if (!self::canAnimate()) {
            return;
        }
        $pair = @stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, 0);
        if ($pair === false) {
            return;
        }
        $parent = posix_getpid();
        $pid = @pcntl_fork();
        if ($pid === -1) {
            fclose($pair[0]);
            fclose($pair[1]);

            return;
        }
        if ($pid === 0) {
            fclose($pair[0]);
            $this->draw($pair[1], $parent);
        }
        fclose($pair[1]);
        $this->channel = $pair[0];
        $this->drawer = $pid;
        $this->send($this->label() . "\n");
    }

    /**
     * The drawer never returns: it exits by signal so that none of the parent
     * state it inherited - open workspaces, locks, temporary directories - gets
     * torn down a second time by a PHP shutdown running in the wrong process.
     *
     * @param  resource  $channel
     */
    private function draw(mixed $channel, int $parent): never {
        $this->isDrawer = true;
        stream_set_blocking($channel, false);
        $label = $this->label();
        $frame = 0;
        $startedAt = microtime(true);
        while (true) {
            $message = stream_get_contents($channel);
            if (is_string($message) && $message !== '') {
                if (str_contains($message, self::STOP)) {
                    break;
                }
                $lines = array_values(array_filter(explode("\n", $message), static fn (string $l): bool => $l !== ''));
                $label = $lines === [] ? $label : (string) end($lines);
            }
            // A drawer outliving its parent would spin against a terminal that
            // has moved on, so it checks who it belongs to on every frame.
            if (feof($channel) || posix_getppid() !== $parent) {
                break;
            }
            $this->paint(self::FRAMES[$frame], $label, microtime(true) - $startedAt);
            $frame = ($frame + 1) % count(self::FRAMES);
            usleep(self::TICK_MICROSECONDS);
        }
        $this->erase();
        posix_kill(posix_getpid(), SIGKILL);
        exit(0);
    }

    private function paint(string $frame, string $label, float $elapsed): void {
        $line = $frame . ' ' . $label . '  ' . $this->elapsed($elapsed);
        $this->width = $this->width !== 0 ? $this->width : max(20, (new Terminal())->getWidth() - 1);
        if (mb_strlen($line) > $this->width) {
            $line = mb_substr($line, 0, $this->width - 1) . '…';
        }
        $this->write("\r\033[K" . ($this->decorated ? Color::DARK_GRAY . $line . Color::RESET : $line));
    }

    private function erase(): void {
        $this->write("\r\033[K");
    }

    private function elapsed(float $seconds): string {
        $whole = (int) $seconds;

        return $whole < 60 ? $whole . 's' : intdiv($whole, 60) . 'm' . str_pad((string) ($whole % 60), 2, '0', STR_PAD_LEFT) . 's';
    }

    /**
     * The drawer writes to the descriptor directly because it must not touch a
     * console object it shares with a parent that is writing at the same time.
     */
    private function write(string $text): void {
        if ($this->isDrawer) {
            fwrite(STDERR, $text);

            return;
        }
        $this->stderr->write($text, false, OutputInterface::OUTPUT_RAW);
    }

    private function send(string $message): void {
        if ($this->channel !== null) {
            @fwrite($this->channel, $message);
        }
    }

    /** Give the drawer its tick to notice the stop, then insist. */
    private function reap(): void {
        for ($waited = 0; $waited < 20; $waited++) {
            if (pcntl_waitpid($this->drawer, $status, WNOHANG) !== 0) {
                $this->drawer = 0;

                return;
            }
            usleep(intdiv(self::TICK_MICROSECONDS, 4));
        }
        posix_kill($this->drawer, SIGKILL);
        pcntl_waitpid($this->drawer, $status);
        $this->drawer = 0;
    }
}
