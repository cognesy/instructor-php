<?php
namespace Cognesy\Instructor\Core\Response;

use Cognesy\Instructor\ApiClient\Responses\ApiResponse;
use Cognesy\Instructor\Contracts\CanGenerateResponse;
use Cognesy\Instructor\Data\ResponseModel;
use Cognesy\Instructor\Deserialization\ResponseDeserializer;
use Cognesy\Instructor\Events\EventDispatcher;
use Cognesy\Instructor\Events\Response\ResponseConvertedToObject;
use Cognesy\Instructor\Events\Response\ResponseGenerationFailed;
use Cognesy\Instructor\Exceptions\JsonParsingException;
use Cognesy\Instructor\Transformation\ResponseTransformer;
use Cognesy\Instructor\Utils\Chain;
use Cognesy\Instructor\Utils\Json\Json;
use Cognesy\Instructor\Utils\Result\Result;
use Cognesy\Instructor\Validation\ResponseValidator;
use Cognesy\Instructor\Validation\ValidationResult;
use Exception;

class ResponseGenerator implements CanGenerateResponse
{
    public function __construct(
        private ResponseDeserializer $responseDeserializer,
        private ResponseValidator $responseValidator,
        private ResponseTransformer $responseTransformer,
        private EventDispatcher $events,
    ) {}

    public function makeResponse(ApiResponse $response, ResponseModel $responseModel) : Result {
        $result = Chain::from(fn() => $response->getJson())
            ->through(fn($responseJson) => match(true) {
                empty($responseJson) => Result::failure('No JSON found in the response'),
                default => Result::success($responseJson)
            })
            ->through(fn($responseJson) => $this->responseDeserializer->deserialize($responseJson, $responseModel))
            ->through(fn($object) => $this->responseValidator->validate($object))
            ->through(fn($object) => $this->responseTransformer->transform($object))
            ->tap(fn($object) => $this->events->dispatch(new ResponseConvertedToObject($object)))
            ->onFailure(fn($result) => $this->events->dispatch(new ResponseGenerationFailed([$result->error()])))
            ->then(fn($result) => match(true) {
                $result->isSuccess() => $result,
                default => Result::failure($this->extractErrors($result))
            })
            ->result();
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