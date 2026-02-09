<?php declare(strict_types=1);

/**
 * @deprecated Use cognesy/agents package instead. This class will be removed in a future version.
 */
namespace Cognesy\Addons\Agent\Core\Traits\Step;

use Cognesy\Addons\Agent\Core\Collections\ToolExecutions;
use Cognesy\Addons\Agent\Core\Enums\AgentStepType;
use Cognesy\Addons\Agent\Exceptions\ToolExecutionException;
use Cognesy\Messages\Messages;
use Cognesy\Polyglot\Inference\Collections\ToolCalls;
use Cognesy\Polyglot\Inference\Data\Usage;
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
            usage: Usage::none(),
            toolCalls: new ToolCalls(),
            toolExecutions: new ToolExecutions(),
            inferenceResponse: null,
            stepType: AgentStepType::Error,
            errors: [$normalized],
        );
    }

}