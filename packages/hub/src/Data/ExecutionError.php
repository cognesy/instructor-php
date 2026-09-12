<?php declare(strict_types=1);

namespace Cognesy\InstructorHub\Data;

class ExecutionError
{
    private const string LLM_AUTHENTICATION = 'llm_authentication';
    private const string LLM_QUOTA = 'llm_quota';
    private const string LLM_RATE_LIMIT = 'llm_rate_limit';
    private const string LLM_NETWORK = 'llm_network';
    private const string LLM_UNAVAILABLE = 'llm_unavailable';
    private const string LLM_REQUEST_REJECTED = 'llm_request_rejected';

    /** @var array<string, string> */
    private const array LLM_TYPES_BY_EXCEPTION = [
        'Cognesy\\Polyglot\\Inference\\Exceptions\\ProviderAuthenticationException' => self::LLM_AUTHENTICATION,
        'Cognesy\\Polyglot\\Inference\\Exceptions\\ProviderQuotaExceededException' => self::LLM_QUOTA,
        'Cognesy\\Polyglot\\Inference\\Exceptions\\ProviderRateLimitException' => self::LLM_RATE_LIMIT,
        'Cognesy\\Http\\Exceptions\\TimeoutException' => self::LLM_NETWORK,
        'Cognesy\\Http\\Exceptions\\NetworkException' => self::LLM_NETWORK,
        'Cognesy\\Polyglot\\Inference\\Exceptions\\ProviderTransientException' => self::LLM_UNAVAILABLE,
        'Cognesy\\Polyglot\\Inference\\Exceptions\\ProviderInvalidRequestException' => self::LLM_REQUEST_REJECTED,
    ];

    private const array LLM_API_FAILURE_TYPES = [
        self::LLM_AUTHENTICATION,
        self::LLM_QUOTA,
        self::LLM_RATE_LIMIT,
        self::LLM_NETWORK,
        self::LLM_UNAVAILABLE,
    ];

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
        $output = $data['output'] ?? '';
        $type = $data['type'] ?? 'unknown';
        if (in_array($type, ['fatal_error', 'uncaught_exception', 'unknown_error'], true)) {
            $type = self::detectErrorType($output);
        }

        return new self(
            type: $type,
            message: $data['message'] ?? '',
            fullOutput: $output,
            exitCode: $data['exitCode'] ?? 1,
            timestamp: isset($data['timestamp'])
                ? new \DateTimeImmutable($data['timestamp'])
                : new \DateTimeImmutable(),
        );
    }

    private static function detectErrorType(string $output): string
    {
        foreach (self::LLM_TYPES_BY_EXCEPTION as $exception => $type) {
            if (str_contains($output, $exception)) {
                return $type;
            }
        }

        return match(true) {
            str_contains($output, 'AssertionError') => 'assertion',
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

    public function isAssertion(): bool
    {
        return $this->type === 'assertion';
    }

    public function isLlmApiFailure(): bool
    {
        return in_array($this->type, self::LLM_API_FAILURE_TYPES, true);
    }

    public function statusLabel(): ?string
    {
        return match ($this->type) {
            self::LLM_AUTHENTICATION => 'AUTH',
            self::LLM_QUOTA => 'QUOTA',
            self::LLM_RATE_LIMIT => 'RATE',
            self::LLM_NETWORK => 'NETWORK',
            self::LLM_UNAVAILABLE => 'REMOTE',
            self::LLM_REQUEST_REJECTED => 'REQUEST',
            default => null,
        };
    }

    public function typeDescription(): string
    {
        return match ($this->type) {
            self::LLM_AUTHENTICATION => 'LLM API authentication',
            self::LLM_QUOTA => 'LLM API quota',
            self::LLM_RATE_LIMIT => 'LLM API rate limit',
            self::LLM_NETWORK => 'LLM API network',
            self::LLM_UNAVAILABLE => 'LLM API unavailable',
            self::LLM_REQUEST_REJECTED => 'LLM API request rejected',
            default => str_replace('_', ' ', $this->type),
        };
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
