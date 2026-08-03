<?php declare(strict_types=1);

namespace Cognesy\Agents\Template;

use Cognesy\Agents\Template\Data\AgentDefinition;
use Cognesy\Agents\Template\Data\StoredAgentDefinition;
use RuntimeException;
use Throwable;

final readonly class FileAgentDefinitionStore
{
    private string $root;

    public function __construct(
        string $root,
        private AgentDefinitionSerializer $serializer = new AgentDefinitionSerializer(),
    ) {
        $resolved = realpath($root);
        if (!is_string($resolved) || !is_dir($resolved) || !is_writable($resolved)) {
            throw new RuntimeException("Agent definition root must be an existing writable directory: {$root}");
        }
        $this->root = $resolved;
    }

    public function root(): string {
        return $this->root;
    }

    public function path(string $name): string {
        return $this->root . '/' . AgentDefinitionName::fromString($name)->filename();
    }

    public function save(
        AgentDefinition $definition,
        bool $overwrite = false,
    ): StoredAgentDefinition {
        $path = $this->path($definition->name);
        $replaced = file_exists($path);
        if ($replaced && !$overwrite) {
            throw new RuntimeException("Agent definition '{$definition->name}' already exists; set overwrite to replace it.");
        }

        $contents = $this->serializer->toMarkdown($definition);
        $temporary = tempnam($this->root, '.agent-definition-');
        if (!is_string($temporary)) {
            throw new RuntimeException("Could not create a temporary file in '{$this->root}'.");
        }

        try {
            $written = file_put_contents($temporary, $contents, LOCK_EX);
            if ($written !== strlen($contents) || !rename($temporary, $path)) {
                throw new RuntimeException("Could not persist agent definition '{$definition->name}'.");
            }
        } catch (Throwable $throwable) {
            if (file_exists($temporary)) {
                unlink($temporary);
            }
            throw $throwable;
        }
        return new StoredAgentDefinition($path, $replaced);
    }
}
