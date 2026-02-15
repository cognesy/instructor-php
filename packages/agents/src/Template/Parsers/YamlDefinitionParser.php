<?php declare(strict_types=1);

namespace Cognesy\Agents\Template\Parsers;

use Cognesy\Agents\Template\Data\AgentDefinition;
use InvalidArgumentException;
use Symfony\Component\Yaml\Yaml;

final readonly class YamlDefinitionParser implements CanParseAgentDefinition
{
    #[\Override]
    public function parse(mixed $data): AgentDefinition {
        $array = match (true) {
            is_array($data) => $data,
            is_string($data) => $this->parseYaml($data),
            default => throw new InvalidArgumentException('YAML agent definition must be a string or array.'),
        };

        return AgentDefinition::fromArray($array);
    }

    /** @return array<string, mixed> */
    private function parseYaml(string $yaml): array {
        try {
            $parsed = Yaml::parse(trim($yaml), Yaml::PARSE_EXCEPTION_ON_INVALID_TYPE);
        } catch (\Throwable $e) {
            throw new InvalidArgumentException("Invalid YAML: {$e->getMessage()}", previous: $e);
        }

        if (!is_array($parsed)) {
            throw new InvalidArgumentException("Invalid YAML: agent definition must be a map");
        }

        return $parsed;
    }
}

