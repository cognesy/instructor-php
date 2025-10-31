<?php declare(strict_types=1);

namespace Cognesy\Addons\ToolUse\Step;

use Cognesy\Addons\ToolUse\Enums\ToolUseStepType;
use Cognesy\Polyglot\Inference\Collections\ToolCalls;
use Cognesy\Polyglot\Inference\Data\InferenceResponse;
use Cognesy\Polyglot\Inference\Enums\InferenceFinishReason;

interface HasStepToolCalls
{
    public function toolCalls(): ToolCalls;

    public function hasToolCalls(): bool;

    public function finishReason(): ?InferenceFinishReason;

    public function inferenceResponse(): ?InferenceResponse;

    public function stepType(): ToolUseStepType;
}