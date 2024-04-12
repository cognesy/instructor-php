<?php
namespace Cognesy\Instructor\Core\ApiClient\MdJsonMode;

use Cognesy\Instructor\ApiClient\Contracts\CanCallChatCompletion;
use Cognesy\Instructor\Contracts\CanCallApiClient;
use Cognesy\Instructor\Data\ResponseModel;
use Cognesy\Instructor\Events\EventDispatcher;
use Cognesy\Instructor\Utils\Json;
use Cognesy\Instructor\Utils\Result;
use JetBrains\PhpStorm\Deprecated;

#[Deprecated]
class ApiClientMdJsonCaller implements CanCallApiClient
{
    private string $prompt = "\nRespond correctly with strict JSON object containing extracted data within a ```json {} ``` codeblock. Object must validate against this JSONSchema:\n";

    public function __construct(
        private EventDispatcher $events,
        private CanCallChatCompletion $client,
    ) {}

    /**
     * Handle LLM function call
     */
    public function callApiClient(
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
            true => (new StreamedMdJsonModeHandler($this->events, $this->client, $request, $responseModel))->handle(),
            default => (new MdJsonModeHandler($this->events, $this->client, $request, $responseModel))->handle()
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
