<?php

namespace Cognesy\Instructor\LLMs;

use Cognesy\Instructor\Data\FunctionCall;

abstract class AbstractJsonHandler extends AbstractCallHandler
{
    protected function getFunctionCalls(mixed $response) : array {
        $jsonData = $this->getJsonData($response);
        if (empty($jsonData)) {
            return [];
        }
        $toolCalls = [];
        $toolCalls[] = new FunctionCall(
            toolCallId: '', // ???
            functionName: $this->getFunctionName($response),
            functionArgsJson: $jsonData
        );
        return $toolCalls;
    }

    protected function getFunctionName(mixed $response) : string {
        return $this->responseModel->functionName ?? '';
    }

    abstract protected function getResponse() : mixed;
    abstract protected function getJsonData(mixed $response) : string;
    abstract protected function getFinishReason(mixed $response) : string;
}