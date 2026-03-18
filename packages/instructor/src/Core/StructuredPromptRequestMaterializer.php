<?php declare(strict_types=1);

namespace Cognesy\Instructor\Core;

use Cognesy\Instructor\Contracts\CanMaterializeRequest;
use Cognesy\Instructor\Data\StructuredOutputExecution;
use Cognesy\Instructor\Data\StructuredPromptPlan;
use Cognesy\Instructor\Telemetry\StructuredOutputTelemetry;
use Cognesy\Polyglot\Inference\Data\InferenceRequest;
use RuntimeException;

final class StructuredPromptRequestMaterializer implements CanMaterializeRequest
{
    public function __construct(
        private readonly ?StructuredPromptPlanBuilder $planBuilder = null,
        private readonly ?StructuredPromptCacheProjector $cacheProjector = null,
    ) {}

    #[\Override]
    public function toInferenceRequest(StructuredOutputExecution $execution): InferenceRequest
    {
        $request = $execution->request();
        $responseModel = $execution->responseModel();
        assert($responseModel !== null, 'Response model cannot be null');

        $plan = $this->toPlan($execution);

        return new InferenceRequest(
            messages: $plan->toLiveMessages(),
            model: $request->model(),
            tools: $responseModel->toolDefinitions(),
            toolChoice: $responseModel->toolChoice(),
            responseFormat: $responseModel->responseFormat(),
            options: $request->options(),
            cachedContext: ($this->cacheProjector ?? new StructuredPromptCacheProjector())
                ->projectMessages($plan->toCachedMessages()),
            responseCachePolicy: $execution->config()->responseCachePolicy(),
            telemetryCorrelation: StructuredOutputTelemetry::inferenceCorrelation($execution),
        );
    }

    public function toMessages(StructuredOutputExecution $execution): \Cognesy\Messages\Messages
    {
        $plan = $this->toPlan($execution);
        $rendered = $plan->toFlattenedMessages();

        if ($rendered->isEmpty()) {
            throw new RuntimeException('Request materialization produced no messages after rendering.');
        }

        return $rendered;
    }

    public function toPlan(StructuredOutputExecution $execution): StructuredPromptPlan
    {
        return ($this->planBuilder ?? new StructuredPromptPlanBuilder())->build($execution);
    }
}
