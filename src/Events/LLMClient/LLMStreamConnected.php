<?php
namespace Cognesy\Instructor\Events\LLMClient;

use Cognesy\Instructor\Events\Event;
use Cognesy\Instructor\Utils\Json\Json;

class LLMStreamConnected extends Event
{
    public function __construct(
        public int $status
    ) {
        parent::__construct();
    }

    public function __toString(): string {
        return Json::encode([
            'status' => $this->status,
        ]);
    }
}