<?php declare(strict_types=1);

/**
 * @deprecated Use cognesy/agents package instead. This class will be removed in a future version.
 */
namespace Cognesy\Addons\Agent\Core\Contracts;

use Cognesy\Addons\Agent\Core\Enums\AgentStepType;
use Cognesy\Polyglot\Inference\Collections\ToolCalls;
use Cognesy\Polyglot\Inference\Data\InferenceResponse;
use Cognesy\Polyglot\Inference\Enums\InferenceFinishReason;

interface HasStepToolCalls
{
    public function toolCalls(): ToolCalls;

    public function hasToolCalls(): bool;

    public function finishReason(): ?InferenceFinishReason;

    public function inferenceResponse(): ?InferenceResponse;

    public function stepType(): AgentStepType;
}