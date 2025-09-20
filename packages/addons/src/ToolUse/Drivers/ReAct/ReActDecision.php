<?php declare(strict_types=1);

namespace Cognesy\Addons\ToolUse\Drivers\ReAct;

final class ReActDecision
{
    public string $thought = '';
    public string $type = '';
    public ?string $tool = null;
    public array $args = [];
    public ?string $answer = null;

    public function __construct(
        string $thought = '',
        string $type = '',
        ?string $tool = null,
        ?array $args = null,
        ?string $answer = null,
    ) {
        $this->thought = $thought;
        $this->type = $type;
        $this->tool = $tool;
        $this->args = $args ?? [];
        $this->answer = $answer;
    }

    public function setThought(string $thought) : void { $this->thought = $thought; }
    public function setType(string $type) : void { $this->type = $type; }
    public function setTool(?string $tool) : void { $this->tool = $tool; }
    /** @param array<string,mixed> $args */
    public function setArgs(array $args) : void { $this->args = $args; }
    public function setAnswer(?string $answer) : void { $this->answer = $answer; }

    public static function fromArray(array $data) : self {
        return new self(
            thought: (string)($data['thought'] ?? ''),
            type: (string)($data['type'] ?? ''),
            tool: $data['tool'] ?? null,
            args: is_string($data['args'] ?? null)
                ? (json_decode($data['args'], true) ?: [])
                : ($data['args'] ?? []),
            answer: $data['answer'] ?? null,
        );
    }

    public function thought() : string { return $this->thought; }
    public function type() : string { return $this->type; }
    public function isCall() : bool { return $this->type === 'call_tool'; }
    public function isFinal() : bool { return $this->type === 'final_answer'; }
    public function tool() : ?string { return $this->tool; }
    public function args() : array { return $this->args; }
    public function answer() : string { return $this->answer ?? ''; }
}
