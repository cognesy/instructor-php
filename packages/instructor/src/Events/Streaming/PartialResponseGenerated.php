<?php declare(strict_types=1);

namespace Cognesy\Instructor\Events\Streaming;

use Cognesy\Instructor\Events\Support\EventValueNormalizer;
use Cognesy\Instructor\Events\StructuredOutputEvent;

final class PartialResponseGenerated extends StructuredOutputEvent
{
    public function __construct(
        public mixed $partialResponse
    ) {
        parent::__construct([
            'valueType' => is_object($partialResponse) ? $partialResponse::class : get_debug_type($partialResponse),
            'hasValue' => $partialResponse !== null,
        ]);
    }

    public function serializedValue(): mixed
    {
        return EventValueNormalizer::normalize($this->partialResponse);
    }
}
