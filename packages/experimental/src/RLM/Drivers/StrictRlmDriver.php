<?php declare(strict_types=1);

namespace Cognesy\Experimental\RLM\Drivers;

use Cognesy\Experimental\RLM\Data\Policy;
use Cognesy\Experimental\RLM\Data\Repl\ReplInventory;
use Cognesy\Experimental\RLM\Protocol\RlmActionStructures;
use Cognesy\Instructor\PendingStructuredOutput;
use Cognesy\Instructor\StructuredOutput;
use Cognesy\Messages\Messages;

final class StrictRlmDriver
{
    /**
     * Extracts an RLM action via StructuredOutput and returns a pending request for later `get()/response()`.
     */
    public function extractAction(Messages $messages, ReplInventory $inventory, Policy $policy): PendingStructuredOutput
    {
        $system = RlmPrompt::buildSystemPrompt($inventory, $policy);
        $structure = RlmActionStructures::decision();

        return (new StructuredOutput())
            ->using('openai')
            ->withSystem($system)
            ->withMessages($messages)
            ->withResponseModel($structure)
            ->withMaxRetries(2)
            ->withOptions(['temperature' => 0.0])
            ->create();
    }
}

