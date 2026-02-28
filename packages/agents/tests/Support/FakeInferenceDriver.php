<?php declare(strict_types=1);

namespace Cognesy\Agents\Tests\Support;

use Cognesy\Polyglot\Inference\Contracts\CanProcessInferenceRequest;
use Cognesy\Polyglot\Inference\Data\DriverCapabilities;
use Cognesy\Polyglot\Inference\Data\InferenceRequest;
use Cognesy\Polyglot\Inference\Data\InferenceResponse;
use Cognesy\Polyglot\Inference\Data\PartialInferenceResponse;

class FakeInferenceDriver implements CanProcessInferenceRequest
{
    /** @var InferenceResponse[] */
    private array $responses;
    /** @var array<int, PartialInferenceResponse[]> */
    private array $streamBatches;
    public int $responseCalls = 0;
    public int $streamCalls = 0;

    public function __construct(array $responses = [], array $streamBatches = [])
    {
        $this->responses = $responses;
        $this->streamBatches = $streamBatches;
    }

    public function makeResponseFor(InferenceRequest $request): InferenceResponse
    {
        $this->responseCalls++;
        if (!empty($this->responses)) {
            return array_shift($this->responses);
        }
        return new InferenceResponse(content: '');
    }

    /** @return iterable<PartialInferenceResponse> */
    public function makeStreamResponsesFor(InferenceRequest $request): iterable
    {
        $this->streamCalls++;
        $batch = !empty($this->streamBatches) ? array_shift($this->streamBatches) : [];
        yield from $this->emitAccumulatedBatch($batch);
    }

    public function capabilities(?string $model = null): DriverCapabilities
    {
        return new DriverCapabilities();
    }

    /** @param PartialInferenceResponse[] $batch */
    private function emitAccumulatedBatch(array $batch): iterable
    {
        $previous = PartialInferenceResponse::empty();
        foreach ($batch as $item) {
            $current = $this->isAccumulated($item)
                ? $item
                : $item->withAccumulatedContent($previous);
            $previous = $current;
            yield $current;
        }
    }

    private function isAccumulated(PartialInferenceResponse $item): bool
    {
        return $item->hasContent()
            || $item->hasReasoningContent()
            || $item->toolCalls()->hasAny();
    }
}
