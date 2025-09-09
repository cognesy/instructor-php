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
use Cognesy\Pipeline\Contracts\CanCarryState;
use Cognesy\Pipeline\Enums\ErrorStrategy;
use Cognesy\Pipeline\Pipeline;
use Cognesy\Pipeline\ProcessingState;
use Cognesy\Polyglot\Inference\Data\InferenceResponse;
use Cognesy\Polyglot\Inference\Enums\OutputMode;
use Cognesy\Utils\Result\Failure;
use Cognesy\Utils\Result\Result;
use Exception;
use Psr\EventDispatcher\EventDispatcherInterface;
use Throwable;

class ResponseGenerator implements CanGenerateResponse
{
    public function __construct(
        private ResponseDeserializer $responseDeserializer,
        private ResponseValidator $responseValidator,
        private ResponseTransformer $responseTransformer,
        private EventDispatcherInterface $events,
    ) {}

    public function makeResponse(InferenceResponse $response, ResponseModel $responseModel, OutputMode $mode) : Result {
        $pipeline = Pipeline::builder(ErrorStrategy::FailFast)
            ->through(fn($responseJson) => match(true) {
                ($responseJson === '') => Result::failure('No JSON found in the response'),
                default => Result::success($responseJson)
            })
            ->through(fn($responseJson) => $this->responseDeserializer->deserialize($responseJson, $responseModel))
            ->through(fn($object) => $this->responseValidator->validate($object))
            ->through(fn($object) => $this->responseTransformer->transform($object))
            ->tap(fn($object) => $this->events->dispatch(new ResponseConvertedToObject(['object' => json_encode($object)])))
            ->onFailure(fn($state) => $this->events->dispatch(new ResponseGenerationFailed(['error' => $state->exception()])))
            ->finally(fn(CanCarryState $state) => match(true) {
                $state->isSuccess() => $state->result(),
                default => Result::failure(implode('; ', $this->extractErrors($state)))
            })
            ->create();

        $json = $response->findJsonData($mode)->toString();
        return $pipeline->executeWith(ProcessingState::with($json))->result();
    }

    // INTERNAL ////////////////////////////////////////////////////////

    protected function extractErrors(CanCarryState|Failure|Exception|ValidationResult $output) : array {
        return match(true) {
            $output instanceof Throwable => [$output->getMessage()],
            $output instanceof CanCarryState && $output->isSuccess() => [],
            $output instanceof CanCarryState && $output->isFailure() => [$output->exception()->getMessage()],
            $output instanceof Result && $output->isSuccess() => [],
            $output instanceof Result && $output->isFailure() => [$output->errorMessage()],
            default => []
        };
    }
}