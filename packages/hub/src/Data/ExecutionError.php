<?php declare(strict_types=1);

namespace Cognesy\InstructorHub\Data;

class ExecutionError
{
    public function __construct(
        public readonly string $type,
        public readonly string $message,
        public readonly string $fullOutput,
        public readonly int $exitCode,
        public readonly \DateTimeImmutable $timestamp,
    ) {}

    public static function fromException(\Throwable $exception): self
    {
        return new self(
            type: 'exception',
            message: $exception->getMessage(),
            fullOutput: $exception->getTraceAsString(),
            exitCode: $exception->getCode() !== 0 ? $exception->getCode() : 1,
            timestamp: new \DateTimeImmutable(),
        );
    }

    public static function fromOutput(string $output, int $exitCode): self
    {
        return new self(
            type: self::detectErrorType($output),
            message: self::extractErrorMessage($output),
            fullOutput: $output,
            exitCode: $exitCode,
            timestamp: new \DateTimeImmutable(),
        );
    }

    public static function fromArray(array $data): self
    {
        return new self(
            type: $data['type'] ?? 'unknown',
            message: $data['message'] ?? '',
            fullOutput: $data['output'] ?? '',
            exitCode: $data['exitCode'] ?? 1,
            timestamp: isset($data['timestamp'])
                ? new \DateTimeImmutable($data['timestamp'])
                : new \DateTimeImmutable(),
        );
    }

    private static function detectErrorType(string $output): string
    {
        return match(true) {
            str_contains($output, 'Fatal error') => 'fatal_error',
            str_contains($output, 'Parse error') => 'parse_error',
            str_contains($output, 'Uncaught') => 'uncaught_exception',
            str_contains($output, 'Warning') => 'warning',
            str_contains($output, 'Notice') => 'notice',
            str_contains($output, 'Error:') => 'runtime_error',
            default => 'unknown_error',
        };
    }

    private static function extractErrorMessage(string $output): string
    {
        $lines = explode("\n", trim($output));
        foreach ($lines as $line) {
            if (str_contains($line, 'Fatal error') || str_contains($line, 'Parse error') || str_contains($line, 'Error:')) {
                return trim($line);
            }
        }
        return $lines[0] ?? 'Unknown error';
    }

    public function toArray(): array
    {
        return [
            'timestamp' => $this->timestamp->format('c'),
            'type' => $this->type,
            'message' => $this->message,
            'output' => $this->fullOutput,
            'exitCode' => $this->exitCode,
        ];
    }
}
