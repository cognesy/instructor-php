<?php

namespace Cognesy\Instructor\Events\LLM;

use Cognesy\Instructor\Events\Event;
use Cognesy\Instructor\Utils\Json;

class RequestToLLMFailed extends Event
{
    public function __construct(
        public array $request,
        public string $errors,
    ) {
        parent::__construct();
    }

    public function __toString(): string
    {
        return Json::encode([
            'errors' => $this->errors,
            'request' => $this->request,
        ]);
    }
}