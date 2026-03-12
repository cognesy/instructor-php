<?php declare(strict_types=1);

namespace Cognesy\Addons\ToolUse\Step;

use Cognesy\Addons\ToolUse\Collections\ToolExecutions;
use Cognesy\Addons\ToolUse\Enums\ToolUseStepType;
use Cognesy\Addons\ToolUse\Exceptions\ToolExecutionException;
use Cognesy\Messages\Messages;
use Cognesy\Messages\ToolCalls;
use Cognesy\Polyglot\Inference\Data\InferenceUsage;
use Throwable;

trait HandlesStepToolExecutions
{
    private readonly ToolExecutions $toolExecutions;

    public function toolExecutions(): ToolExecutions {
        return $this->toolExecutions;
    }

    public function errorExecutions(): ToolExecutions {
        return new ToolExecutions(...$this->toolExecutions->havingErrors());
    }

    public static function failure(Messages $inputMessages, Throwable $error): self {
        $normalized = $error instanceof Throwable
            ? $error
            : new ToolExecutionException('Unknown tool-use error');

        return new self(
            inputMessages: $inputMessages,
            outputMessages: Messages::empty(),
            usage: InferenceUsage::none(),
            toolCalls: new ToolCalls(),
            toolExecutions: new ToolExecutions(),
            inferenceResponse: null,
            stepType: ToolUseStepType::Error,
            errors: [$normalized],
        );
    }

}