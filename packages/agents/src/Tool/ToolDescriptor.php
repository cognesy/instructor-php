<?php declare(strict_types=1);

namespace Cognesy\Agents\Tool;

use Cognesy\Agents\Tool\Contracts\CanDescribeTool;

readonly class ToolDescriptor implements CanDescribeTool
{
    public function __construct(
        private string $name,
        private string $description,
        private array $metadata = [],
        private array $instructions = [],
    ) {}

    #[\Override]
    public function name(): string {
        return $this->name;
    }

    #[\Override]
    public function description(): string {
        return $this->description;
    }

    #[\Override]
    public function metadata(): array {
        return array_merge([
            'name' => $this->name,
            'summary' => $this->description,
        ], $this->metadata);
    }

    #[\Override]
    public function instructions(): array {
        return array_merge([
            'name' => $this->name,
            'description' => $this->description,
            'parameters' => [],
            'returns' => 'mixed',
        ], $this->instructions);
    }
}
