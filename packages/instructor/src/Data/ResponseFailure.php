<?php declare(strict_types=1);

namespace Cognesy\Instructor\Data;

use Cognesy\Instructor\Enums\ResponseFailureStage;
use JsonSerializable;
use Stringable;
use Throwable;

/**
 * Stage-aware structured-output failure carried through attempts and retries.
 */
final readonly class ResponseFailure implements JsonSerializable, Stringable
{
    /**
     * @param array<string, bool|float|int|string|null> $context
     */
    public function __construct(
        public ResponseFailureStage $stage,
        public string $message,
        public string $errorType,
        public ?Throwable $cause = null,
        public array $context = [],
    ) {}

    /**
     * @param array<string, bool|float|int|string|null> $context
     */
    public static function fromError(
        ResponseFailureStage $stage,
        mixed $error,
        array $context = [],
    ): self {
        if ($error instanceof self) {
            return $error;
        }

        return new self(
            stage: $stage,
            message: self::messageFor($error),
            errorType: self::typeFor($error),
            cause: $error instanceof Throwable ? $error : null,
            context: $context,
        );
    }

    public static function fromArray(array $data): self
    {
        return new self(
            stage: ResponseFailureStage::from((string) $data['stage']),
            message: (string) ($data['message'] ?? ''),
            errorType: (string) ($data['errorType'] ?? 'unknown'),
            context: is_array($data['context'] ?? null) ? $data['context'] : [],
        );
    }

    public function safeMessage(): string
    {
        return "Structured output {$this->stage->value} failed.";
    }

    /**
     * Content-safe data for events and telemetry. The diagnostic message and
     * throwable stay on the failure itself for retry and exception handling.
     *
     * @return array<string, mixed>
     */
    public function eventData(): array
    {
        return [
            'stage' => $this->stage->value,
            'errorMessage' => $this->safeMessage(),
            'errorType' => $this->errorType,
            'context' => $this->context,
        ];
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'stage' => $this->stage->value,
            'message' => $this->message,
            'errorType' => $this->errorType,
            'context' => $this->context,
        ];
    }

    #[\Override]
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    #[\Override]
    public function __toString(): string
    {
        return "[{$this->stage->value}] {$this->message}";
    }

    private static function messageFor(mixed $error): string
    {
        return match (true) {
            $error instanceof Throwable => $error->getMessage(),
            $error instanceof Stringable => (string) $error,
            is_string($error) => $error,
            is_array($error) => implode('; ', array_map(self::messageFor(...), $error)),
            is_scalar($error) || $error === null => var_export($error, true),
            default => get_debug_type($error),
        };
    }

    private static function typeFor(mixed $error): string
    {
        return match (true) {
            $error instanceof Throwable => $error::class,
            is_object($error) => $error::class,
            default => get_debug_type($error),
        };
    }
}
