<?php declare(strict_types=1);

namespace Cognesy\Instructor\Tests\Integration\Support;

use Cognesy\Polyglot\Inference\Contracts\CanProcessInferenceRequest;
use Cognesy\Polyglot\Inference\Data\DriverCapabilities;
use Cognesy\Polyglot\Inference\Data\InferenceRequest;
use Cognesy\Polyglot\Inference\Data\InferenceResponse;
use Cognesy\Polyglot\Inference\Data\PartialInferenceDelta;

/**
 * Test-only driver that returns a provided iterator for streaming
 * and counts calls to both sync and stream methods.
 */
class ProbeStreamDriver implements CanProcessInferenceRequest
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

    public function makeStreamDeltasFor(InferenceRequest $request): iterable {
        $this->streamCalls++;
        foreach ($this->iterator as $item) {
            if (!$item instanceof PartialInferenceDelta) {
                throw new \InvalidArgumentException('ProbeStreamDriver expects deltas.');
            }

            yield $item;
        }
    }

    public function capabilities(?string $model = null): DriverCapabilities
    {
        return new DriverCapabilities();
    }
}
