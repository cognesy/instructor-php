<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Inference;

use Cognesy\Polyglot\Inference\Contracts\CanCreateInference;
use Cognesy\Polyglot\Inference\Contracts\CanProcessInferenceRequest;
use Cognesy\Polyglot\Inference\Data\InferenceExecution;
use Cognesy\Polyglot\Inference\Data\InferenceRequest;
use Cognesy\Polyglot\Inference\Data\Pricing;
use Psr\EventDispatcher\EventDispatcherInterface;

final class InferenceRuntime implements CanCreateInference
{
    public function __construct(
        private readonly CanProcessInferenceRequest $driver,
        private readonly EventDispatcherInterface $events,
        private readonly ?Pricing $pricing = null,
    ) {}

    #[\Override]
    public function create(InferenceRequest $request): PendingInference {
        return new PendingInference(
            execution: InferenceExecution::fromRequest($request),
            driver: $this->driver,
            eventDispatcher: $this->events,
            pricing: $this->pricing,
        );
    }
}

