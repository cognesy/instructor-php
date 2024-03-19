<?php
namespace Cognesy\Instructor\LLMs\OpenAI\MdJsonMode;

use Cognesy\Instructor\Contracts\CanCallFunction;
use Cognesy\Instructor\Data\ResponseModel;
use Cognesy\Instructor\Events\EventDispatcher;
use Cognesy\Instructor\LLMs\OpenAI\JsonMode\JsonModeHandler;
use Cognesy\Instructor\LLMs\OpenAI\JsonMode\StreamedJsonModeCallHandler;
use Cognesy\Instructor\Utils\Result;
use OpenAI\Client;

class OpenAIMdJsonCaller implements CanCallFunction
{
    private string $prompt = "\nRespond with JSON containing extracted data within a ```json {} ``` codeblock. Do not return JSONSchema, only JSON. Response must follow this JSONSchema:\n";

    public function __construct(
        private EventDispatcher $eventDispatcher,
        private Client $client,
    ) {}

    /**
     * Handle LLM function call
     */
    public function callFunction(
        array $messages,
        ResponseModel $responseModel,
        string $model,
        array $options,
    ) : Result {
        $messages = $this->appendInstructions($messages, $responseModel->jsonSchema);
        $request = array_merge([
            'model' => $model,
            'messages' => $messages,
        ], $options);

        return match($options['stream'] ?? false) {
            true => (new StreamedJsonModeCallHandler($this->eventDispatcher, $this->client, $request, $responseModel))->handle(),
            default => (new JsonModeHandler($this->eventDispatcher, $this->client, $request, $responseModel))->handle()
        };
    }

    private function appendInstructions(array $messages, array $jsonSchema) : array {
        $lastIndex = count($messages) - 1;
        if (!isset($messages[$lastIndex]['content'])) {
            $messages[$lastIndex]['content'] = '';
        }
        $messages[$lastIndex]['content'] .= $this->prompt . json_encode($jsonSchema);
        return $messages;
    }
}
