<?php

namespace Cognesy\Instructor\Core;

use Cognesy\Instructor\ApiClient\ApiClient;
use Cognesy\Instructor\Data\ResponseModel;
use Cognesy\Instructor\Enums\Mode;
use Cognesy\Instructor\Schema\Factories\ToolCallBuilder;
use Cognesy\Instructor\Utils\Json;
use Exception;

class ApiClientRequestBuilder
{
    private string $prompt = "\nRespond correctly with strict JSON object containing extracted data within a ```json {} ``` codeblock. Object must validate against this JSONSchema:\n";

    public function __construct(
        private ApiClient $client,
        private ToolCallBuilder $toolCallBuilder,
    ) {}

    public function makeClientRequest(
        array $messages,
        ResponseModel $responseModel,
        string $model,
        array $options,
        Mode $mode,
    ) : ApiClient {
        return match($mode) {
            Mode::Json => $this->client->jsonCompletion(
                messages: $messages,
                responseFormat: [
                    'type' => 'json_object',
                    'schema' => $responseModel->jsonSchema
                ],
                model: $model,
                options: $options
            ),
            Mode::Tools => $this->client->toolsCall(
                messages: $messages,
                tools: $this->toolCallBuilder->render(
                    $responseModel->jsonSchema,
                    $responseModel->functionName,
                    $responseModel->functionDescription
                ),
                toolChoice: [
                    'type' => 'function',
                    'function' => ['name' => $responseModel->functionName]
                ],
                model: $model,
                options: $options
            ),
            Mode::MdJson => $this->client->chatCompletion(
                messages: $this->appendInstructions($messages, $responseModel->jsonSchema),
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
