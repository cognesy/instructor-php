<?php

namespace Cognesy\Instructor\Events\Request;

use Cognesy\Instructor\ApiClient\Requests\ApiRequest;
use Cognesy\Instructor\Events\Event;
use Cognesy\Instructor\Utils\Json\Json;
use Psr\Log\LogLevel;

class RequestToLLMFailed extends Event
{
    public $logLevel = LogLevel::ERROR;

    public function __construct(
        public ApiRequest $request,
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