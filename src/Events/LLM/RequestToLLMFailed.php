<?php

namespace Cognesy\Instructor\Events\LLM;

use Cognesy\Instructor\Events\Event;

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
        return json_encode([
            'errors' => $this->errors,
            'request' => $this->request,
        ]);
    }
}