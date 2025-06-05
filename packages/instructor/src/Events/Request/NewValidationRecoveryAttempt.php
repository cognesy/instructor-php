<?php
namespace Cognesy\Instructor\Events\Request;

use Cognesy\Utils\Events\Event;
use Cognesy\Utils\Json\Json;

final class NewValidationRecoveryAttempt extends Event
{
    public function __construct(
        public int   $retry,
        public array $errors,
    )
    {
        parent::__construct();
    }

    public function __toString(): string
    {
        return Json::encode([
            'retry' => $this->retry,
            'errors' => $this->errors,
        ]);
    }
}
