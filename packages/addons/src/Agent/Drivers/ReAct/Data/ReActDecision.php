<?php declare(strict_types=1);

/**
 * @deprecated Use cognesy/agents package instead. This class will be removed in a future version.
 */
namespace Cognesy\Addons\Agent\Drivers\ReAct\Data;

use Cognesy\Addons\Agent\Drivers\ReAct\Contracts\Decision;
use Cognesy\Schema\Attributes\Description;

final class ReActDecision implements Decision
{
    #[Description('Brief reasoning for the next action')]
    public string $thought = '';
    #[Description('Decision type - call_tool or final_answer')]
    public string $type = '';
    #[Description('Tool name to call when type=call_tool')]
    public ?string $tool = null;
    #[Description('Arguments for the selected tool as a JSON object (not array) with parameter names as keys, e.g. {"arg1": "val1", "arg2": 123}')]
    public array $args = [];
    #[Description('Final answer when type=final_answer')]
    public ?string $answer = null;

    #[\Override]
    public function thought(): string {
        return $this->thought;
    }

    public function type(): string {
        return $this->type;
    }

    #[\Override]
    public function isCall(): bool {
        return $this->type === 'call_tool';
    }

    public function isFinal(): bool {
        return $this->type === 'final_answer';
    }

    #[\Override]
    public function tool(): ?string {
        return $this->tool;
    }

    #[\Override]
    public function args(): array {
        return $this->args;
    }

    public function setArgs(array $args): void {
        $this->args = $args;
    }

    public function answer(): string {
        return $this->answer ?? '';
    }
}
