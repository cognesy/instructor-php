<?php declare(strict_types=1);

namespace Cognesy\Instructor\Core;

use Cognesy\Instructor\Contracts\CanGenerateResponse;
use Cognesy\Instructor\Data\ResponseModel;
use Cognesy\Instructor\Deserialization\Contracts\CanDeserializeResponse;
use Cognesy\Instructor\Events\Response\ResponseConvertedToObject;
use Cognesy\Instructor\Events\Response\ResponseGenerationFailed;
use Cognesy\Instructor\Extraction\Contracts\CanExtractResponse;
use Cognesy\Instructor\Extraction\Data\ExtractionInput;
use Cognesy\Instructor\Transformation\Contracts\CanTransformResponse;
use Cognesy\Instructor\Validation\Contracts\CanValidateResponse;
use Cognesy\Polyglot\Inference\Data\InferenceResponse;
use Cognesy\Instructor\Enums\OutputMode;
use Cognesy\Utils\Result\Result;
use Psr\EventDispatcher\EventDispatcherInterface;
use Throwable;

/**
 * Turns a complete InferenceResponse into the response value:
 * extract (mode-specific output → array), then hydrate (ObjectHydrator owns
 * the deserialize → validate → transform sequencing). This class owns the
 * extraction stage and failure/success event reporting only.
 */
class ResponseGenerator implements CanGenerateResponse
{
    private readonly ObjectHydrator $hydrator;

    public function __construct(
        CanDeserializeResponse $responseDeserializer,
        CanValidateResponse $responseValidator,
        CanTransformResponse $responseTransformer,
        private readonly EventDispatcherInterface $events,
        private readonly CanExtractResponse $extractor,
    ) {
        $this->hydrator = new ObjectHydrator(
            deserializer: $responseDeserializer,
            validator: $responseValidator,
            transformer: $responseTransformer,
        );
    }

    #[\Override]
    public function makeResponse(
        InferenceResponse $response,
        ResponseModel $responseModel,
        OutputMode $mode,
        mixed $prebuiltValue = null,
    ) : Result {
        if ($prebuiltValue !== null) {
            return $this->finalizePrebuilt($prebuiltValue, $responseModel);
        }

        $result = $this->extractAndHydrate($response, $responseModel, $mode);

        return match (true) {
            $result->isSuccess() => $this->reportSuccess($result),
            default => $this->reportFailure($result),
        };
    }

    /**
     * Prebuilt values (streaming finalization) keep their historical event
     * semantics: only unexpected exceptions are reported, validation failures
     * flow back silently (the retry loop reports them at its own level).
     */
    private function finalizePrebuilt(mixed $value, ResponseModel $responseModel): Result {
        $result = $this->hydrator->finalize($value, $responseModel);
        if ($result->isFailure() && $result->error() instanceof Throwable) {
            $this->reportFailure($result);
        }
        return $result;
    }

    public function hydrator(): ObjectHydrator {
        return $this->hydrator;
    }

    private function extractAndHydrate(
        InferenceResponse $response,
        ResponseModel $responseModel,
        OutputMode $mode,
    ): Result {
        try {
            $data = $this->extractor->extract(ExtractionInput::fromResponse($response, $mode));
        } catch (Throwable $error) {
            return Result::failure($error);
        }

        return $this->hydrator->hydrate($data, $responseModel);
    }

    private function reportSuccess(Result $result): Result {
        $this->events->dispatch(new ResponseConvertedToObject($this->valueSummary($result->unwrap())));
        return $result;
    }

    private function reportFailure(Result $result): Result {
        $error = $result->error();
        $this->events->dispatch(new ResponseGenerationFailed([
            'errorMessage' => $result->errorMessage(),
            'errorType' => $error instanceof Throwable ? $error::class : get_debug_type($error),
        ]));
        return $result;
    }

    private function valueSummary(mixed $value) : array
    {
        return match (true) {
            is_object($value) => [
                'responseType' => $value::class,
                'fieldCount' => count(get_object_vars($value)),
            ],
            is_array($value) => [
                'responseType' => 'array',
                'itemCount' => count($value),
                'keys' => array_slice(array_keys($value), 0, 20),
            ],
            default => [
                'responseType' => get_debug_type($value),
            ],
        };
    }
}
