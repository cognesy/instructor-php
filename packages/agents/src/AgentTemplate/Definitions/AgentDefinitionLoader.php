<?php declare(strict_types=1);

namespace Cognesy\Agents\AgentTemplate\Definitions;

use InvalidArgumentException;
use RuntimeException;
use Symfony\Component\Yaml\Yaml;

final class AgentDefinitionLoader
{
    public function loadFile(string $path): AgentDefinition {
        $content = @file_get_contents($path);
        if ($content === false) {
            throw new RuntimeException("Failed to read agent definition file: {$path}");
        }

        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        $data = match ($extension) {
            'yaml', 'yml' => $this->parseYaml($content),
            'md' => $this->parseMarkdown($content),
            default => throw new InvalidArgumentException(
                "Unsupported file extension '.{$extension}' for agent definition: {$path}"
            ),
        };

        return AgentDefinition::fromArray($data);
    }

    /** @return array<string, mixed> */
    private function parseYaml(string $content): array {
        try {
            $parsed = Yaml::parse(
                trim($content),
                Yaml::PARSE_EXCEPTION_ON_INVALID_TYPE
            );
        } catch (\Throwable $e) {
            throw new InvalidArgumentException(
                "Invalid YAML: {$e->getMessage()}",
                previous: $e
            );
        }

        if (!is_array($parsed)) {
            throw new InvalidArgumentException("Invalid YAML: agent definition must be a map");
        }

        return $parsed;
    }

    /** @return array<string, mixed> */
    private function parseMarkdown(string $content): array {
        $content = str_replace("\r\n", "\n", $content);

        if (!preg_match('/^---\s*\n(.*?)\n---\s*\n(.*)$/s', $content, $matches)) {
            throw new InvalidArgumentException(
                "Invalid markdown format: missing YAML frontmatter (expected ---\\n...\\n---)"
            );
        }

        $data = $this->parseYaml($matches[1]);
        $systemPrompt = trim($matches[2]);

        if ($systemPrompt === '') {
            throw new InvalidArgumentException(
                "Invalid markdown format: system prompt (content after frontmatter) cannot be empty"
            );
        }

        $data['systemPrompt'] = $systemPrompt;

        return $data;
    }
}
