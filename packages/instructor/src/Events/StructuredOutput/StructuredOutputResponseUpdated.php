<?php declare(strict_types=1);

namespace Cognesy\Instructor\Events\StructuredOutput;

use Cognesy\Instructor\Data\StructuredOutputResponse;
use Cognesy\Instructor\Events\Support\EventValueNormalizer;
use Cognesy\Instructor\Events\StructuredOutputEvent;

class StructuredOutputResponseUpdated extends StructuredOutputEvent
{
    public function __construct(
        array $data = [],
        public readonly ?StructuredOutputResponse $response = null,
    ) {
        parent::__construct($data);
    }

    public function value(): mixed
    {
        return $this->response?->value();
    }

    public function serializedValue(): mixed
    {
        return EventValueNormalizer::normalize($this->value());
    }

    public function serializedToolCalls(): array
    {
        return $this->response?->toolCalls()->toArray() ?? [];
    }
}
