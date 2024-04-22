<?php

namespace Cognesy\Instructor\Core;

use Cognesy\Instructor\ApiClient\ApiClient;
use Cognesy\Instructor\Data\Request;
use Cognesy\Instructor\Data\ResponseModel;
use Cognesy\Instructor\Enums\Mode;
use Cognesy\Instructor\Schema\Factories\ToolCallBuilder;
use Cognesy\Instructor\Utils\Json;
use Exception;

class RequestBuilder
{
    private string $prompt = "\nRespond correctly with strict JSON object containing extracted data within a ```json {} ``` codeblock. Object must validate against this JSONSchema:\n";

    public function __construct(
        private ToolCallBuilder $toolCallBuilder,
    ) {}

    public function clientWithRequest(
        array $messages,
        ResponseModel $responseModel,
        Request $request,
    ) : ApiClient {
        $mode = $request->mode;
        $model = $request->model;
        $options = $request->options;
        $client = $request->client();

        $clientWithRequest = match($mode) {
            Mode::Json => $client->jsonCompletion(
                messages: $messages,
                responseFormat: [
                    'type' => 'json_object',
                    'schema' => $responseModel->jsonSchema
                ],
                model: $model,
                options: $options
            ),
            Mode::Tools => $client->toolsCall(
                messages: $messages,
                tools: [$this->toolCallBuilder->render(
                    $responseModel->jsonSchema,
                    $responseModel->functionName,
                    $responseModel->functionDescription
                )],
                toolChoice: [
                    'type' => 'function',
                    'function' => ['name' => $responseModel->functionName]
                ],
                model: $model,
                options: $options
            ),
            Mode::MdJson => $client->chatCompletion(
                messages: $this->appendInstructions($messages, $responseModel->jsonSchema),
                model: $model,
                options: $options
            ),
            default => throw new Exception('Unknown mode')
        };

        return $clientWithRequest;
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
