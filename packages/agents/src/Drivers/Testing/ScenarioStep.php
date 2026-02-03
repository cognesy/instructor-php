<?php declare(strict_types=1);

namespace Cognesy\Agents\Drivers\Testing;

use Cognesy\Agents\Core\Collections\ErrorList;
use Cognesy\Agents\Core\Data\AgentState;
use Cognesy\Agents\Core\Data\AgentStep;
use Cognesy\Agents\Core\Enums\AgentStepType;
use Cognesy\Messages\Messages;
use Cognesy\Polyglot\Inference\Collections\ToolCalls;
use Cognesy\Polyglot\Inference\Data\InferenceResponse;
use Cognesy\Polyglot\Inference\Data\Usage;

final readonly class ScenarioStep
{
    public function __construct(
        public string        $response,
        public Usage         $usage,
        public AgentStepType $stepType,
        public ?ToolCalls    $toolCalls = null,
        public bool          $executeTools = true,
    ) {}

    public static function final(string $response, ?Usage $usage = null): self
    {
        return new self(
            response: $response,
            usage: $usage ?? new Usage(0, 0),
            stepType: AgentStepType::FinalResponse,
        );
    }

    public static function tool(string $response, ?Usage $usage = null): self
    {
        return new self(
            response: $response,
            usage: $usage ?? new Usage(0, 0),
            stepType: AgentStepType::ToolExecution,
        );
    }

    public static function error(string $response, ?Usage $usage = null): self
    {
        return new self(
            response: $response,
            usage: $usage ?? new Usage(0, 0),
            stepType: AgentStepType::Error,
        );
    }

    public static function toolCall(
        string $toolName,
        array  $args = [],
        string $response = '',
        ?Usage $usage = null,
        bool   $executeTools = true,
    ): self
    {
        $toolCalls = ToolCalls::empty()->withAddedToolCall($toolName, $args);
        return new self(
            response: $response,
            usage: $usage ?? new Usage(0, 0),
            stepType: AgentStepType::ToolExecution,
            toolCalls: $toolCalls,
            executeTools: $executeTools,
        );
    }

    public function toAgentStep(AgentState $state): AgentStep
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
            inputMessages: $state->context()->messagesForInference(),
            outputMessages: Messages::fromString($this->response, 'assistant'),
            inferenceResponse: $response,
            errors: $errors,
        );
    }
}
