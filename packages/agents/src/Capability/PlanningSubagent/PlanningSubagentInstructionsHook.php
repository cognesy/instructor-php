<?php declare(strict_types=1);

namespace Cognesy\Agents\Capability\PlanningSubagent;

use Cognesy\Agents\Hook\Contracts\HookInterface;
use Cognesy\Agents\Hook\Data\HookContext;

final readonly class PlanningSubagentInstructionsHook implements HookInterface
{
    public function __construct(
        private string $instructions,
    ) {}

    #[\Override]
    public function handle(HookContext $context): HookContext
    {
        $state = $context->state();

        $fragment = trim($this->instructions);
        if ($fragment === '') {
            return $context;
        }

        $current = trim($state->context()->systemPrompt());
        $prompt = match (true) {
            $current === '' => $fragment,
            str_contains($current, $fragment) => $current,
            default => $current . "\n\n" . $fragment,
        };

        return $context->withState($state->withSystemPrompt($prompt));
    }
}
