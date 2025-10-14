<?php declare(strict_types=1);

namespace Cognesy\Instructor\Partials;

use Cognesy\Instructor\Contracts\CanExecuteStructuredOutput;
use Cognesy\Instructor\Core\InferenceProvider;
use Cognesy\Instructor\Core\ValidationRetryHandler;
use Cognesy\Instructor\Data\StructuredOutputExecution;
use Cognesy\Instructor\Partials\Data\AggregatedResponse;
use Cognesy\Polyglot\Inference\Data\InferenceResponse;
use Cognesy\Polyglot\Inference\Data\PartialInferenceResponse;
use Cognesy\Polyglot\Inference\Collections\PartialInferenceResponseList;
use Generator;

/**
 * Streams LLM responses using the new Partials pipeline.
 * Bridges InferenceProvider (polyglot) with PartialStreamFactory and yields
 * StructuredOutputExecution updates compatible with existing consumers.
 */
final class PartialStreamingRequestHandler implements CanExecuteStructuredOutput
{
    public function __construct(
        private readonly InferenceProvider $inferenceProvider,
        private readonly PartialStreamFactory $partials,
        private readonly ValidationRetryHandler $retryHandler,
    ) {}

    #[\Override]
    public function nextUpdate(StructuredOutputExecution $execution): Generator {
        $responseModel = $execution->responseModel();
        assert($responseModel !== null, 'Response model cannot be null');
        $mode = $execution->outputMode();

        // Get polyglot responses stream and filter only PartialInferenceResponse
        $polyglot = $this->inferenceProvider->getInference($execution)->stream()->responses();
        $partialsList = PartialInferenceResponseList::empty();
        $tapped = (function () use ($polyglot, &$partialsList): Generator {
            foreach ($polyglot as $resp) {
                if ($resp instanceof PartialInferenceResponse) {
                    $partialsList = $partialsList->withNewPartialResponse($resp);
                    yield $resp;
                }
            }
        })();

        // Run Partials pipeline
        $aggregateStream = $this->partials->makePureStream($tapped, $responseModel, $mode);

        $finalInference = null;
        /** @var AggregatedResponse $aggregate */
        foreach ($aggregateStream as $aggregate) {
            $inference = $aggregate->toInferenceResponse();
            $finalInference = $inference;

            $updated = $execution->withCurrentAttempt(
                inferenceResponse: $inference,
                partialInferenceResponses: $partialsList,
                errors: $this->retryHandler->errors(),
            );
            yield $updated;
        }

        // Ensure we have an inference to validate/finalize
        if ($finalInference === null) {
            $finalInference = InferenceResponse::empty();
        }

        // Validate and finalize
        $result = $this->retryHandler->validateResponse(
            $finalInference,
            $responseModel,
            $mode,
        );
        $finalValue = $this->retryHandler->finalizeOrThrow($execution, $result);

        yield $execution->withSuccessfulAttempt(
            inferenceResponse: $finalInference->withValue($finalValue),
            partialInferenceResponses: $partialsList,
            returnedValue: $finalValue,
        );
    }
}
