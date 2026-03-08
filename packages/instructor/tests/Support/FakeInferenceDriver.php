<?php declare(strict_types=1);

namespace Cognesy\Instructor\Tests\Support;

use Closure;
use Cognesy\Polyglot\Inference\Contracts\CanProcessInferenceRequest;
use Cognesy\Polyglot\Inference\Data\DriverCapabilities;
use Cognesy\Polyglot\Inference\Data\InferenceRequest;
use Cognesy\Polyglot\Inference\Data\InferenceResponse;
use Cognesy\Polyglot\Inference\Data\PartialInferenceDelta;
use Cognesy\Polyglot\Inference\Data\PartialInferenceResponse;

/**
 * Lightweight fake inference driver for unit tests.
 * - Returns queued InferenceResponse objects for non-streaming
 * - Returns queued arrays of PartialInferenceDelta or PartialInferenceResponse fixtures for streaming
 */
class FakeInferenceDriver implements CanProcessInferenceRequest
{
    /** @var InferenceResponse[] */
    private array $responses;
    /** @var array<int, array<PartialInferenceDelta|PartialInferenceResponse>> */
    private array $streamBatches;
    /** @var null|Closure(InferenceRequest, self): iterable<PartialInferenceDelta|PartialInferenceResponse> */
    private ?Closure $onStream;
    public int $responseCalls = 0;
    public int $streamCalls = 0;

    /**
     * @param InferenceResponse[] $responses
     * @param array<int, array<PartialInferenceDelta|PartialInferenceResponse>> $streamBatches
     */
    public function __construct(
        array $responses = [],
        array $streamBatches = [],
        ?Closure $onStream = null,
    ) {
        $this->responses = $responses;
        $this->streamBatches = $streamBatches;
        $this->onStream = $onStream;
    }

    public function makeResponseFor(InferenceRequest $request): InferenceResponse {
        $this->responseCalls++;
        if (!empty($this->responses)) {
            return array_shift($this->responses);
        }
        return new InferenceResponse(content: '');
    }

    /** @return iterable<PartialInferenceDelta> */
    public function makeStreamDeltasFor(InferenceRequest $request): iterable {
        $this->streamCalls++;
        if ($this->onStream !== null) {
            yield from $this->emitBatch(($this->onStream)($request, $this));
            return;
        }

        $batch = !empty($this->streamBatches) ? array_shift($this->streamBatches) : [];
        yield from $this->emitBatch($batch);
    }

    public function capabilities(?string $model = null): DriverCapabilities {
        return new DriverCapabilities();
    }

    /** @param iterable<PartialInferenceDelta|PartialInferenceResponse> $batch */
    private function emitBatch(iterable $batch): iterable
    {
        $items = is_array($batch) ? $batch : iterator_to_array($batch, false);
        if ($items === []) {
            return;
        }

        if ($items[0] instanceof PartialInferenceDelta) {
            foreach ($items as $delta) {
                assert($delta instanceof PartialInferenceDelta);
                yield $delta;
            }
            return;
        }

        foreach (FakeStreamFactory::from(...$items) as $partialResponse) {
            yield new PartialInferenceDelta(
                contentDelta: $partialResponse->contentDelta,
                reasoningContentDelta: $partialResponse->reasoningContentDelta,
                toolId: $partialResponse->toolId(),
                toolName: $partialResponse->toolName(),
                toolArgs: $partialResponse->toolArgs(),
                finishReason: $partialResponse->finishReason(),
                usage: $partialResponse->usage(),
                usageIsCumulative: $partialResponse->isUsageCumulative(),
                value: $partialResponse->value(),
            );
        }
    }

}
