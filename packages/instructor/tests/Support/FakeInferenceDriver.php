<?php declare(strict_types=1);

namespace Tests\Instructor\Support;

use Cognesy\Http\Contracts\HttpResponse;
use Cognesy\Http\Data\HttpRequest;
use Cognesy\Polyglot\Inference\Contracts\CanHandleInference;
use Cognesy\Polyglot\Inference\Data\InferenceRequest;
use Cognesy\Polyglot\Inference\Data\InferenceResponse;
use Cognesy\Polyglot\Inference\Data\PartialInferenceResponse;

/**
 * Lightweight fake inference driver for unit tests.
 * - Returns queued InferenceResponse objects for non-streaming
 * - Returns queued arrays of PartialInferenceResponse for streaming
 */
class FakeInferenceDriver implements CanHandleInference
{
    /** @var InferenceResponse[] */
    private array $responses;
    /** @var array<int, PartialInferenceResponse[]> */
    private array $streamBatches;
    public int $responseCalls = 0;
    public int $streamCalls = 0;

    /**
     * @param InferenceResponse[] $responses
     * @param array<int, PartialInferenceResponse[]> $streamBatches
     */
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

    public function toHttpRequest(InferenceRequest $request): HttpRequest
    {
        return new HttpRequest(
            url: 'https://mock.local/llm',
            method: 'POST',
            headers: [],
            body: ['messages' => $request->messages()],
            options: ['stream' => $request->isStreamed()],
        );
    }

    public function httpResponseToInference(HttpResponse $httpResponse): InferenceResponse
    {
        return new InferenceResponse(content: '');
    }

    /** @return iterable<PartialInferenceResponse> */
    public function httpResponseToInferenceStream(HttpResponse $httpResponse): iterable
    {
        if (!empty($this->streamBatches)) {
            foreach ($this->streamBatches[0] as $item) {
                yield $item;
            }
        }
    }
}
