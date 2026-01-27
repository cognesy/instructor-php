<?php declare(strict_types=1);

namespace Cognesy\Agents\Drivers\Testing;

use Cognesy\Agents\Core\Collections\ErrorList;
use Cognesy\Agents\Core\Collections\ToolExecutions;
use Cognesy\Agents\Core\Collections\Tools;
use Cognesy\Agents\Core\Contracts\CanExecuteToolCalls;
use Cognesy\Agents\Core\Contracts\CanUseTools;
use Cognesy\Agents\Core\Data\AgentState;
use Cognesy\Agents\Core\Data\AgentStep;
use Cognesy\Agents\Core\Enums\AgentStepType;
use Cognesy\Messages\Messages;
use Cognesy\Polyglot\Inference\Collections\ToolCalls;
use Cognesy\Polyglot\Inference\Data\InferenceResponse;
use Cognesy\Polyglot\Inference\Data\Usage;

final class DeterministicAgentDriver implements CanUseTools
{
    /** @var list<ScenarioStep> */
    private array $steps;
    private int $index;
    private string $defaultResponse;
    private Usage $defaultUsage;
    private AgentStepType $defaultStepType;

    /**
     * @param list<ScenarioStep> $steps
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
            $step instanceof ScenarioStep => $this->makeStep($step, $state, $executor),
            default => $this->defaultStep($state),
        };
    }

    private function resolveStep(): ScenarioStep|null {
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

        $response = new InferenceResponse(
            toolCalls: $step->toolCalls,
            usage: $step->usage,
        );
        $errors = $this->errorsForType($step->stepType);

        return new AgentStep(
            inputMessages: $state->messagesForInference(),
            outputMessages: Messages::fromString($step->response, 'assistant'),
            toolExecutions: $executions,
            inferenceResponse: $response,
            errors: $errors,
        );
    }

    private function defaultStep(AgentState $state): AgentStep {
        $response = new InferenceResponse(
            toolCalls: ToolCalls::empty(),
            usage: $this->defaultUsage,
        );
        $errors = $this->errorsForType($this->defaultStepType);

        return new AgentStep(
            inputMessages: $state->messagesForInference(),
            outputMessages: Messages::fromString($this->defaultResponse, 'assistant'),
            inferenceResponse: $response,
            errors: $errors,
        );
    }

    private function errorsForType(AgentStepType $type): ErrorList {
        return match ($type) {
            AgentStepType::Error => new ErrorList(new \RuntimeException('Deterministic step marked as error')),
            default => ErrorList::empty(),
        };
    }
}
