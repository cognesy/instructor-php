<?php

namespace Cognesy\Instructor\Events\RequestHandler;

use Cognesy\Instructor\Data\Request;
use Cognesy\Instructor\Data\ResponseModel;
use Cognesy\Instructor\Events\Event;

class FunctionCallRequested extends Event
{

    /**
     * @param array|array[] $messages
     * @param ResponseModel $responseModel
     * @param \Cognesy\Instructor\Data\Request $request
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
        return json_encode([
            'messages' => $this->messages,
            'tool' => $this->responseModel->functionName,
            'tools' => $this->responseModel->functionCall,
            'model' => $this->request->model,
            'options' => $this->request->options
        ]);
    }
}