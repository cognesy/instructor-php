<?php declare(strict_types=1);

namespace Cognesy\Agents\Evals;

final readonly class EvalLog
{
    /** @param array<string, mixed> $context */
    public function __construct(
        private string $message,
        private array $context = [],
    ) {}

    public function message(): string {
        return $this->message;
    }

    /** @return array<string, mixed> */
    public function context(): array {
        return $this->context;
    }

    /** @return array{message: string, context: array<string, mixed>} */
    public function toArray(): array {
        return ['message' => $this->message, 'context' => $this->context];
    }
}
