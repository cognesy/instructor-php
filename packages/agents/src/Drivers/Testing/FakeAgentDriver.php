<?php declare(strict_types=1);

namespace Cognesy\Agents\Drivers\Testing;

use Cognesy\Agents\Core\Collections\ErrorList;
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
use Cognesy\Polyglot\Inference\LLMProvider;

final class FakeAgentDriver implements CanUseTools
{
    /** @var list<ScenarioStep> */
    private array $steps;
    private int $index;
    private string $defaultResponse;
    private Usage $defaultUsage;
    private AgentStepType $defaultStepType;
    /** @var list<ScenarioStep>|null */
    private ?array $childSteps;

    /**
     * @param list<ScenarioStep> $steps
     * @param list<ScenarioStep>|null $childSteps Steps for spawned subagent drivers
     */
    public function __construct(
        array $steps = [],
        string $defaultResponse = 'ok',
        ?Usage $defaultUsage = null,
        ?AgentStepType $defaultStepType = null,
        int $startIndex = 0,
        ?array $childSteps = null,
    ) {
        $this->steps = $steps;
        $this->index = $startIndex;
        $this->defaultResponse = $defaultResponse;
        $this->defaultUsage = $defaultUsage ?? new Usage(0, 0);
        $this->defaultStepType = $defaultStepType ?? AgentStepType::FinalResponse;
        $this->childSteps = $childSteps;
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
            childSteps: $this->childSteps,
        );
    }

    /**
     * @param list<ScenarioStep> $steps Scenario steps for spawned subagent drivers
     */
    public function withChildSteps(array $steps): self {
        return new self(
            steps: $this->steps,
            defaultResponse: $this->defaultResponse,
            defaultUsage: $this->defaultUsage,
            defaultStepType: $this->defaultStepType,
            startIndex: $this->index,
            childSteps: $steps,
        );
    }

    public function withLLMProvider(LLMProvider $provider): self {
        $childSteps = $this->childSteps ?? [ScenarioStep::final('ok')];
        return new self(
            steps: $childSteps,
            defaultResponse: $childSteps[array_key_last($childSteps)]->response ?? 'ok',
            defaultUsage: $this->defaultUsage,
            defaultStepType: $this->defaultStepType,
            startIndex: 0,
        );
    }

    #[\Override]
    public function useTools(AgentState $state, Tools $tools, CanExecuteToolCalls $executor): AgentState {
        $step = $this->resolveStep();
        $step = match (true) {
            $step instanceof ScenarioStep => $this->makeToolUseStep($step, $state, $executor),
            default => $this->defaultStep($state),
        };
        return $state->withCurrentStep($step);
    }

    private function resolveStep(): ?ScenarioStep {
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

    private function makeToolUseStep(ScenarioStep $step, AgentState $state, CanExecuteToolCalls $executor): AgentStep {
        $toolCalls = $step->toolCalls ?? ToolCalls::empty();
        if ($toolCalls->hasNone()) {
            return $step->toAgentStep($state);
        }
        $response = new InferenceResponse(
            toolCalls: $toolCalls,
            usage: $step->usage,
        );
        $executions = match($step->executeTools) {
            true => $executor->executeTools($toolCalls, $state),
            false => null,
        };
        $errors = $this->errorsForType($step->stepType);
        return new AgentStep(
            inputMessages: $state->context()->messagesForInference(),
            outputMessages: Messages::fromString($step->response, 'assistant'),
            inferenceResponse: $response,
            toolExecutions: $executions,
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
            inputMessages: $state->context()->messagesForInference(),
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
