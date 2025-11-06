<?php declare(strict_types=1);

namespace Cognesy\Instructor\Core;

use Cognesy\Instructor\Contracts\CanGenerateResponse;
use Cognesy\Instructor\Data\ResponseModel;
use Cognesy\Instructor\Deserialization\Contracts\CanDeserializeResponse;
use Cognesy\Instructor\Events\Response\ResponseConvertedToObject;
use Cognesy\Instructor\Events\Response\ResponseGenerationFailed;
use Cognesy\Instructor\Transformation\Contracts\CanTransformResponse;
use Cognesy\Instructor\Validation\Contracts\CanValidateResponse;
use Cognesy\Instructor\Validation\ValidationResult;
use Cognesy\Pipeline\Enums\ErrorStrategy;
use Cognesy\Pipeline\Pipeline;
use Cognesy\Pipeline\ProcessingState;
use Cognesy\Pipeline\StateContracts\CanCarryState;
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
        private CanDeserializeResponse $responseDeserializer,
        private CanValidateResponse $responseValidator,
        private CanTransformResponse $responseTransformer,
        private EventDispatcherInterface $events,
    ) {}

    #[\Override]
    public function makeResponse(InferenceResponse $response, ResponseModel $responseModel, OutputMode $mode) : Result {
        // Fast-path: if a fully processed value is already present, accept it.
        if ($response->hasValue()) {
            return Result::success($response->value());
        }
        $pipeline = $this->makeResponsePipeline($responseModel);
        $json = $response->findJsonData($mode)->toString();
        return $pipeline->executeWith(ProcessingState::with($json))->result();
    }

    // INTERNAL ////////////////////////////////////////////////////////

    private function makeResponsePipeline(ResponseModel $responseModel) : Pipeline {
        return Pipeline::builder(ErrorStrategy::FailFast)
            ->through(fn($responseContent) => match(true) {
                ($responseContent === '') => Result::failure('No JSON found in the response'),
                default => Result::success($responseContent)
            })
            ->through(fn($responseContent) => $this->responseDeserializer->deserialize($responseContent, $responseModel))
            ->through(fn($responseObject) => $this->responseValidator->validate($responseObject, $responseModel))
            ->through(fn($responseObject) => $this->responseTransformer->transform($responseObject, $responseModel))
            ->tap(fn($responseObject) => $this->events->dispatch(new ResponseConvertedToObject(['object' => json_encode($responseObject)])))
            ->onFailure(fn($state) => $this->events->dispatch(new ResponseGenerationFailed(['error' => $state->exception()])))
            ->finally(fn(CanCarryState $state) => match(true) {
                $state->isSuccess() => $state->result(),
                default => Result::failure(implode('; ', $this->extractErrors($state)))
            })
            ->create();
    }

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
