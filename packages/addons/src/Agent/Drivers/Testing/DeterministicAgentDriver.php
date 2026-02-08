<?php declare(strict_types=1);

/**
 * @deprecated Use cognesy/instructor-agents package instead. This class will be removed in a future version.
 */
namespace Cognesy\Addons\Agent\Drivers\Testing;

use Cognesy\Addons\Agent\Core\Collections\ToolExecutions;
use Cognesy\Addons\Agent\Core\Collections\Tools;
use Cognesy\Addons\Agent\Core\Contracts\CanExecuteToolCalls;
use Cognesy\Addons\Agent\Core\Contracts\CanUseTools;
use Cognesy\Addons\Agent\Core\Data\AgentState;
use Cognesy\Addons\Agent\Core\Data\AgentStep;
use Cognesy\Addons\Agent\Core\Enums\AgentStepType;
use Cognesy\Messages\Messages;
use Cognesy\Polyglot\Inference\Collections\ToolCalls;
use Cognesy\Polyglot\Inference\Data\Usage;

final class DeterministicAgentDriver implements CanUseTools
{
    /** @var array<int, ScenarioStep|AgentStep> */
    private array $steps;
    private int $index;
    private string $defaultResponse;
    private Usage $defaultUsage;
    private AgentStepType $defaultStepType;

    /**
     * @param array<int, ScenarioStep|AgentStep> $steps
     */
    public function __construct(
        array $steps = [],
        string $defaultResponse = 'ok',
        ?Usage $defaultUsage = null,
        ?AgentStepType $defaultStepType = null,
        int $startIndex = 0,
    ) {
        $this->steps = $steps;
        $this->index = $startIndex;
        $this->defaultResponse = $defaultResponse;
        $this->defaultUsage = $defaultUsage ?? new Usage(0, 0);
        $this->defaultStepType = $defaultStepType ?? AgentStepType::FinalResponse;
    }

    public static function fromSteps(ScenarioStep ...$steps): self {
        return new self(array_values($steps));
    }

    public static function fromResponses(string ...$responses): self {
        if ($responses === []) {
            return new self();
        }
        $steps = array_map(
            static fn(string $response): ScenarioStep => ScenarioStep::final($response),
            $responses,
        );
        $default = $responses[array_key_last($responses)];
        return new self(array_values($steps), $default);
    }

    public function withSteps(ScenarioStep ...$steps): self {
        return new self(
            steps: array_values($steps),
            defaultResponse: $this->defaultResponse,
            defaultUsage: $this->defaultUsage,
            defaultStepType: $this->defaultStepType,
            startIndex: 0,
        );
    }

    #[\Override]
    public function useTools(AgentState $state, Tools $tools, CanExecuteToolCalls $executor): AgentStep
    {
        $step = $this->resolveStep();
        return match (true) {
            $step instanceof AgentStep => $step,
            $step instanceof ScenarioStep => $this->makeStep($step, $state, $executor),
            default => $this->defaultStep($state),
        };
    }

    private function resolveStep(): ScenarioStep|AgentStep|null {
        if ($this->steps === []) {
            return null;
        }
        if ($this->index >= count($this->steps)) {
            return $this->steps[array_key_last($this->steps)];
        }
        $step = $this->steps[$this->index];
        $this->index++;
        return $step;
    }

    private function makeStep(ScenarioStep $step, AgentState $state, CanExecuteToolCalls $executor): AgentStep {
        if ($step->toolCalls === null) {
            return $step->toAgentStep($state);
        }

        $executions = $step->executeTools
            ? $executor->useTools($step->toolCalls, $state)
            : new ToolExecutions();

        return new AgentStep(
            inputMessages: $state->messagesForInference(),
            outputMessages: Messages::fromString($step->response, 'assistant'),
            usage: $step->usage,
            toolCalls: $step->toolCalls,
            toolExecutions: $executions,
            stepType: $step->stepType,
        );
    }

    private function defaultStep(AgentState $state): AgentStep {
        return new AgentStep(
            inputMessages: $state->messagesForInference(),
            outputMessages: Messages::fromString($this->defaultResponse, 'assistant'),
            usage: $this->defaultUsage,
            stepType: $this->defaultStepType,
        );
    }
}

final readonly class ScenarioStep
{
    public function __construct(
        public string $response,
        public Usage $usage,
        public AgentStepType $stepType,
        public ?ToolCalls $toolCalls = null,
        public bool $executeTools = true,
    ) {
    }

    public static function final(string $response, ?Usage $usage = null): self {
        return new self(
            response: $response,
            usage: $usage ?? new Usage(0, 0),
            stepType: AgentStepType::FinalResponse,
        );
    }

    public static function tool(string $response, ?Usage $usage = null): self {
        return new self(
            response: $response,
            usage: $usage ?? new Usage(0, 0),
            stepType: AgentStepType::ToolExecution,
        );
    }

    public static function error(string $response, ?Usage $usage = null): self {
        return new self(
            response: $response,
            usage: $usage ?? new Usage(0, 0),
            stepType: AgentStepType::Error,
        );
    }

    public static function toolCall(
        string $toolName,
        array $args = [],
        string $response = '',
        ?Usage $usage = null,
        bool $executeTools = true,
    ): self {
        $toolCalls = ToolCalls::empty()->withAddedToolCall($toolName, $args);
        return new self(
            response: $response,
            usage: $usage ?? new Usage(0, 0),
            stepType: AgentStepType::ToolExecution,
            toolCalls: $toolCalls,
            executeTools: $executeTools,
        );
    }

    public function toAgentStep(AgentState $state): AgentStep {
        return new AgentStep(
            inputMessages: $state->messagesForInference(),
            outputMessages: Messages::fromString($this->response, 'assistant'),
            usage: $this->usage,
            stepType: $this->stepType,
        );
    }
}
