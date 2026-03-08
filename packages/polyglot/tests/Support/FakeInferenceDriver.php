<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Tests\Support;

use Closure;
use Cognesy\Polyglot\Inference\Contracts\CanProcessInferenceRequest;
use Cognesy\Polyglot\Inference\Data\DriverCapabilities;
use Cognesy\Polyglot\Inference\Data\InferenceRequest;
use Cognesy\Polyglot\Inference\Data\InferenceResponse;
use Cognesy\Polyglot\Inference\Data\PartialInferenceDelta;
use Cognesy\Polyglot\Inference\Data\PartialInferenceResponse;

final class FakeInferenceDriver implements CanProcessInferenceRequest
{
    /** @var InferenceResponse[] */
    private array $responses;
    /** @var array<int, PartialInferenceResponse[]> */
    private array $streamBatches;
    /** @var null|Closure(InferenceRequest, self):InferenceResponse */
    private ?Closure $onResponse;
    /** @var null|Closure(InferenceRequest, self):iterable<PartialInferenceResponse> */
    private ?Closure $onStream;
    private DriverCapabilities $capabilities;

    public int $responseCalls = 0;
    public int $streamCalls = 0;

    /**
     * @param InferenceResponse[] $responses
     * @param array<int, PartialInferenceResponse[]> $streamBatches
     * @param null|Closure(InferenceRequest, self):InferenceResponse $onResponse
     * @param null|Closure(InferenceRequest, self):iterable<PartialInferenceResponse> $onStream
     */
    public function __construct(
        array $responses = [],
        array $streamBatches = [],
        ?Closure $onResponse = null,
        ?Closure $onStream = null,
        ?DriverCapabilities $capabilities = null,
    ) {
        $this->responses = $responses;
        $this->streamBatches = $streamBatches;
        $this->onResponse = $onResponse;
        $this->onStream = $onStream;
        $this->capabilities = $capabilities ?? new DriverCapabilities();
    }

    public function makeResponseFor(InferenceRequest $request): InferenceResponse {
        $this->responseCalls++;
        if ($this->onResponse !== null) {
            return ($this->onResponse)($request, $this);
        }
        if (!empty($this->responses)) {
            return array_shift($this->responses);
        }
        return InferenceResponse::empty();
    }

    /** @return iterable<PartialInferenceDelta> */
    public function makeStreamDeltasFor(InferenceRequest $request): iterable {
        $this->streamCalls++;
        if ($this->onStream !== null) {
            yield from $this->emitDeltas(($this->onStream)($request, $this));
            return;
        }

        $batch = !empty($this->streamBatches)
            ? array_shift($this->streamBatches)
            : [];
        yield from $this->emitDeltas($batch);
    }

    public function capabilities(?string $model = null): DriverCapabilities {
        return $this->capabilities;
    }

    /** @param iterable<PartialInferenceResponse> $partials */
    private function emitDeltas(iterable $partials): iterable {
        foreach ($partials as $item) {
            yield new PartialInferenceDelta(
                contentDelta: $item->contentDelta,
                reasoningContentDelta: $item->reasoningContentDelta,
                toolId: $item->toolId(),
                toolName: $item->toolName(),
                toolArgs: $item->toolArgs(),
                finishReason: $item->finishReason(),
                usage: $item->usage(),
                usageIsCumulative: $item->isUsageCumulative(),
                value: $item->value(),
            );
        }
    }
}
