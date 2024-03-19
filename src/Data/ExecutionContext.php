<?php

namespace Cognesy\Instructor\Data;

class ExecutionContext
{
    private function __construct(
        private readonly Request $request,
        private readonly ?ResponseModel $responseModel,
        private readonly ?LLMResponse $response,
    ) {
    }

    static public function make(
        Request $request,
        ?ResponseModel $responseModel,
        ?LLMResponse $response,
    ): ExecutionContext {
        return new ExecutionContext($request, $responseModel, $response);
    }

    public function getRequest(): Request {
        return $this->request;
    }

    public function getResponseModel(): ResponseModel {
        return $this->responseModel;
    }

    public function hasResponseModel(): bool {
        return $this->responseModel !== null;
    }

    public function getResponse(): LLMResponse {
        return $this->response;
    }

    public function hasResponse(): bool {
        return $this->response !== null;
    }
}