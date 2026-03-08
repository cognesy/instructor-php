<?php declare(strict_types=1);

namespace Cognesy\Instructor\Core;

use Cognesy\Instructor\Contracts\CanGenerateResponse;
use Cognesy\Instructor\Data\ResponseModel;
use Cognesy\Instructor\Deserialization\Contracts\CanDeserializeResponse;
use Cognesy\Instructor\Enums\ReturnTarget;
use Cognesy\Instructor\Events\Response\ResponseConvertedToObject;
use Cognesy\Instructor\Events\Response\ResponseGenerationFailed;
use Cognesy\Instructor\Extraction\Contracts\CanExtractResponse;
use Cognesy\Instructor\Extraction\Data\ExtractionInput;
use Cognesy\Instructor\Transformation\Contracts\CanTransformResponse;
use Cognesy\Instructor\Validation\Contracts\CanValidateResponse;
use Cognesy\Instructor\Validation\ValidationResult;
use Cognesy\Polyglot\Inference\Data\InferenceResponse;
use Cognesy\Instructor\Enums\OutputMode;
use Cognesy\Pipeline\Enums\ErrorStrategy;
use Cognesy\Pipeline\Pipeline;
use Cognesy\Pipeline\ProcessingState;
use Cognesy\Pipeline\StateContracts\CanCarryState;
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
        private CanExtractResponse $extractor,
    ) {}

    #[\Override]
    public function makeResponse(
        InferenceResponse $response,
        ResponseModel $responseModel,
        OutputMode $mode,
        mixed $prebuiltValue = null,
    ) : Result {
        if ($prebuiltValue !== null) {
            return $this->processPrebuiltValue($prebuiltValue, $responseModel);
        }

        // Array-first pipeline: extract → deserialize → validate → transform
        try {
            $data = $this->extractor->extract(ExtractionInput::fromResponse($response, $mode));
        } catch (Throwable $error) {
            $this->events->dispatch(new ResponseGenerationFailed(['error' => $error]));
            return Result::failure($error->getMessage());
        }

        // Stage 2-4: Deserialize, Validate, Transform via pipeline
        $pipeline = $this->makePipeline($responseModel);
        return $pipeline->executeWith(ProcessingState::with($data))->result();
    }

    /**
     * Array-first pipeline: array → deserialize → validate (if object) → transform (if object)
     */
    private function makePipeline(ResponseModel $responseModel): Pipeline {
        $returnTarget = $responseModel->returnTarget();
        $skipObjectStages = $returnTarget === ReturnTarget::Array;

        return Pipeline::builder(ErrorStrategy::FailFast)
            ->through(fn(array $data) => Result::success($data))
            ->through(fn(array $data) => $this->responseDeserializer->deserialize($data, $responseModel))
            ->through(fn($response) => match (true) {
                $skipObjectStages => Result::success($response),
                default => $this->responseValidator->validate($response, $responseModel)
            })
            ->through(fn($response) => match (true) {
                $skipObjectStages => Result::success($response),
                default => $this->responseTransformer->transform($response, $responseModel)
            })
            ->tap(fn($response) => $this->events->dispatch(new ResponseConvertedToObject(['object' => json_encode($response)])))
            ->onFailure(fn($state) => $this->events->dispatch(new ResponseGenerationFailed(['error' => $state->exception()])))
            ->finally(fn(CanCarryState $state) => match (true) {
                $state->isSuccess() => $state->result(),
                default => Result::failure(implode('; ', $this->extractErrors($state)))
            })
            ->create();
    }

    private function processPrebuiltValue(mixed $value, ResponseModel $responseModel): Result {
        if (!$responseModel->returnTarget()->expectsObject() || !is_object($value)) {
            return Result::success($value);
        }

        try {
            $validated = $this->responseValidator->validate($value, $responseModel);
            if ($validated->isFailure()) {
                return $validated;
            }

            return $this->responseTransformer->transform($validated->unwrap(), $responseModel);
        } catch (Throwable $error) {
            $this->events->dispatch(new ResponseGenerationFailed(['error' => $error]));
            return Result::failure($error->getMessage());
        }
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
