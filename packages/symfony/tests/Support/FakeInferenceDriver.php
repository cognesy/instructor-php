<?php

declare(strict_types=1);

namespace Cognesy\Instructor\Symfony\Tests\Support;

use Cognesy\Polyglot\Inference\Contracts\CanProcessInferenceRequest;
use Cognesy\Polyglot\Inference\Data\DriverCapabilities;
use Cognesy\Polyglot\Inference\Data\InferenceRequest;
use Cognesy\Polyglot\Inference\Data\InferenceResponse;
use Cognesy\Polyglot\Inference\Data\PartialInferenceDelta;

final class FakeInferenceDriver implements CanProcessInferenceRequest
{
    /** @param list<InferenceResponse> $responses */
    public function __construct(private array $responses = []) {}

    public function makeResponseFor(InferenceRequest $request): InferenceResponse
    {
        return array_shift($this->responses) ?? new InferenceResponse(content: '');
    }

    /** @return iterable<PartialInferenceDelta> */
    public function makeStreamDeltasFor(InferenceRequest $request): iterable
    {
        yield from [];
    }

    public function capabilities(?string $model = null): DriverCapabilities
    {
        return new DriverCapabilities();
    }
}
