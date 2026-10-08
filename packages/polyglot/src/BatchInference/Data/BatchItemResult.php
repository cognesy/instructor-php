<?php

declare(strict_types=1);

namespace Cognesy\Polyglot\BatchInference\Data;

use Cognesy\Polyglot\Inference\Data\InferenceResponse;
use Cognesy\Utils\Result\Result;

final readonly class BatchItemResult
{
    /** @param Result<InferenceResponse, BatchItemFailure> $result */
    public function __construct(
        private ?string $key,
        private Result $result,
        private BatchItemProvenance $provenance,
    ) {
    }

    public function key(): ?string
    {
        return $this->key;
    }

    /** @return Result<InferenceResponse, BatchItemFailure> */
    public function result(): Result
    {
        return $this->result;
    }

    public function provenance(): BatchItemProvenance
    {
        return $this->provenance;
    }
}
