<?php declare(strict_types=1);

namespace Cognesy\Agents\Drivers\Testing;

use Cognesy\Agents\Collections\ErrorList;
use Cognesy\Agents\Collections\Tools;
use Cognesy\Agents\Context\CanAcceptMessageCompiler;
use Cognesy\Agents\Context\CanCompileMessages;
use Cognesy\Agents\Context\Compilers\SelectedSections;
use Cognesy\Agents\Data\AgentState;
use Cognesy\Agents\Data\AgentStep;
use Cognesy\Agents\Drivers\CanAcceptToolRuntime;
use Cognesy\Agents\Drivers\CanUseTools;
use Cognesy\Agents\Enums\AgentStepType;
use Cognesy\Agents\Interception\PassThroughInterceptor;
use Cognesy\Agents\Tool\ToolExecutor;
use Cognesy\Agents\Tool\Contracts\CanExecuteToolCalls;
use Cognesy\Events\Dispatchers\EventDispatcher;
use Cognesy\Messages\Messages;
use Cognesy\Polyglot\Inference\Collections\ToolCalls;
use Cognesy\Polyglot\Inference\Config\LLMConfig;
use Cognesy\Polyglot\Inference\Contracts\CanAcceptLLMConfig;
use Cognesy\Polyglot\Inference\Contracts\CanAcceptLLMProvider;
use Cognesy\Polyglot\Inference\Data\InferenceResponse;
use Cognesy\Polyglot\Inference\Data\Usage;
use Cognesy\Polyglot\Inference\LLMProvider;

final class FakeAgentDriver implements CanUseTools, CanAcceptToolRuntime, CanAcceptLLMProvider, CanAcceptLLMConfig, CanAcceptMessageCompiler
{
    private const CURSOR_METADATA_KEY = 'driver.fake.cursor';

    /** @var list<ScenarioStep> */
    private array $steps;
    private string $defaultResponse;
    private Usage $defaultUsage;
    private AgentStepType $defaultStepType;
    /** @var list<ScenarioStep>|null */
    private ?array $childSteps;
    private CanCompileMessages $messageCompiler;
    private Tools $tools;
    private CanExecuteToolCalls $executor;

    /**
     * @param list<ScenarioStep> $steps
     * @param list<ScenarioStep>|null $childSteps Steps for spawned subagent drivers
     */
    public function __construct(
        array $steps = [],
        string $defaultResponse = 'ok',
        ?Usage $defaultUsage = null,
        ?AgentStepType $defaultStepType = null,
        ?array $childSteps = null,
        ?CanCompileMessages $messageCompiler = null,
        ?Tools $tools = null,
        ?CanExecuteToolCalls $executor = null,
    ) {
        $this->steps = $steps;
        $this->defaultResponse = $defaultResponse;
        $this->defaultUsage = $defaultUsage ?? new Usage(0, 0);
        $this->defaultStepType = $defaultStepType ?? AgentStepType::FinalResponse;
        $this->childSteps = $childSteps;
        $this->messageCompiler = $messageCompiler ?? SelectedSections::default();
        $this->tools = $tools ?? new Tools();
        $this->executor = $executor ?? new ToolExecutor(
            tools: $this->tools,
            events: new EventDispatcher('fake-agent-driver'),
            interceptor: new PassThroughInterceptor(),
        );
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
            childSteps: $this->childSteps,
            messageCompiler: $this->messageCompiler,
            tools: $this->tools,
            executor: $this->executor,
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
            childSteps: $steps,
            messageCompiler: $this->messageCompiler,
            tools: $this->tools,
            executor: $this->executor,
        );
    }

    #[\Override]
    public function llmProvider(): LLMProvider {
        return LLMProvider::new();
    }

    #[\Override]
    public function withLLMProvider(LLMProvider $llm): static {
        return $this->forSubagent();
    }

    #[\Override]
    public function withLLMConfig(LLMConfig $config): static {
        return $this->forSubagent();
    }

    private function forSubagent(): self {
        $childSteps = $this->childSteps ?? [ScenarioStep::final('ok')];
        return new self(
            steps: $childSteps,
            defaultResponse: $childSteps[array_key_last($childSteps)]->response ?? 'ok',
            defaultUsage: $this->defaultUsage,
            defaultStepType: $this->defaultStepType,
            messageCompiler: $this->messageCompiler,
            tools: $this->tools,
            executor: $this->executor,
        );
    }

    #[\Override]
    public function messageCompiler(): CanCompileMessages {
        return $this->messageCompiler;
    }

    #[\Override]
    public function withMessageCompiler(CanCompileMessages $compiler): static {
        return new self(
            steps: $this->steps,
            defaultResponse: $this->defaultResponse,
            defaultUsage: $this->defaultUsage,
            defaultStepType: $this->defaultStepType,
            childSteps: $this->childSteps,
            messageCompiler: $compiler,
            tools: $this->tools,
            executor: $this->executor,
        );
    }

    #[\Override]
    public function withToolRuntime(Tools $tools, CanExecuteToolCalls $executor): static {
        return new self(
            steps: $this->steps,
            defaultResponse: $this->defaultResponse,
            defaultUsage: $this->defaultUsage,
            defaultStepType: $this->defaultStepType,
            childSteps: $this->childSteps,
            messageCompiler: $this->messageCompiler,
            tools: $tools,
            executor: $executor,
        );
    }

    #[\Override]
    public function useTools(AgentState $state): AgentState {
        [$step, $nextCursor] = $this->resolveStep($state);
        $step = match (true) {
            $step instanceof ScenarioStep => $this->makeToolUseStep($step, $state, $this->executor),
            default => $this->defaultStep($state),
        };
        return $state
            ->withMetadata(self::CURSOR_METADATA_KEY, $nextCursor)
            ->withCurrentStep($step);
    }

    /**
     * @return array{0: ScenarioStep|null, 1: int}
     */
    private function resolveStep(AgentState $state): array {
        $cursor = (int) $state->metadata()->get(self::CURSOR_METADATA_KEY, 0);
        if ($this->steps === []) {
            return [null, $cursor];
        }
        $lastIndex = count($this->steps) - 1;
        if ($cursor > $lastIndex) {
            return [$this->steps[$lastIndex], $cursor];
        }
        return [$this->steps[$cursor], $cursor + 1];
    }

    private function makeToolUseStep(ScenarioStep $step, AgentState $state, CanExecuteToolCalls $executor): AgentStep {
        $inputMessages = $this->messageCompiler->compile($state);
        $toolCalls = $step->toolCalls ?? ToolCalls::empty();
        if ($toolCalls->hasNone()) {
            return $step->toAgentStep($state, $inputMessages);
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
            inputMessages: $inputMessages,
            outputMessages: Messages::fromString($step->response, 'assistant'),
            inferenceResponse: $response,
            toolExecutions: $executions,
            errors: $errors,
        );
    }

    private function defaultStep(AgentState $state): AgentStep {
        $inputMessages = $this->messageCompiler->compile($state);
        $response = new InferenceResponse(
            toolCalls: ToolCalls::empty(),
            usage: $this->defaultUsage,
        );
        $errors = $this->errorsForType($this->defaultStepType);
        return new AgentStep(
            inputMessages: $inputMessages,
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
