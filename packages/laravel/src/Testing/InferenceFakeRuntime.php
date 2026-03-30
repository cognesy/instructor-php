<?php

declare(strict_types=1);

namespace Cognesy\Instructor\Laravel\Testing;

use Cognesy\Events\Dispatchers\EventDispatcher;
use Cognesy\Polyglot\Inference\Contracts\CanCreateInference;
use Cognesy\Polyglot\Inference\Data\InferenceExecution;
use Cognesy\Polyglot\Inference\Data\InferenceRequest;
use Cognesy\Polyglot\Inference\Data\InferenceResponse;
use Cognesy\Polyglot\Inference\PendingInference;

final class InferenceFakeRuntime implements CanCreateInference
{
    /** @var list<InferenceRequest> */
    private array $recorded = [];

    private readonly EventDispatcher $events;

    public function __construct(
        private readonly InferenceFakeDriver $driver,
    ) {
        $this->events = new EventDispatcher('laravel-structured-output-fake.inference');
    }

    public function create(InferenceRequest $request): PendingInference
    {
        $this->recorded[] = $request;

        return new PendingInference(
            execution: InferenceExecution::fromRequest($request),
            driver: $this->driver,
            eventDispatcher: $this->events,
        );
    }

    public function recorded(): array
    {
        return $this->recorded;
    }
}

final class InferenceFakeDriver implements \Cognesy\Polyglot\Inference\Contracts\CanProcessInferenceRequest
{
    public function __construct(
        private readonly InferenceResponse $response,
    ) {}

    public function makeResponseFor(InferenceRequest $request): InferenceResponse
    {
        return $this->response;
    }

    public function makeStreamDeltasFor(InferenceRequest $request): iterable
    {
        return [];
    }

    public function capabilities(?string $model = null): \Cognesy\Polyglot\Inference\Data\DriverCapabilities
    {
        return new \Cognesy\Polyglot\Inference\Data\DriverCapabilities();
    }
}
