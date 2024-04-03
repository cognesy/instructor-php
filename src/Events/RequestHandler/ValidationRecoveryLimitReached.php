<?php

namespace Cognesy\Instructor\Events\RequestHandler;

use Cognesy\Instructor\Events\Event;
use Cognesy\Instructor\Utils\Json;

class ValidationRecoveryLimitReached extends Event
{
    public function __construct(
        public int $retries,
        public array $errors,
    ) {
        parent::__construct();
    }

    public function __toString(): string
    {
        return Json::encode([
            'retries' => $this->retries,
            'errors' => $this->errors
        ]);
    }
}
