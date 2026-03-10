<?php declare(strict_types=1);

namespace Cognesy\Instructor\Tests\Support;

use Closure;
use Cognesy\Polyglot\Inference\Contracts\CanProcessInferenceRequest;
use Cognesy\Polyglot\Inference\Data\DriverCapabilities;
use Cognesy\Polyglot\Inference\Data\InferenceRequest;
use Cognesy\Polyglot\Inference\Data\InferenceResponse;
use Cognesy\Polyglot\Inference\Data\PartialInferenceDelta;

/**
 * Lightweight fake inference driver for unit tests.
 * - Returns queued InferenceResponse objects for non-streaming
 * - Returns queued arrays of PartialInferenceDelta fixtures for streaming
 */
class FakeInferenceDriver implements CanProcessInferenceRequest
{
    /** @var InferenceResponse[] */
    private array $responses;
    /** @var array<int, array<PartialInferenceDelta>> */
    private array $streamBatches;
    /** @var null|Closure(InferenceRequest, self): iterable<PartialInferenceDelta> */
    private ?Closure $onStream;
    public int $responseCalls = 0;
    public int $streamCalls = 0;

    /**
     * @param InferenceResponse[] $responses
     * @param array<int, array<PartialInferenceDelta>> $streamBatches
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

    /** @param iterable<PartialInferenceDelta> $batch */
    private function emitBatch(iterable $batch): iterable
    {
        foreach ($batch as $delta) {
            assert($delta instanceof PartialInferenceDelta);
            yield $delta;
        }
    }
}
