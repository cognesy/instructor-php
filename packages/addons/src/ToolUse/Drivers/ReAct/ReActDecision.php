<?php declare(strict_types=1);

namespace Cognesy\Addons\ToolUse\Drivers\ReAct;

final class ReActDecision
{
    /**
     * Public properties enable direct deserialization by StructuredOutput.
     */
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

    // Symfony serializer-friendly setters
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

    public static function jsonSchema() : array {
        return [
            'type' => 'object',
            'properties' => [
                'thought' => ['type' => 'string', 'description' => 'Brief reasoning for the next action.'],
                'type' => ['type' => 'string', 'enum' => ['call_tool', 'final_answer']],
                'tool' => ['type' => 'string', 'description' => 'Tool name to call if type=call_tool'],
                'args' => ['type' => 'string', 'description' => 'JSON string with arguments for the tool.'],
                'answer' => ['type' => 'string', 'description' => 'Final answer when type=final_answer'],
            ],
            'required' => ['thought', 'type'],
            'allOf' => [
                [ 'if' => [ 'properties' => ['type' => ['const' => 'call_tool']] ], 'then' => [ 'required' => ['tool', 'args'] ] ],
                [ 'if' => [ 'properties' => ['type' => ['const' => 'final_answer']] ], 'then' => [ 'required' => ['answer'] ] ],
            ],
        ];
    }

    public function thought() : string { return $this->thought; }
    public function type() : string { return $this->type; }
    public function isCall() : bool { return $this->type === 'call_tool'; }
    public function isFinal() : bool { return $this->type === 'final_answer'; }
    public function tool() : ?string { return $this->tool; }
    public function args() : array { return $this->args; }
    public function answer() : string { return $this->answer ?? ''; }
}
