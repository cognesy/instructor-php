<?php

declare(strict_types=1);

namespace Cognesy\Instructor\Symfony\Tests\Support;

use Cognesy\Events\Dispatchers\EventDispatcher;
use Cognesy\Instructor\Tests\Support\FakeInferenceDriver;
use Cognesy\Polyglot\Inference\Contracts\CanCreateInference;
use Cognesy\Polyglot\Inference\Data\InferenceExecution;
use Cognesy\Polyglot\Inference\Data\InferenceRequest;
use Cognesy\Polyglot\Inference\Data\InferenceResponse;
use Cognesy\Polyglot\Inference\PendingInference;

final class InferenceFakeRuntime implements CanCreateInference
{
    /** @var list<InferenceRequest> */
    private array $recorded = [];

    /**
     * @param list<InferenceResponse> $responses
     * @param array<int, array<mixed>> $streamBatches
     */
    public function __construct(
        private readonly FakeInferenceDriver $driver,
        private readonly EventDispatcher $events = new EventDispatcher('symfony-inference-fake'),
    ) {}

    public static function fromResponses(string|InferenceResponse ...$responses): self
    {
        return new self(new FakeInferenceDriver(responses: array_map(
            static fn (string|InferenceResponse $response): InferenceResponse => match (true) {
                $response instanceof InferenceResponse => $response,
                default => new InferenceResponse(content: $response),
            },
            $responses,
        )));
    }

    /** @return list<InferenceRequest> */
    public function recorded(): array
    {
        return $this->recorded;
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
}
