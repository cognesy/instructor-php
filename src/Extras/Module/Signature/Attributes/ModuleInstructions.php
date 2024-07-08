<?php
namespace Cognesy\Instructor\Extras\Module\Signature\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
class ModuleInstructions
{
    public array $instructions;

    public function __construct(
        public string|array $text,
    ) {
        $this->instructions = is_array($text) ? $text : [$text];
    }

    public function get(): array {
        return $this->instructions;
    }

    public function add(string $instruction): void {
        $this->instructions[] = $instruction;
    }

    public function clear(): void {
        $this->instructions = [];
    }

    public function text(): string {
        return implode("\n", $this->instructions);
    }
}
