<?php

namespace Cognesy\Instructor\Events\Request;

use Cognesy\Utils\Events\Event;
use Cognesy\Utils\Json\Json;

final class ResponseModelRequested extends Event
{
    public function __construct(
        public mixed $requestedModel
    ) {
        parent::__construct();
    }

    public function __toString(): string {
        return Json::encode($this->toArray());
    }

    public function toArray(): array {
        return [
            'requestedModel' => $this->requestedModel,
        ];
    }
}