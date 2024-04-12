<?php
namespace Cognesy\Instructor\Core\Response;

use Cognesy\Instructor\ApiClient\Data\Responses\ApiResponse;
use Cognesy\Instructor\Contracts\CanHandleResponse;
use Cognesy\Instructor\Data\ResponseModel;
use Cognesy\Instructor\Data\ValidationResult;
use Cognesy\Instructor\Events\EventDispatcher;
use Cognesy\Instructor\Events\RequestHandler\ToolCallResponseConvertedToObject;
use Cognesy\Instructor\Events\RequestHandler\ResponseGenerationFailed;
use Cognesy\Instructor\Exceptions\JsonParsingException;
use Cognesy\Instructor\Utils\Chain;
use Cognesy\Instructor\Utils\Json;
use Cognesy\Instructor\Utils\Result;
use Exception;

class ResponseHandler implements CanHandleResponse
{
    public function __construct(
        private ResponseDeserializer $responseDeserializer,
        private ResponseValidator $responseValidator,
        private ResponseTransformer $responseTransformer,
        private EventDispatcher $events,
    ) {}

    public function handleResponse(ApiResponse $response, ResponseModel $responseModel) : Result {
        $result = Chain::from(fn() => $response->getJson())
            ->through(fn($responseJson) => match(true) {
                empty($responseJson) => Result::failure('No JSON found in the response'),
                default => Result::success($responseJson)
            })
            ->through(fn($responseJson) => $this->responseDeserializer->deserialize($responseJson, $responseModel))
            ->through(fn($object) => $this->responseValidator->validate($object))
            ->through(fn($object) => $this->responseTransformer->transform($object))
            ->tap(fn($object) => $this->events->dispatch(new ToolCallResponseConvertedToObject($object)))
            ->then(fn($result) => match(true) {
                $result->isSuccess() => $result,
                default => Result::failure($this->extractErrors($result))
            });
        if ($result->isFailure()) {
            $this->events->dispatch(new ResponseGenerationFailed($result->error()));
        }
        return $result;
    }

    protected function extractErrors(Result|Exception $result) : array {
        if ($result instanceof Exception) {
            return [$result->getMessage()];
        }
        if ($result->isSuccess()) {
            return [];
        }
        $errorValue = $result->error();
        return match($errorValue) {
            is_array($errorValue) => $errorValue,
            is_string($errorValue) => [$errorValue],
            $errorValue instanceof ValidationResult => [$errorValue->getErrorMessage()],
            $errorValue instanceof JsonParsingException => [$errorValue->message],
            $errorValue instanceof Exception => [$errorValue->getMessage()],
            default => [Json::encode($errorValue)]
        };
    }
}