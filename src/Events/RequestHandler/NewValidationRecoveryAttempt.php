<?php
namespace Cognesy\Instructor\Events\RequestHandler;

use Cognesy\Instructor\Events\Event;

class NewValidationRecoveryAttempt extends Event
{
    public function __construct(
        public int    $retry,
        public string $errors,
    )
    {
        parent::__construct();
    }

    public function __toString(): string
    {
        return json_encode([
            'retry' => $this->retry,
            'errors' => $this->errors
        ]);
    }
}
