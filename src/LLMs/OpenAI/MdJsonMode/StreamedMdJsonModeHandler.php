<?php
namespace Cognesy\Instructor\LLMs\OpenAI\JsonMode;

use Cognesy\Instructor\Data\ResponseModel;
use Cognesy\Instructor\Events\EventDispatcher;
use Cognesy\Instructor\Exceptions\JSONParsingException;
use Cognesy\Instructor\LLMs\AbstractStreamedJsonHandler;
use Cognesy\Instructor\Utils\Arrays;
use Cognesy\Instructor\Utils\Json;
use Cognesy\Instructor\Utils\JsonParser;
use Cognesy\Instructor\Utils\Result;
use Exception;
use OpenAI\Client;

class StreamedMdJsonModeHandler extends AbstractStreamedJsonHandler
{
    private Client $client;
    private bool $matchToExpectedFields = false;
    private bool $preventJsonSchema = true;

    public function __construct(
        EventDispatcher $events,
        Client $client,
        array $request,
        ResponseModel $responseModel,
    ) {
        $this->client = $client;
        $this->events = $events;
        $this->request = $request;
        $this->responseModel = $responseModel;
    }

    protected function getStream() : Result {
        try {
            $stream = $this->client->chat()->createStreamed($this->request);
        } catch (Exception $e) {
            return Result::failure($e);
        }
        return Result::success($stream);
    }

    protected function getFinishReason(mixed $response) : string {
        return $response->choices[0]->finishReason ?? '';
    }

    protected function getArgumentChunk($response) : string {
        return $response->choices[0]->delta->content ?? '';
    }

    protected function validatePartialResponse(string $partialResponseText) : void {
        if ($this->preventJsonSchema) {
            $this->preventJsonSchemaResponse($partialResponseText);
        }
        if ($this->matchToExpectedFields) {
            $this->detectNonMatchingJson($partialResponseText);
        }
    }

    /// VALIDATIONS //////////////////////////////////////////////////////////////////

    private function preventJsonSchemaResponse(string $partialResponseText) {
        if (!$this->isJsonSchemaResponse($partialResponseText)) {
            return;
        }
        throw new JsonParsingException(
            message: 'You started responding with JSONSchema. Respond with JSON data instead.',
            json: $partialResponseText,
        );
    }

    private function isJsonSchemaResponse(string $responseText) : bool {
        // ...detect JSONSchema response
        try {
            $jsonFragment = Json::findPartial($responseText);
            $decoded = (new JsonParser)->parse($jsonFragment, true);
        } catch (Exception $e) {
            // also covers no JSON at all - which is fine, as some models will respond with text
            return false;
        }
        if (isset($decoded['type']) && $decoded['type'] === 'object') {
            return true;
        }
        return false;
    }

    private function detectNonMatchingJson(string $responseText) {
        if ($this->isMatchingResponseModel($responseText, $this->responseModel)) {
            return;
        }
        throw new JsonParsingException(
            message: 'JSON does not match schema.',
            json: $this->responseText,
        );
    }

    private function isMatchingResponseModel(
        string        $partialResponseText,
        ResponseModel $responseModel
    ) : bool {
        // ...check for response model property names
        $propertyNames = $responseModel->schema->getPropertyNames();
        if (empty($propertyNames)) {
            return true;
        }
        // ...detect matching response model
        try {
            $jsonFragment = Json::findPartial($partialResponseText);
            $decoded = (new JsonParser)->parse($jsonFragment, true);
            // we can try removing last item as it is likely to be still incomplete
            $decoded = Arrays::removeTail($decoded, 1);
        } catch (Exception $e) {
            return false;
        }
        // Question: how to make this work while we're getting partially
        // retrieved field names
        $decodedKeys = array_filter(array_keys($decoded));
        if (empty($decodedKeys)) {
            return true;
        }
        return Arrays::isSubset($decodedKeys, $propertyNames);
    }
}