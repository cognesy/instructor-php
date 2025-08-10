<?php declare(strict_types=1);

namespace Cognesy\Instructor\Core;

use Cognesy\Instructor\Contracts\CanGenerateResponse;
use Cognesy\Instructor\Data\ResponseModel;
use Cognesy\Instructor\Deserialization\ResponseDeserializer;
use Cognesy\Instructor\Events\Response\ResponseConvertedToObject;
use Cognesy\Instructor\Events\Response\ResponseGenerationFailed;
use Cognesy\Instructor\Transformation\ResponseTransformer;
use Cognesy\Instructor\Validation\ResponseValidator;
use Cognesy\Instructor\Validation\ValidationResult;
use Cognesy\Pipeline\Legacy\Chain\ResultChain;
use Cognesy\Polyglot\Inference\Data\InferenceResponse;
use Cognesy\Polyglot\Inference\Enums\OutputMode;
use Cognesy\Utils\Json\Json;
use Cognesy\Utils\Json\JsonParsingException;
use Cognesy\Utils\Result\Result;
use Exception;
use Psr\EventDispatcher\EventDispatcherInterface;

class ResponseGenerator implements CanGenerateResponse
{
    public function __construct(
        private ResponseDeserializer $responseDeserializer,
        private ResponseValidator $responseValidator,
        private ResponseTransformer $responseTransformer,
        private EventDispatcherInterface $events,
    ) {}

    public function makeResponse(InferenceResponse $response, ResponseModel $responseModel, OutputMode $mode) : Result {
        $result = ResultChain::from(fn() => $response->findJsonData($mode)->toString())
            ->through(fn($responseJson) => match(true) {
                ($responseJson === '') => Result::failure('No JSON found in the response'),
                default => Result::success($responseJson)
            })
            ->through(fn($responseJson) => $this->responseDeserializer->deserialize($responseJson, $responseModel))
            ->through(fn($object) => $this->responseValidator->validate($object))
            ->through(fn($object) => $this->responseTransformer->transform($object))
            ->tap(fn($object) => $this->events->dispatch(new ResponseConvertedToObject(['object' => json_encode($object)])))
            ->onFailure(fn($result) => $this->events->dispatch(new ResponseGenerationFailed(['error' => $result->error()])))
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