<?php declare(strict_types=1);

namespace Cognesy\Agents\Agent\Data;

use Cognesy\Agents\Agent\Collections\NameList;
use InvalidArgumentException;

final readonly class AgentDescriptor
{
    public function __construct(
        public string $name,
        public string $description,
        public NameList $tools,
        public NameList $capabilities,
    ) {
        $this->assertNotEmpty($name, 'name');
        $this->assertNotEmpty($description, 'description');
    }

    public function toArray(): array {
        return [
            'name' => $this->name,
            'description' => $this->description,
            'tools' => $this->tools->toArray(),
            'capabilities' => $this->capabilities->toArray(),
        ];
    }

    private function assertNotEmpty(string $value, string $field): void {
        if ($value !== '') {
            return;
        }
        throw new InvalidArgumentException("Agent descriptor {$field} cannot be empty.");
    }
}
