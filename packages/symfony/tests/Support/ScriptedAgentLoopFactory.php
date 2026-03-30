<?php

declare(strict_types=1);

namespace Cognesy\Instructor\Symfony\Tests\Support;

use Cognesy\Agents\CanControlAgentLoop;
use Cognesy\Agents\Data\AgentState;
use Cognesy\Agents\Template\Contracts\CanInstantiateAgentLoop;
use Cognesy\Agents\Template\Data\AgentDefinition;

final class ScriptedAgentLoopFactory implements CanInstantiateAgentLoop
{
    /** @var list<string> */
    private array $responses;

    /** @var list<AgentState> */
    private array $recorded = [];

    private int $nextResponse = 0;

    /**
     * @param list<string> $responses
     */
    public function __construct(array $responses)
    {
        $this->responses = $responses === [] ? ['ok'] : array_values($responses);
    }

    public static function fromResponses(string ...$responses): self
    {
        return new self($responses);
    }

    /** @return list<AgentState> */
    public function recorded(): array
    {
        return $this->recorded;
    }

    public function instantiateAgentLoop(AgentDefinition $definition): CanControlAgentLoop
    {
        return new ScriptedAgentLoop(
            response: $this->nextPlannedResponse(),
            onExecute: function (AgentState $state): void {
                $this->recorded[] = $state;
            },
        );
    }

    private function nextPlannedResponse(): string
    {
        $index = min($this->nextResponse, count($this->responses) - 1);
        $this->nextResponse++;

        return $this->responses[$index];
    }
}

final readonly class ScriptedAgentLoop implements CanControlAgentLoop
{
    /**
     * @param \Closure(AgentState): void $onExecute
     */
    public function __construct(
        private string $response,
        private \Closure $onExecute,
    ) {}

    public function execute(AgentState $state): AgentState
    {
        ($this->onExecute)($state);

        return $state->withMessages($state->messages()->asAssistant($this->response));
    }

    public function iterate(AgentState $state): iterable
    {
        yield $this->execute($state);
    }
}
