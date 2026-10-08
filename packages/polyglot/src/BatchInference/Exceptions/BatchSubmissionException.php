<?php

declare(strict_types=1);

namespace Cognesy\Polyglot\BatchInference\Exceptions;

use Cognesy\Polyglot\BatchInference\Enums\BatchMutationCertainty;
use Throwable;

final class BatchSubmissionException extends BatchException
{
    /** @param array<string, string> $artifactIds */
    public function __construct(
        string $message,
        private readonly string $stage,
        private readonly BatchMutationCertainty $certainty,
        private readonly array $artifactIds = [],
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, previous: $previous);
    }

    public function stage(): string
    {
        return $this->stage;
    }
    public function certainty(): BatchMutationCertainty
    {
        return $this->certainty;
    }

    /** @return array<string, string> */
    public function artifactIds(): array
    {
        return $this->artifactIds;
    }
}
