<?php declare(strict_types=1);

namespace Cognesy\Instructor\Core;

use Cognesy\Instructor\Data\StructuredOutputExecution;
use Cognesy\Instructor\Data\StructuredOutputResponse;

final readonly class AttemptProcessingResult
{
    private function __construct(
        private StructuredOutputExecution $execution,
        private ?StructuredOutputResponse $response = null,
        private bool $shouldRetry = false,
    ) {}

    public static function retry(StructuredOutputExecution $execution): self
    {
        return new self(
            execution: $execution,
            shouldRetry: true,
        );
    }

    public static function terminal(
        StructuredOutputExecution $execution,
        StructuredOutputResponse $response,
    ): self {
        return new self(
            execution: $execution,
            response: $response,
            shouldRetry: false,
        );
    }

    public function execution(): StructuredOutputExecution
    {
        return $this->execution;
    }

    public function response(): ?StructuredOutputResponse
    {
        return $this->response;
    }

    public function shouldRetry(): bool
    {
        return $this->shouldRetry;
    }
}
