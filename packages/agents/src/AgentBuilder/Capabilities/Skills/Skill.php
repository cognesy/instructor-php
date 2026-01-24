<?php declare(strict_types=1);

namespace Cognesy\Agents\AgentBuilder\Capabilities\Skills;

class Skill
{
    /** @param list<string> $resources */
    public function __construct(
        public string $name,
        public string $description,
        public string $body,
        public string $path,
        public array $resources = [],
    ) {}

    public function toArray(): array {
        return [
            'name' => $this->name,
            'description' => $this->description,
            'body' => $this->body,
            'path' => $this->path,
            'resources' => $this->resources,
        ];
    }

    public function render(): string {
        $parts = [];
        $parts[] = "<skill name=\"{$this->name}\">";
        $parts[] = $this->body;

        if (!empty($this->resources)) {
            $parts[] = "";
            $parts[] = "## Available Resources";
            foreach ($this->resources as $resource) {
                $parts[] = "- {$resource}";
            }
        }

        $parts[] = "</skill>";

        return implode("\n", $parts);
    }

    public function renderMetadata(): string {
        return "[{$this->name}]: {$this->description}";
    }
}
