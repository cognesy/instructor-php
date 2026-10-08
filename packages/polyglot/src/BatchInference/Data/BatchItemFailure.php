<?php

declare(strict_types=1);

namespace Cognesy\Polyglot\BatchInference\Data;

use Cognesy\Polyglot\BatchInference\Enums\BatchItemFailureKind;

final readonly class BatchItemFailure
{
    public function __construct(
        private BatchItemFailureKind $kind,
        private string $code,
        private string $message,
    ) {
    }

    public function kind(): BatchItemFailureKind
    {
        return $this->kind;
    }
    public function code(): string
    {
        return $this->code;
    }
    public function message(): string
    {
        return $this->message;
    }
}
