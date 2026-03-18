<?php declare(strict_types=1);

namespace Cognesy\Instructor\Events\PartialsGenerator;

use Cognesy\Instructor\Events\Support\EventValueNormalizer;
use Cognesy\Instructor\Events\StructuredOutputEvent;
use Cognesy\Utils\Json\Json;

final class PartialResponseGenerated extends StructuredOutputEvent
{
    public function __construct(
        public mixed $partialResponse
    ) {
        parent::__construct([
            'valueType' => is_object($partialResponse) ? $partialResponse::class : get_debug_type($partialResponse),
            'value' => EventValueNormalizer::normalize($partialResponse),
        ]);
    }

    public function __toString(): string
    {
        return Json::encode($this->partialResponse);
    }
}
