<?php declare(strict_types=1);

namespace Tests\Addons\Support;

use Cognesy\Polyglot\Inference\Contracts\CanProcessInferenceRequest;
use Cognesy\Polyglot\Inference\Data\DriverCapabilities;
use Cognesy\Polyglot\Inference\Data\InferenceRequest;
use Cognesy\Polyglot\Inference\Data\InferenceResponse;
use Cognesy\Polyglot\Inference\Data\PartialInferenceResponse;

class FakeInferenceRequestDriver implements CanProcessInferenceRequest
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
        foreach ($batch as $item) {
            yield $item;
        }
    }

    public function capabilities(?string $model = null): DriverCapabilities
    {
        return new DriverCapabilities();
    }
}
