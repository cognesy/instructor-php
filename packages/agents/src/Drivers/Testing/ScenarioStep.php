<?php declare(strict_types=1);

namespace Cognesy\Agents\Drivers\Testing;

use Cognesy\Agents\Data\AgentState;
use Cognesy\Agents\Data\AgentStep;
use Cognesy\Agents\Enums\AgentStepType;
use Cognesy\Messages\Messages;
use Cognesy\Messages\ToolCalls;
use Cognesy\Polyglot\Inference\Data\InferenceResponse;
use Cognesy\Polyglot\Inference\Data\InferenceUsage;
use Cognesy\Utils\Exceptions\ErrorList;

final readonly class ScenarioStep
{
    public function __construct(
        public string        $response,
        public InferenceUsage         $usage,
        public AgentStepType $stepType,
        public ?ToolCalls    $toolCalls = null,
        public bool          $executeTools = true,
    ) {}

    public static function final(string $response, ?InferenceUsage $usage = null): self
    {
        return new self(
            response: $response,
            usage: $usage ?? new InferenceUsage(0, 0),
            stepType: AgentStepType::FinalResponse,
        );
    }

    public static function tool(string $response, ?InferenceUsage $usage = null): self
    {
        return new self(
            response: $response,
            usage: $usage ?? new InferenceUsage(0, 0),
            stepType: AgentStepType::ToolExecution,
        );
    }

    public static function error(string $response, ?InferenceUsage $usage = null): self
    {
        return new self(
            response: $response,
            usage: $usage ?? new InferenceUsage(0, 0),
            stepType: AgentStepType::Error,
        );
    }

    public static function toolCall(
        string $toolName,
        array  $args = [],
        string $response = '',
        ?InferenceUsage $usage = null,
        bool   $executeTools = true,
    ): self
    {
        $toolCalls = ToolCalls::empty()->withAddedToolCall($toolName, $args);
        return new self(
            response: $response,
            usage: $usage ?? new InferenceUsage(0, 0),
            stepType: AgentStepType::ToolExecution,
            toolCalls: $toolCalls,
            executeTools: $executeTools,
        );
    }

    public function toAgentStep(AgentState $state, ?Messages $inputMessages = null): AgentStep
    {
        $errors = match ($this->stepType) {
            AgentStepType::Error => new ErrorList(new \RuntimeException('Scenario step marked as error')),
            default => ErrorList::empty(),
        };

        $response = new InferenceResponse(
            toolCalls: $this->toolCalls ?? ToolCalls::empty(),
            usage: $this->usage,
        );

        return new AgentStep(
            inputMessages: $inputMessages ?? $state->store()->toMessages(),
            outputMessages: Messages::fromString($this->response, 'assistant'),
            inferenceResponse: $response,
            errors: $errors,
        );
    }
}
