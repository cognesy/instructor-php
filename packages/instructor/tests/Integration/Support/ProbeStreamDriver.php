<?php declare(strict_types=1);

namespace Cognesy\Instructor\Tests\Support;

use Cognesy\Http\Data\HttpRequest;
use Cognesy\Http\Data\HttpResponse;
use Cognesy\Polyglot\Inference\Contracts\CanHandleInference;
use Cognesy\Polyglot\Inference\Data\DriverCapabilities;
use Cognesy\Polyglot\Inference\Data\InferenceRequest;
use Cognesy\Polyglot\Inference\Data\InferenceResponse;
use Cognesy\Polyglot\Inference\Enums\OutputMode;

/**
 * Test-only driver that returns a provided iterator for streaming
 * and counts calls to both sync and stream methods.
 */
class ProbeStreamDriver implements CanHandleInference
{
    public int $responseCalls = 0;
    public int $streamCalls = 0;

    public function __construct(
        private \Iterator $iterator,
        private ?InferenceResponse $syncResponse = null,
    ) {}

    public function makeResponseFor(InferenceRequest $request): InferenceResponse {
        $this->responseCalls++;
        return $this->syncResponse ?? new InferenceResponse(content: '');
    }

    public function makeStreamResponsesFor(InferenceRequest $request): iterable {
        $this->streamCalls++;
        return $this->iterator; // single live iterator instance
    }

    public function toHttpRequest(InferenceRequest $request): HttpRequest {
        return new HttpRequest(url: 'mock://probe', method: 'POST');
    }

    public function httpResponseToInference(HttpResponse $httpResponse): InferenceResponse {
        return $this->syncResponse ?? new InferenceResponse(content: '');
    }

    public function httpResponseToInferenceStream(HttpResponse $httpResponse): iterable {
        return $this->iterator;
    }

    public function capabilities(?string $model = null): DriverCapabilities
    {
        return new DriverCapabilities(
            outputModes: OutputMode::cases(),
            streaming: true,
            toolCalling: true,
            jsonSchema: true,
            responseFormatWithTools: true,
        );
    }
}
