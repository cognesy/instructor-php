<?php declare(strict_types=1);

namespace Cognesy\Addons\Agent\Step;

use Cognesy\Addons\Agent\Enums\AgentStepType;
use Cognesy\Polyglot\Inference\Collections\ToolCalls;
use Cognesy\Polyglot\Inference\Data\InferenceResponse;
use Cognesy\Polyglot\Inference\Enums\InferenceFinishReason;

trait HandlesStepToolCalls
{
    private readonly ToolCalls $toolCalls;
    private readonly InferenceResponse $inferenceResponse;
    private readonly AgentStepType $stepType;

    public function toolCalls(): ToolCalls {
        return $this->toolCalls;
    }

    public function hasToolCalls(): bool {
        return $this->toolCalls()->count() > 0;
    }

    public function finishReason(): ?InferenceFinishReason {
        return $this->inferenceResponse->finishReason();
    }

    public function inferenceResponse(): ?InferenceResponse {
        return $this->inferenceResponse;
    }

    public function stepType(): AgentStepType {
        return $this->stepType;
    }
}