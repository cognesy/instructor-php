<?php

namespace Cognesy\Instructor\ApiClient\Traits;

use Cognesy\Instructor\Data\Request;
use Cognesy\Instructor\Data\ResponseModel;
use Cognesy\Instructor\Enums\Mode;
use Cognesy\Instructor\Utils\Json;
use Exception;

trait HandlesRequestClass
{
    private string $prompt = "\nRespond correctly with strict JSON object containing extracted data within a ```json {} ``` codeblock. Object must validate against this JSONSchema:\n";

    public function addRequest(
        array $messages,
        ResponseModel $responseModel,
        Request $request,
    ) : static {
        $mode = $request->mode;
        $model = $request->model;
        $options = $request->options;

        return match($mode) {
            Mode::MdJson => $this->chatCompletion(
                messages: $this->appendInstructions($messages, $responseModel->jsonSchema),
                model: $model,
                options: $options
            ),
            Mode::Json => $this->jsonCompletion(
                messages: $messages,
                responseFormat: [
                    'type' => 'json_object',
                    'schema' => $responseModel->jsonSchema
                ],
                model: $model,
                options: $options
            ),
            Mode::Tools => $this->toolsCall(
                messages: $messages,
                tools: [$responseModel->toolCallSchema()],
                toolChoice: [
                    'type' => 'function',
                    'function' => ['name' => $responseModel->functionName]
                ],
                model: $model,
                options: $options
            ),
            default => throw new Exception('Unknown mode')
        };
    }

    private function appendInstructions(array $messages, array $jsonSchema) : array {
        $lastIndex = count($messages) - 1;
        if (!isset($messages[$lastIndex]['content'])) {
            $messages[$lastIndex]['content'] = '';
        }
        $messages[$lastIndex]['content'] .= $this->prompt . Json::encode($jsonSchema);
        return $messages;
    }
}
