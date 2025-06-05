<?php

namespace Cognesy\Instructor\Events\Response;

use Cognesy\Instructor\Deserialization\Contracts\CanDeserializeSelf;
use Cognesy\Utils\Events\Event;
use Cognesy\Utils\Json\Json;

final class CustomResponseDeserializationAttempt extends Event
{
    public function __construct(
        public CanDeserializeSelf $instance,
        public string $json,
    ) {
        parent::__construct();
    }

    public function __toString(): string {
        return Json::encode([
            'object' => $this->instance,
            'json' => $this->json
        ]);
    }
}