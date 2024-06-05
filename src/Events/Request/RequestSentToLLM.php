<?php
namespace Cognesy\Instructor\Events\Request;

use Cognesy\Instructor\ApiClient\Requests\ApiRequest;
use Cognesy\Instructor\Events\Event;
use Cognesy\Instructor\Utils\Json;
use Psr\Log\LogLevel;

class RequestSentToLLM extends Event
{
    public $logLevel = LogLevel::INFO;

    public function __construct(
        public ApiRequest $request,
    ) {
        parent::__construct();
    }

    public function __toString(): string {
        return Json::encode(array_filter([
            'class' => get_class($this->request),
            'messages' => $this->request->messages ?? '',
            'model' => $this->request->model ?? '',
            'response_format' => $this->request->responseFormat ?? [],
            'tools' => $this->request->tools ?? [],
            'tool_choice' => $this->request->toolChoice ?? '',
            'options' => $this->request->options ?? [],
        ]));
    }
}