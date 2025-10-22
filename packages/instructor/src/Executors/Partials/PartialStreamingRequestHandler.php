<?php declare(strict_types=1);

namespace Cognesy\Instructor\Executors\Partials;

use Cognesy\Instructor\Contracts\CanExecuteStructuredOutput;
use Cognesy\Instructor\Contracts\CanGenerateResponse;
use Cognesy\Instructor\Core\InferenceProvider;
use Cognesy\Instructor\Core\RetryHandler;
use Cognesy\Instructor\Data\StructuredOutputExecution;
use Cognesy\Instructor\Executors\Partials\ResponseAggregation\AggregationState;
use Cognesy\Polyglot\Inference\Collections\PartialInferenceResponseList;
use Cognesy\Polyglot\Inference\Data\InferenceResponse;
use Cognesy\Utils\Result\Result;
use Generator;

/**
 * Streams LLM responses using the new Partials pipeline.
 * Bridges InferenceProvider with PartialStreamFactory and yields
 * StructuredOutputExecution updates compatible with existing consumers.
 */
final class PartialStreamingRequestHandler implements CanExecuteStructuredOutput
{
    public function __construct(
        private readonly InferenceProvider $inferenceProvider,
        private readonly PartialStreamFactory $partials,
        private readonly CanGenerateResponse $processor,
        private readonly RetryHandler $retryHandler,
    ) {}

    #[\Override]
    public function nextUpdate(StructuredOutputExecution $execution): Generator {
        $responseModel = $execution->responseModel();
        assert($responseModel !== null, 'Response model cannot be null');
        $mode = $execution->outputMode();

        $processingResult = Result::failure('No response generated');
        $finalInference = null;
        $lastPartials = PartialInferenceResponseList::empty();

        while ($processingResult->isFailure() && !$execution->maxRetriesReached()) {
            // Get polyglot responses stream for this attempt
            $inferenceResponses = $this->inferenceProvider->getInference($execution)->stream()->responses();

            // Run Partials pipeline with accumulation enabled to satisfy legacy API
            $aggregateStream = $this->partials->makeObservableStream(
                source: $inferenceResponses,
                responseModel: $responseModel,
                mode: $mode,
                accumulatePartials: true,
            );

            $finalInference = null;
            $lastPartials = PartialInferenceResponseList::empty();

            /** @var AggregationState $aggregate */
            foreach ($aggregateStream as $aggregate) {
                $inference = $aggregate->toInferenceResponse();
                $finalInference = $inference;
                $lastPartials = $aggregate->partials();

                $updated = $execution->withCurrentAttempt(
                    inferenceResponse: $inference,
                    partialInferenceResponses: $aggregate->partials(),
                    errors: $this->retryHandler->errors(),
                );
                yield $updated;
            }

            // Ensure we have an inference to validate/finalize
            if ($finalInference === null) {
                $finalInference = InferenceResponse::empty();
            }

            // Process final response for this attempt
            $processingResult = $this->processor->makeResponse($finalInference, $responseModel, $mode);

            // On failure, record and prepare for retry
            if ($processingResult->isFailure()) {
                $execution = $this->retryHandler->handleError(
                    $processingResult,
                    $execution,
                    $finalInference,
                    $lastPartials,
                );
            }
        }

        // Finalize (or throw) and yield successful attempt
        $finalValue = $this->retryHandler->finalizeOrThrow($execution, $processingResult);
        yield $execution->withSuccessfulAttempt(
            inferenceResponse: $finalInference->withValue($finalValue),
            partialInferenceResponses: $lastPartials,
            returnedValue: $finalValue,
        );
    }
}
