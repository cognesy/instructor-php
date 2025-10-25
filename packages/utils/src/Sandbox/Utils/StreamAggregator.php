<?php declare(strict_types=1);

namespace Cognesy\Utils\Sandbox\Utils;

use Symfony\Component\Process\Process;

final class StreamAggregator
{
    private string $stdout = '';
    private string $stderr = '';
    private bool $truncOut = false;
    private bool $truncErr = false;

    public function __construct(
        private readonly int $stdoutCap,
        private readonly int $stderrCap,
    ) {}

    public function consume(string $type, string $buffer): void {
        $isOut = $type === Process::OUT;
        if ($isOut) {
            $this->appendBounded($this->stdout, $buffer, $this->stdoutCap, $this->truncOut);
            return;
        }
        $this->appendBounded($this->stderr, $buffer, $this->stderrCap, $this->truncErr);
    }

    // Direct variants for proc_open-based drivers (no Symfony type constants needed)
    public function appendOut(string $buffer): void {
        $this->appendBounded($this->stdout, $buffer, $this->stdoutCap, $this->truncOut);
    }

    public function appendErr(string $buffer): void {
        $this->appendBounded($this->stderr, $buffer, $this->stderrCap, $this->truncErr);
    }

    public function stdout(): string {
        return $this->stdout;
    }

    public function stderr(): string {
        return $this->stderr;
    }

    public function truncatedStdout(): bool {
        return $this->truncOut;
    }

    public function truncatedStderr(): bool {
        return $this->truncErr;
    }

    // INTERNAL ///////////////////////////////////////////////////////////////////

    private function appendBounded(string &$target, string $chunk, int $cap, bool &$truncated): void {
        if ($truncated) {
            return;
        }
        $target .= $chunk;
        if (strlen($target) > $cap) {
            $target = substr($target, -$cap);
            $truncated = true;
        }
    }
}
