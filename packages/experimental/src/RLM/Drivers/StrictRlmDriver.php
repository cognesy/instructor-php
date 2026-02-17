<?php declare(strict_types=1);

namespace Cognesy\Experimental\RLM\Drivers;

use Cognesy\Experimental\RLM\Data\Policy;
use Cognesy\Experimental\RLM\Data\Repl\ReplInventory;
use Cognesy\Experimental\RLM\Protocol\RlmActionStructures;
use Cognesy\Instructor\Contracts\CanCreateStructuredOutput;
use Cognesy\Instructor\Data\StructuredOutputRequest;
use Cognesy\Instructor\PendingStructuredOutput;
use Cognesy\Messages\Messages;

final class StrictRlmDriver
{
    public function __construct(
        private CanCreateStructuredOutput $structuredOutput,
    ) {}

    /**
     * Extracts an RLM action via StructuredOutput and returns a pending request for later `get()/response()`.
     */
    public function extractAction(Messages $messages, ReplInventory $inventory, Policy $policy): PendingStructuredOutput
    {
        $system = RlmPrompt::buildSystemPrompt($inventory, $policy);
        $structure = RlmActionStructures::decision();
        $request = new StructuredOutputRequest(
            messages: $messages,
            requestedSchema: $structure,
            system: $system,
            options: ['temperature' => 0.0],
        );

        return $this->structuredOutput->create($request);
    }
}
