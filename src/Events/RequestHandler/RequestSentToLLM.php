<?php

namespace Cognesy\Instructor\Events\RequestHandler;

use Cognesy\Instructor\Core\Request;
use Cognesy\Instructor\Core\ResponseModel;
use Cognesy\Instructor\Events\Event;

class RequestSentToLLM extends Event
{

    /**
     * @param array|array[] $messages
     * @param ResponseModel $responseModel
     * @param Request $request
     */
    public function __construct(
        public array $messages,
        public ResponseModel $responseModel,
        public Request $request
    ){
        parent::__construct();
    }

    public function __toString(): string
    {
        return $this->format(json_encode([
            'messages' => $this->messages,
            'tool' => $this->responseModel->functionName,
            'tools' => $this->responseModel->functionCall,
            'model' => $this->request->model,
            'options' => $this->request->options
        ]));
    }
}