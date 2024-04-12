<?php
namespace Cognesy\Instructor\Core\ApiClient\JsonMode;

use Cognesy\Instructor\ApiClient\Contracts\CanCallJsonCompletion;
use Cognesy\Instructor\Contracts\CanCallApiClient;
use Cognesy\Instructor\Data\ResponseModel;
use Cognesy\Instructor\Events\EventDispatcher;
use Cognesy\Instructor\Utils\Result;
use JetBrains\PhpStorm\Deprecated;

#[Deprecated]
class ApiClientJsonCaller implements CanCallApiClient
{
    public function __construct(
        private EventDispatcher $events,
        private CanCallJsonCompletion $client,
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
        $request = array_merge([
            'model' => $model,
            'messages' => $messages,
            'response_format' => ['type' => 'json_object', 'schema' => $responseModel->jsonSchema],
        ], $options);

        return match($options['stream'] ?? false) {
            true => (new StreamedJsonModeHandler($this->events, $this->client, $request, $responseModel))->handle(),
            default => (new JsonModeHandler($this->events, $this->client, $request, $responseModel))->handle()
        };
    }
}
