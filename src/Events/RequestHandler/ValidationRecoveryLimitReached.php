<?php

namespace Cognesy\Instructor\Events\RequestHandler;

use Cognesy\Instructor\Events\Event;

class ValidationRecoveryLimitReached extends Event
{
    public function __construct(
        public int $retries,
        public string $errors,
    ) {
        parent::__construct();
    }

    public function __toString(): string
    {
        return json_encode([
            'retries' => $this->retries,
            'errors' => $this->errors
        ]);
    }
}
