<?php
namespace Cognesy\Instructor\LLMs;

use Cognesy\Instructor\ApiClient\Data\Responses\ApiResponse;
use Cognesy\Instructor\Data\FunctionCall;
use Cognesy\Instructor\Utils\Json;

abstract class AbstractJsonHandler extends AbstractCallHandler
{
    protected function getFunctionCalls(ApiResponse $response) : array {
        $jsonData = Json::find($response->content);
        if (empty($jsonData)) {
            return [];
        }
        $toolCalls = [];
        $toolCalls[] = new FunctionCall(
            id: '', // ???
            functionName: $this->responseModel->functionName ?? '',
            functionArgsJson: $jsonData
        );
        return $toolCalls;
    }

    abstract protected function getResponse() : ApiResponse;
}