<?php declare(strict_types=1);

namespace Cognesy\Utils\Sandbox\Runners;

use Cognesy\Utils\Sandbox\Contracts\CanRunProcess;
use Cognesy\Utils\Sandbox\Data\ExecResult;
use Cognesy\Utils\Sandbox\Data\ExitCodes;
use Cognesy\Utils\Sandbox\Utils\ProcUtils;
use Cognesy\Utils\Sandbox\Utils\StreamAggregator;
use Cognesy\Utils\Sandbox\Utils\TimeoutTracker;

final class ProcRunner implements CanRunProcess
{
    public function __construct(
        private readonly TimeoutTracker $tracker,
        private readonly int $stdoutCap,
        private readonly int $stderrCap,
        private readonly string $nameForError = 'process',
    ) {}

    /**
     * Execute a launch argv via proc_open with bounded IO and timeouts.
     *
     * @param list<string> $argv
     * @param array<string,string> $env
     */
    #[\Override]
    public function run(array $argv, string $cwd, array $env, ?string $stdin): ExecResult {
        [$proc, $pipes] = $this->openProcess($argv, $cwd, $env);
        $this->initPipes($pipes, $stdin);
        $agg = $this->makeAggregator();
        $timedOut = $this->pollLoop($proc, $pipes, $agg);
        return $this->finalize($proc, $pipes, $agg, $timedOut);
    }

    // INTERNAL ///////////////////////////////////////////////////////////////////////

    /** @return array{0:resource,1:array<int,resource>} */
    private function openProcess(array $argv, string $cwd, array $env): array {
        $desc = [0 => ['pipe', 'w'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
        $proc = proc_open($argv, $desc, $pipes, $cwd, $env);
        if (!\is_resource($proc)) { throw new \RuntimeException('Failed to start ' . $this->nameForError); }
        return [$proc, $pipes];
    }

    private function initPipes(array $pipes, ?string $stdin): void {
        if ($stdin !== null && $stdin !== '') { @fwrite($pipes[0], $stdin); }
        @fclose($pipes[0]);
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);
        $this->tracker->start();
    }

    private function makeAggregator(): StreamAggregator {
        return new StreamAggregator(stdoutCap: $this->stdoutCap, stderrCap: $this->stderrCap);
    }

    /**
     * @param resource $proc
     * @param array<int,resource> $pipes
     */
    private function pollLoop($proc, array $pipes, StreamAggregator $agg): bool {
        $timedOut = false;
        $status = proc_get_status($proc);
        $pid = $status['pid'];
        while (true) {
            $status = proc_get_status($proc);
            if ($this->tracker->shouldTerminate()) {
                $timedOut = true;
                ProcUtils::terminateProcessGroup($proc, $pid);
                $status = proc_get_status($proc);
            }
            $this->readOnce($pipes, $agg);
            if (!$status['running'] || $timedOut) { break; }
        }
        return $timedOut;
    }

    private function readOnce(array $pipes, StreamAggregator $agg): void {
        $read = [$pipes[1], $pipes[2]]; $write = null; $except = null;
        @stream_select($read, $write, $except, 0, 100_000);
        foreach ($read as $r) {
            $chunk = (string)stream_get_contents($r, 8192);
            if ($chunk !== '') { $this->tracker->onActivity(); }
            if ($r === $pipes[1]) { $agg->appendOut($chunk); continue; }
            $agg->appendErr($chunk);
        }
    }

    /**
     * @param resource $proc
     * @param array<int,resource> $pipes
     */
    private function finalize($proc, array $pipes, StreamAggregator $agg, bool $timedOut): ExecResult {
        $agg->appendOut((string)stream_get_contents($pipes[1]));
        $agg->appendErr((string)stream_get_contents($pipes[2]));
        fclose($pipes[1]);
        fclose($pipes[2]);
        $code = proc_close($proc);
        if ($timedOut) { $code = ExitCodes::TIMEOUT; }
        return new ExecResult(
            stdout: $agg->stdout(),
            stderr: $agg->stderr(),
            exitCode: $code,
            duration: $this->tracker->duration(),
            timedOut: $timedOut,
            truncatedStdout: $agg->truncatedStdout(),
            truncatedStderr: $agg->truncatedStderr(),
        );
    }
}
