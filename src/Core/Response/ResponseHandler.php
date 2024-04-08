<?php
namespace Cognesy\Instructor\Core\Response;

use Cognesy\Instructor\ApiClient\Data\Responses\ApiResponse;
use Cognesy\Instructor\Contracts\CanHandleResponse;
use Cognesy\Instructor\Data\ResponseModel;
use Cognesy\Instructor\Data\ValidationResult;
use Cognesy\Instructor\Events\EventDispatcher;
use Cognesy\Instructor\Events\RequestHandler\ToolCallResponseConvertedToObject;
use Cognesy\Instructor\Events\RequestHandler\ResponseGenerationFailed;
use Cognesy\Instructor\Exceptions\DeserializationException;
use Cognesy\Instructor\Exceptions\JsonParsingException;
use Cognesy\Instructor\Exceptions\ValidationException;
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
        // which functions have been called (or selected - if parallel tools on)
        $responseJson = $response->getJson();
        if (empty($responseJson)) {
            return Result::failure('No JSON found in the response');
        }
        // process LLM response
        $processingResult = $this->processResponse($responseJson, $responseModel);
        if ($processingResult->isSuccess()) {
            return $processingResult;
        }
        $errors = $this->extractErrors($processingResult);
        return Result::failure($errors);
    }

    /////////////////////////////////////////////////////////////////////////////////////////////////

    protected function processResponse(string $jsonData, ResponseModel $responseModel) : Result {
        // check if JSON not empty and not malformed
        try {
            $result = $this->toResponse($jsonData, $responseModel);
            if ($result->isSuccess()) {
                return $result;
            }
            $errors = $this->extractErrors($result);
        } catch (ValidationException $e) {
            // handle uncaught validation exceptions
            $errors = $this->extractErrors($e);
        } catch (DeserializationException $e) {
            // handle uncaught deserialization exceptions
            $errors = $this->extractErrors($e);
        } catch (Exception $e) {
            // throw on other exceptions
            $this->events->dispatch(new ResponseGenerationFailed([$e->getMessage()]));
            throw new Exception($e->getMessage());
        }
        return Result::failure($errors);
    }

    protected function toResponse(string $jsonData, ResponseModel $responseModel) : Result {
        return Chain::from(fn() => $this->responseDeserializer->deserialize($jsonData, $responseModel))
            ->through(fn($object) => $this->responseValidator->validate($object))
            ->through(fn($object) => $this->responseTransformer->transform($object))
            ->tap(fn($object) => $this->events->dispatch(new ToolCallResponseConvertedToObject($object)))
            ->result();
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