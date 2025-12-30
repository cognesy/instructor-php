<?php declare(strict_types=1);

namespace Cognesy\Utils\Sandbox\Data;

final readonly class ExecResult
{
    public function __construct(
        private string $stdout,
        private string $stderr,
        private int $exitCode,
        private float $duration,
        private bool $timedOut = false,
        private bool $truncatedStdout = false,
        private bool $truncatedStderr = false,
    ) {}

    public function stdout(): string {
        return $this->stdout;
    }

    public function stderr(): string {
        return $this->stderr;
    }

    public function exitCode(): int {
        return $this->exitCode;
    }

    public function duration(): float {
        return $this->duration;
    }

    public function timedOut(): bool {
        return $this->timedOut;
    }

    public function truncatedStdout(): bool {
        return $this->truncatedStdout;
    }

    public function truncatedStderr(): bool {
        return $this->truncatedStderr;
    }

    public function success(): bool {
        return $this->exitCode === 0 && !$this->timedOut;
    }

    public function combinedOutput(): string {
        $out = $this->stdout;
        if ($this->stderr !== '') {
            $out .= ($out !== '' ? "\n" : '') . $this->stderr;
        }
        return $out;
    }

    public function toArray(): array {
        return [
            'stdout' => $this->stdout,
            'stderr' => $this->stderr,
            'exit_code' => $this->exitCode,
            'duration' => $this->duration,
            'timed_out' => $this->timedOut,
            'truncated_stdout' => $this->truncatedStdout,
            'truncated_stderr' => $this->truncatedStderr,
            'success' => $this->success(),
        ];
    }
}

