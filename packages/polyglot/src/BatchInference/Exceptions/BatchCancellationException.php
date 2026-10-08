<?php

declare(strict_types=1);

namespace Cognesy\Polyglot\BatchInference\Exceptions;

use Cognesy\Polyglot\BatchInference\Data\BatchReference;
use Cognesy\Polyglot\BatchInference\Enums\BatchMutationCertainty;
use Throwable;

final class BatchCancellationException extends BatchException
{
    public function __construct(
        private readonly BatchReference $reference,
        private readonly BatchMutationCertainty $certainty,
        ?Throwable $previous = null,
    ) {
        $message = match ($certainty) {
            BatchMutationCertainty::Rejected => 'Batch cancellation request was rejected.',
            default => 'Batch cancellation acknowledgement is uncertain.',
        };
        parent::__construct($message, previous: $previous);
    }

    public function reference(): BatchReference
    {
        return $this->reference;
    }

    public function certainty(): BatchMutationCertainty
    {
        return $this->certainty;
    }
}
