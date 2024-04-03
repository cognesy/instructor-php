<?php
namespace Cognesy\Instructor\Events\LLM;

use Cognesy\Instructor\Events\Event;
use Cognesy\Instructor\Utils\Json;

class RequestSentToLLM extends Event
{
    public function __construct(
        public mixed $request,
    ) {
        parent::__construct();
    }

    public function __toString(): string {
        return Json::encode($this->request);
    }
}