<?php
namespace Cognesy\Instructor\Features\Core;

use Cognesy\Instructor\Data\ResponseModel;
use Cognesy\Instructor\Events\EventDispatcher;
use Cognesy\Instructor\Events\Response\ResponseConvertedToObject;
use Cognesy\Instructor\Events\Response\ResponseGenerationFailed;
use Cognesy\Instructor\Exceptions\JsonParsingException;
use Cognesy\Instructor\Features\Core\Contracts\CanGenerateResponse;
use Cognesy\Instructor\Features\Deserialization\ResponseDeserializer;
use Cognesy\Instructor\Features\LLM\Data\LLMResponse;
use Cognesy\Instructor\Features\Transformation\ResponseTransformer;
use Cognesy\Instructor\Features\Validation\ResponseValidator;
use Cognesy\Instructor\Features\Validation\ValidationResult;
use Cognesy\Instructor\Utils\Chain;
use Cognesy\Instructor\Utils\Json\Json;
use Cognesy\Instructor\Utils\Result\Result;
use Exception;

class ResponseGenerator implements CanGenerateResponse
{
    public function __construct(
        private ResponseDeserializer $responseDeserializer,
        private ResponseValidator $responseValidator,
        private ResponseTransformer $responseTransformer,
        private EventDispatcher $events,
    ) {}

    public function makeResponse(LLMResponse $response, ResponseModel $responseModel) : Result {
        $result = Chain::from(fn() => $response->json())
            ->through(fn($responseJson) => match(true) {
                ($responseJson === '') => Result::failure('No JSON found in the response'),
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

    // INTERNAL ////////////////////////////////////////////////////////

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